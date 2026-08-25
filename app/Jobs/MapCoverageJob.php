<?php

namespace App\Jobs;

use App\Enums\KnowledgeState;
use App\Models\Fact;
use App\Models\Thread;
use App\Services\Ollama\OllamaClient;
use App\Support\Vector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Works out what a complete answer to the thread's question would need, and
 * how much of it is actually in hand.
 *
 * The model only names the sub-topics; the counting is done here against the
 * fact embeddings. Asking a local model to also tally its own coverage gets
 * confident numbers that are not true, and the whole point of this panel is
 * to be trusted when it says something is missing.
 */
class MapCoverageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    /** Thread deleted mid-flight: drop the job rather than failing it. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public Thread $thread)
    {
        $this->onQueue(config('research.knowledge_queue'));
    }

    public function handle(OllamaClient $ollama): void
    {
        $thread = $this->thread->fresh();

        if ($thread === null) {
            return;
        }

        /** @var Collection<int, Fact> $facts */
        $facts = $thread->facts()->active()->whereNotNull('embedding')->get();

        if ($facts->isEmpty()) {
            return;
        }

        $thread->markKnowledgeState(KnowledgeState::Mapping);

        try {
            $topics = $this->proposeTopics($ollama, $thread, $facts);
        } catch (Throwable $e) {
            report($e);
            $thread->markKnowledgeState(KnowledgeState::Idle);

            return;
        }

        if ($topics === []) {
            $thread->markKnowledgeState(KnowledgeState::Idle);

            return;
        }

        $thread->forceFill([
            'coverage' => $this->measure($ollama, $topics, $facts),
            'coverage_mapped_at' => now(),
            'knowledge_state' => KnowledgeState::Idle,
        ])->save();
    }

    /**
     * @param  Collection<int, Fact>  $facts
     * @return list<array{topic: string, why: string|null}>
     */
    protected function proposeTopics(OllamaClient $ollama, Thread $thread, Collection $facts): array
    {
        $limit = (int) config('research.coverage_topics');

        $sample = $facts
            ->sortByDesc('confidence')
            ->take(60)
            ->map(fn (Fact $fact): string => "- {$fact->statement}")
            ->implode("\n");

        $response = $ollama->structured([
            [
                'role' => 'system',
                'content' => "You break a research question into the sub-topics a complete answer would have to cover. Name at most {$limit}, each a short noun phrase, ordered by how central it is to the question. Cover the whole question, not only the parts already researched — a sub-topic nobody has touched is the most useful thing you can name. Give a one-line reason for each. Return JSON only.",
            ],
            [
                'role' => 'user',
                'content' => <<<PROMPT
                Research question: {$thread->seed_question}
                Topic: {$thread->title}

                What has been found so far:
                {$sample}
                PROMPT,
            ],
        ], $this->schema());

        /** @var list<mixed> $rows */
        $rows = array_values((array) ($response['topics'] ?? []));

        return array_values(collect($rows)
            ->map(fn (mixed $row): ?array => is_array($row) && filled($row['topic'] ?? null) ? [
                'topic' => str((string) $row['topic'])->limit(120)->toString(),
                'why' => filled($row['why'] ?? null) ? str((string) $row['why'])->limit(200)->toString() : null,
            ] : null)
            ->filter()
            ->take($limit)
            ->all());
    }

    /**
     * Count the facts that actually speak to each sub-topic.
     *
     * @param  list<array{topic: string, why: string|null}>  $topics
     * @param  Collection<int, Fact>  $facts
     * @return list<array{topic: string, why: string|null, facts_count: int, status: string}>
     */
    protected function measure(OllamaClient $ollama, array $topics, Collection $facts): array
    {
        $vectors = $ollama->embed(array_column($topics, 'topic'));
        $floor = (float) config('research.coverage_min_similarity');
        $thin = (int) config('research.coverage_thin_below');
        $embeddings = $facts->pluck('embedding')->all();

        $mapped = [];

        foreach ($topics as $index => $topic) {
            $vector = $vectors[$index] ?? null;

            $count = is_array($vector)
                ? count(array_filter(
                    $embeddings,
                    fn (mixed $embedding): bool => is_array($embedding) && Vector::cosine($vector, $embedding) >= $floor,
                ))
                : 0;

            $mapped[] = [
                ...$topic,
                'facts_count' => $count,
                'status' => match (true) {
                    $count === 0 => 'open',
                    $count < $thin => 'thin',
                    default => 'covered',
                },
            ];
        }

        return $mapped;
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topics' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'topic' => ['type' => 'string'],
                            'why' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['topic'],
                    ],
                ],
            ],
            'required' => ['topics'],
        ];
    }
}
