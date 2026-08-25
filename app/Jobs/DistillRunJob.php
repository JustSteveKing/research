<?php

namespace App\Jobs;

use App\Enums\KnowledgeState;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Source;
use App\Services\Ollama\OllamaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Distils a finished run into atomic facts.
 *
 * Fast mode returns cited pages with no claims or summaries, so the report is
 * the only place the knowledge lives. Each fact is attributed back to one of
 * the cited URLs where the model can tell which source supports it.
 */
class DistillRunJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    /** Thread deleted mid-flight: drop the job rather than failing it. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public ResearchRun $run)
    {
        $this->onQueue(config('research.knowledge_queue'));
    }

    public function handle(OllamaClient $ollama): void
    {
        $run = $this->run->fresh();

        if ($run === null || blank($run->report)) {
            return;
        }

        $run->thread->markKnowledgeState(KnowledgeState::Distilling);

        /** @var Collection<int, Source> $sources */
        $sources = $run->thread->sources()->get();

        $response = $ollama->structured([
            [
                'role' => 'system',
                'content' => 'You extract atomic, self-contained facts from a research report. Each fact must stand alone without the surrounding context, keep concrete numbers, names and dates, and carry no opinion. Attribute each fact to the URL that supports it, chosen only from the supplied sources; use null when no single source is clearly responsible. Return JSON only.',
            ],
            [
                'role' => 'user',
                'content' => <<<PROMPT
                Research topic: {$run->thread->title}
                Question this report answers: {$run->query}

                Sources cited by the report:
                {$this->describeSources($sources)}

                Report:
                {$this->trimReport($run->report)}

                Extract up to 20 atomic facts. Give each a confidence between 0 and 1, 1-3 lowercase topic tags, and the source URL that backs it.
                PROMPT,
            ],
        ], $this->schema());

        $this->store($run, $sources, $response['facts'] ?? []);

        $run->thread->sources()->where('distilled', false)->update(['distilled' => true]);
    }

    /**
     * @param  Collection<int, Source>  $sources
     * @param  array<int, array<string, mixed>>  $facts
     */
    protected function store(ResearchRun $run, Collection $sources, array $facts): void
    {
        $statements = [];
        $rows = [];

        foreach (array_slice($facts, 0, 20) as $fact) {
            $statement = trim((string) ($fact['statement'] ?? ''));

            if ($statement === '') {
                continue;
            }

            $statements[] = $statement;
            $rows[] = $fact;
        }

        if ($statements === []) {
            return;
        }

        $embeddings = $this->embed($statements);
        $seen = $this->existingStatements($run->thread_id);

        foreach ($statements as $index => $statement) {
            // Only exact repeats are dropped here. Cosine cannot separate a
            // paraphrase from a flat contradiction — with nomic-embed-text
            // "targets 2027" against "targets 2030" scores 0.98, higher than
            // a genuine reword of the same claim — so near-duplicates are
            // stored and DetectContradictionsJob decides which they are.
            if (isset($seen[$this->fingerprint($statement)])) {
                continue;
            }

            $seen[$this->fingerprint($statement)] = true;
            $embedding = $embeddings[$index] ?? null;

            $source = $this->matchSource($sources, $rows[$index]['source_url'] ?? null);

            Fact::query()->create([
                'thread_id' => $run->thread_id,
                'source_id' => $source?->getKey(),
                'research_run_id' => $run->getKey(),
                'statement' => $statement,
                'tags' => array_values(array_map('strval', $rows[$index]['tags'] ?? [])),
                'confidence' => (float) ($rows[$index]['confidence'] ?? 0.6),
                'embedding' => $embedding,
                // A fact is only as current as the page it came from; without
                // a publication date the distillation date is the best we have.
                'asserted_at' => $source->published_at ?? now(),
            ]);

        }
    }

    /**
     * Statements the thread already holds, dismissed ones included: the
     * reader threw those out and a new run must not bring them back.
     *
     * @return array<string, bool>
     */
    protected function existingStatements(int $threadId): array
    {
        return Fact::query()
            ->where('thread_id', $threadId)
            ->pluck('statement')
            ->mapWithKeys(fn (string $statement): array => [$this->fingerprint($statement) => true])
            ->all();
    }

    protected function fingerprint(string $statement): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($statement)) ?? $statement);
    }

    /**
     * @param  list<string>  $statements
     * @return list<array<int, float>>
     */
    protected function embed(array $statements): array
    {
        $client = app(OllamaClient::class);

        try {
            return $client->embed($statements);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * @param  Collection<int, Source>  $sources
     */
    protected function matchSource(Collection $sources, mixed $url): ?Source
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return $sources->first(fn (Source $source): bool => $source->url === $url)
            ?? $sources->first(fn (Source $source): bool => str_contains($source->url, rtrim($url, '/')));
    }

    /**
     * @param  Collection<int, Source>  $sources
     */
    protected function describeSources(Collection $sources): string
    {
        if ($sources->isEmpty()) {
            return 'None.';
        }

        return $sources
            ->map(function (Source $source): string {
                $line = "- {$source->url} — ".($source->title ?? 'untitled');

                // Balanced mode adds per-page analysis; fast mode does not.
                if (filled($source->summary)) {
                    $line .= "\n  summary: {$source->summary}";
                }

                foreach ($source->claims ?? [] as $claim) {
                    $line .= "\n  claim: {$claim}";
                }

                return $line;
            })
            ->implode("\n");
    }

    protected function trimReport(string $report): string
    {
        return str($report)->limit(12000)->toString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'facts' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'statement' => ['type' => 'string'],
                            'confidence' => ['type' => 'number'],
                            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'source_url' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['statement', 'confidence'],
                    ],
                ],
            ],
            'required' => ['facts'],
        ];
    }
}
