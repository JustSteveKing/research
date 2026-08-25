<?php

namespace App\Jobs;

use App\Enums\KnowledgeState;
use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\Thread;
use App\Services\Ollama\OllamaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Regenerates the thread's living brief from everything learned so far.
 */
class SynthesizeThreadBriefJob implements ShouldQueue
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

        $thread->markKnowledgeState(KnowledgeState::Writing);

        $rows = $thread->facts()
            ->active()
            ->with('source:id,url,title')
            ->orderByDesc('pinned_at')
            ->orderByDesc('confidence')
            ->limit(120)
            ->get();

        $facts = $rows
            ->map(fn (Fact $fact): string => sprintf(
                '- %s (source: %s%s%s)',
                $fact->statement,
                $fact->source_id === null ? 'n/a' : $fact->source->url,
                $fact->isStale() ? ', stale: '.$fact->ageInDays().' days old' : '',
                $fact->isPinned() ? ', confirmed by the reader' : '',
            ))
            ->implode("\n");

        $questions = $thread->runs()->pluck('query')->implode("\n- ");
        $disputes = $this->describeDisputes($rows);

        if (trim($facts) === '') {
            $thread->markKnowledgeState(KnowledgeState::Idle);

            return;
        }

        try {
            $brief = $ollama->chat([
                [
                    'role' => 'system',
                    'content' => 'You maintain a living research brief in markdown. Structure it as: a two-sentence answer, "## What we know" with grouped bullet points, "## Tensions and uncertainties", and "## Open threads". Put every flagged disagreement under "## Tensions and uncertainties" and say which side you find better supported and why. Mark anything labelled stale as needing a re-check. Cite source URLs inline as markdown links. Be concise and concrete.',
                ],
                [
                    'role' => 'user',
                    'content' => <<<PROMPT
                    Research topic: {$thread->title}
                    Original question: {$thread->seed_question}

                    Questions researched so far:
                    - {$questions}

                    Facts gathered:
                    {$facts}

                    Flagged disagreements:
                    {$disputes}
                    PROMPT,
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        if (trim($brief) === '') {
            return;
        }

        $thread->forceFill([
            'brief' => $brief,
            'brief_generated_at' => now(),
            'knowledge_state' => KnowledgeState::Idle,
            // Recomputed here because the brief is the fullest description of
            // what the thread is about, and it is what related threads match on.
            'embedding' => $ollama->embedOne($this->topic($thread, $brief)),
        ])->save();
    }

    /**
     * The disagreements the detector found between these facts and anything
     * else known, so the brief can address them instead of averaging them out.
     *
     * @param  Collection<int, Fact>  $facts
     */
    protected function describeDisputes(Collection $facts): string
    {
        $lines = Contradiction::forFacts($facts)
            ->with(['fact:id,statement', 'otherFact:id,statement'])
            ->limit(20)
            ->get()
            ->map(fn (Contradiction $clash): string => sprintf(
                '- "%s" vs "%s"%s',
                $clash->fact->statement,
                $clash->otherFact->statement,
                $clash->note === null ? '' : ' — '.$clash->note,
            ));

        return $lines->isEmpty() ? 'None found.' : $lines->implode("\n");
    }

    /**
     * What the thread is about, in the few thousand characters an embedding
     * model will actually read.
     */
    protected function topic(Thread $thread, string $brief): string
    {
        return str("{$thread->title}\n{$thread->seed_question}\n{$brief}")->limit(4000)->toString();
    }
}
