<?php

namespace App\Jobs;

use App\Enums\KnowledgeState;
use App\Enums\QuestionStatus;
use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Models\FollowUpQuestion;
use App\Models\ResearchRun;
use App\Models\RunEvent;
use App\Services\Research\SourceRecorder;
use App\Services\Tabstack\TabstackClient;
use App\Services\Tabstack\TabstackException;
use App\Support\DesktopNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Streams a single Tabstack /research call, persisting every SSE event so the
 * UI can replay the run's progress without holding an open connection.
 */
class RunResearchJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    /**
     * A dropped connection should not cost a whole run. Only transport-level
     * failures reach a second attempt; an error event from Tabstack, or a
     * rejected request, is terminal. The credit reservation is held across
     * attempts and settled once, so a retry never double-charges.
     */
    public int $tries = 3;

    /** Thread deleted mid-flight: drop the job rather than failing it. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public ResearchRun $run)
    {
        $this->onQueue(config('research.queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(TabstackClient $tabstack, SourceRecorder $sources, DesktopNotifier $notifier): void
    {
        $run = $this->run->fresh();

        if ($run === null || $run->status->isFinished()) {
            return;
        }

        // Research streams are long and expensive, so only one is in flight at
        // a time whatever the worker configuration says. The lock covers the
        // network call alone: the knowledge chain dispatched further down
        // queues the next run, and must not be locked out by this one.
        $lock = Cache::lock('tabstack-research', $this->timeout + 60);

        if (! $lock->get()) {
            $this->release(15);

            return;
        }

        $run->forceFill([
            'status' => RunStatus::Streaming,
            'started_at' => now(),
            'phase' => 'start',
        ])->save();

        // A retry replays the stream from the top, so the partial trail the
        // last attempt left behind would otherwise show up twice.
        $run->events()->delete();

        $sequence = 0;
        $completed = false;
        $reported = null;
        $thrown = null;

        try {
            $tabstack->research($run->query, $run->mode, function (string $event, array $data) use ($run, &$sequence, &$completed, &$reported): void {
                $this->recordEvent($run, $event, $data, $sequence++);

                if ($event === 'complete') {
                    $run->forceFill([
                        'report' => (string) ($data['report'] ?? ''),
                        'metadata' => $data['metadata'] ?? [],
                    ])->save();

                    $completed = true;
                }

                if ($event === 'error') {
                    $reported = (string) ($data['message'] ?? 'Tabstack reported an error.');
                }
            });
        } catch (Throwable $e) {
            $thrown = $e;
        } finally {
            $lock->release();
        }

        if (! $completed) {
            // An error event is Tabstack's considered answer, so it stands.
            // Anything else is the connection, and worth another go.
            $failure = $reported !== null
                ? new TabstackException($reported)
                : ($thrown ?? TabstackException::truncatedStream());

            if ($reported === null && $this->shouldRetry($failure)) {
                throw $failure;
            }

            $this->fail($failure->getMessage(), $notifier);

            return;
        }

        $run->forceFill([
            'status' => RunStatus::Completed,
            'phase' => 'complete',
            'completed_at' => now(),
            'error' => null,
        ])->save();

        $this->settleCredits($run, spend: true);

        FollowUpQuestion::query()
            ->where('spawned_run_id', $run->getKey())
            ->update(['status' => QuestionStatus::Done]);

        $sources->record($run);

        // A watch run announces what changed instead, once the chain knows.
        if (! $run->is_watch) {
            $notifier->send('Research complete', str($run->query)->limit(80)->toString());
        }

        // Chained so follow-ups are planned against freshly distilled facts,
        // contradictions are found before the brief is written, and the brief
        // is written last. ProposeFollowUpsJob hands control to the autopilot
        // once it knows what is still missing.
        $thread = $run->thread;
        $thread->recordKnowledgeError(null);
        $thread->markKnowledgeState(KnowledgeState::Queued);

        Bus::chain(array_filter([
            new DistillRunJob($run),
            new DetectContradictionsJob($run),
            new ProposeFollowUpsJob($run),
            new SynthesizeThreadBriefJob($thread),
            new MapCoverageJob($thread),
            // Runs last so the digest counts everything the chain produced.
            $run->is_watch ? new ReportWatchChangesJob($run) : null,
        ]))
            ->catch(fn (Throwable $e) => $thread->recordKnowledgeError($e->getMessage()))
            ->dispatch();
    }

    public function failed(?Throwable $exception): void
    {
        $this->fail($exception?->getMessage() ?? 'The research job failed.', app(DesktopNotifier::class));
    }

    /**
     * Retry only what a second attempt could plausibly fix, and only while
     * attempts remain — the last one has to settle the run's credits.
     */
    protected function shouldRetry(Throwable $failure): bool
    {
        if ($this->attempts() >= $this->tries) {
            return false;
        }

        return ! $failure instanceof TabstackException || $failure->isRetryable();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function recordEvent(ResearchRun $run, string $event, array $data, int $sequence): void
    {
        RunEvent::query()->create([
            'research_run_id' => $run->getKey(),
            'sequence' => $sequence,
            'event' => $event,
            'message' => isset($data['message']) ? str((string) $data['message'])->limit(240)->toString() : null,
            'data' => $event === 'complete' ? ['message' => $data['message'] ?? null] : $data,
            'created_at' => now(),
        ]);

        if (! in_array($event, ['complete', 'error'], true)) {
            $run->forceFill(['phase' => $event])->save();
        }
    }

    protected function fail(string $message, DesktopNotifier $notifier): void
    {
        $run = $this->run->fresh();

        if ($run === null || $run->status->isFinished()) {
            return;
        }

        $run->forceFill([
            'status' => RunStatus::Failed,
            'error' => str($message)->limit(1000)->toString(),
            'completed_at' => now(),
        ])->save();

        $this->settleCredits($run, spend: false);

        $thread = $run->thread->fresh();

        if ($thread !== null && ! $thread->hasActiveRun()) {
            $thread->forceFill(['status' => ThreadStatus::Failed])->save();
        }

        $notifier->send('Research failed', str($message)->limit(120)->toString());
    }

    /**
     * Release the reservation made at dispatch, moving it to spent when the
     * run actually produced a report.
     */
    protected function settleCredits(ResearchRun $run, bool $spend): void
    {
        DB::transaction(function () use ($run, $spend): void {
            $thread = $run->thread()->lockForUpdate()->first();

            if ($thread === null) {
                return;
            }

            $thread->forceFill([
                'credits_reserved' => max(0, $thread->credits_reserved - $run->credits_cost),
                'credits_spent' => $spend ? $thread->credits_spent + $run->credits_cost : $thread->credits_spent,
            ])->save();
        });
    }
}
