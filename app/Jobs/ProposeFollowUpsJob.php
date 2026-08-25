<?php

namespace App\Jobs;

use App\Enums\KnowledgeState;
use App\Enums\QuestionOrigin;
use App\Enums\QuestionStatus;
use App\Models\FollowUpQuestion;
use App\Models\ResearchRun;
use App\Services\Ollama\OllamaClient;
use App\Services\Research\ThreadAutopilot;
use App\Support\Vector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Reads the gap evaluation of a finished run and proposes the questions that
 * would deepen the thread, then hands control to the autopilot.
 */
class ProposeFollowUpsJob implements ShouldQueue
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

    public function handle(OllamaClient $ollama, ThreadAutopilot $autopilot): void
    {
        $run = $this->run->fresh();

        if ($run === null) {
            return;
        }

        $run->thread->markKnowledgeState(KnowledgeState::Planning);

        try {
            $this->propose($ollama, $run);
        } catch (Throwable $e) {
            report($e);

            $this->storeGapQueriesAsFallback($run);
        }

        $autopilot->advance($run->thread->fresh());
    }

    protected function propose(OllamaClient $ollama, ResearchRun $run): void
    {
        $gaps = collect($run->gapEvaluations());

        $openQuestions = $gaps
            ->flatMap(fn (array $gap): array => $gap['questionAssessments'] ?? [])
            ->reject(fn (array $assessment): bool => ($assessment['status'] ?? '') === 'answered')
            ->map(fn (array $assessment): string => "- {$assessment['question']} ({$assessment['status']}): ".($assessment['findings'] ?? ''))
            ->implode("\n");

        $suggestedQueries = $gaps->flatMap(fn (array $gap): array => $gap['searchQueries'] ?? [])->implode("\n- ");
        $gapDescription = $gaps->pluck('gapDescription')->filter()->implode(' ');
        $knownFacts = $run->thread->facts()->latest()->limit(30)->pluck('statement')->implode("\n- ");
        $askedAlready = $run->thread->runs()->pluck('query')->implode("\n- ");
        $limit = (int) config('research.follow_ups_per_run');

        // Fast mode returns no gap evaluation, so the report is what the
        // proposals have to be grounded in.
        $report = str((string) $run->report)->limit(8000)->toString();

        $response = $ollama->structured([
            [
                'role' => 'system',
                'content' => 'You plan the next steps of an ongoing web research thread. Propose specific, standalone web-search questions that close knowledge gaps left by the report. Never repeat a question that has already been researched. Return JSON only.',
            ],
            [
                'role' => 'user',
                'content' => <<<PROMPT
                Research topic: {$run->thread->title}
                Original question: {$run->thread->seed_question}
                Query just completed: {$run->query}

                Report returned for that query:
                {$report}

                Identified gap: {$gapDescription}

                Unanswered or partial questions:
                {$openQuestions}

                Queries the research engine suggested:
                - {$suggestedQueries}

                Already researched:
                - {$askedAlready}

                Already known facts:
                - {$knownFacts}

                Propose at most {$limit} follow-up questions, each scored 0-1 for how much it would improve the thread, most valuable first.
                PROMPT,
            ],
        ], $this->schema());

        $this->store($run, $response['questions'] ?? [], QuestionOrigin::Ollama);
    }

    /**
     * When the local model is unavailable, fall back to the engine's own gap queries.
     */
    protected function storeGapQueriesAsFallback(ResearchRun $run): void
    {
        $questions = collect($run->gapEvaluations())
            ->flatMap(fn (array $gap): array => $gap['searchQueries'] ?? [])
            ->map(fn (string $query): array => ['question' => $query, 'score' => 0.5, 'rationale' => 'Suggested by the research engine.'])
            ->all();

        $this->store($run, $questions, QuestionOrigin::Gap);
    }

    /**
     * @param  array<int, array<string, mixed>>  $questions
     */
    protected function store(ResearchRun $run, array $questions, QuestionOrigin $origin): void
    {
        if ($questions === []) {
            return;
        }

        $ollama = app(OllamaClient::class);
        $threshold = (float) config('research.duplicate_threshold');

        $existingText = FollowUpQuestion::query()
            ->where('thread_id', $run->thread_id)
            ->pluck('question')
            ->map(fn (string $question): string => mb_strtolower($question))
            ->all();

        $existingVectors = FollowUpQuestion::query()
            ->where('thread_id', $run->thread_id)
            ->pluck('embedding')
            ->all();

        $previousQueries = $run->thread->runs()->pluck('query')
            ->map(fn (string $query): string => mb_strtolower($query))
            ->all();

        foreach (array_slice($questions, 0, (int) config('research.follow_ups_per_run')) as $question) {
            $text = trim((string) ($question['question'] ?? ''));

            if ($text === '' || in_array(mb_strtolower($text), [...$existingText, ...$previousQueries], true)) {
                continue;
            }

            $embedding = $ollama->embedOne($text);

            if (is_array($embedding) && Vector::maxSimilarity($embedding, $existingVectors) >= $threshold) {
                continue;
            }

            FollowUpQuestion::query()->create([
                'thread_id' => $run->thread_id,
                'research_run_id' => $run->getKey(),
                'question' => $text,
                'origin' => $origin,
                'status' => QuestionStatus::Pending,
                'score' => (float) ($question['score'] ?? 0.5),
                'rationale' => $question['rationale'] ?? null,
                'embedding' => $embedding,
            ]);

            $existingText[] = mb_strtolower($text);

            if (is_array($embedding)) {
                $existingVectors[] = $embedding;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'questions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question' => ['type' => 'string'],
                            'score' => ['type' => 'number'],
                            'rationale' => ['type' => 'string'],
                        ],
                        'required' => ['question', 'score'],
                    ],
                ],
            ],
            'required' => ['questions'],
        ];
    }
}
