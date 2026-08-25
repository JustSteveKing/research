<?php

namespace App\Jobs;

use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Source;
use App\Support\DesktopNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Says what a watch run actually changed.
 *
 * A watch is only worth running if it can answer "what moved since last
 * time", so this counts what the run added rather than announcing that it
 * finished. Nothing new is a real answer too, and is reported as such — a
 * silent watch is indistinguishable from a broken one.
 */
class ReportWatchChangesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    /** Thread deleted mid-flight: drop the job rather than failing it. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public ResearchRun $run)
    {
        $this->onQueue(config('research.knowledge_queue'));
    }

    public function handle(DesktopNotifier $notifier): void
    {
        $run = $this->run->fresh();

        if ($run === null || ! $run->is_watch) {
            return;
        }

        $facts = Fact::query()->active()->where('research_run_id', $run->getKey())->get();

        $disputes = $facts->isEmpty() ? 0 : Contradiction::forFacts($facts)
            ->where('detected_run_id', $run->getKey())
            ->count();

        $sources = Source::query()
            ->where('thread_id', $run->thread_id)
            ->where('first_run_id', $run->getKey())
            ->count();

        $stale = $run->thread->facts()
            ->active()
            ->where('research_run_id', '!=', $run->getKey())
            ->get()
            ->filter(fn (Fact $fact): bool => $fact->isStale())
            ->count();

        $report = [
            'checked_at' => now()->toIso8601String(),
            'run_id' => $run->getKey(),
            'facts' => $facts->count(),
            'sources' => $sources,
            'disputes' => $disputes,
            'stale' => $stale,
            'headline' => $this->headline($facts->count(), $disputes, $sources),
        ];

        $run->thread->forceFill(['last_watch_report' => $report])->save();

        $notifier->send(
            "Watch · {$run->thread->title}",
            $report['headline'],
        );
    }

    /**
     * Lead with the disagreement when there is one: a watch that contradicts
     * what you already believed is the whole reason to run it.
     */
    protected function headline(int $facts, int $disputes, int $sources): string
    {
        if ($disputes > 0) {
            return $disputes === 1
                ? "1 new finding contradicts what you knew, plus {$facts} facts from {$sources} new sources."
                : "{$disputes} new findings contradict what you knew, plus {$facts} facts from {$sources} new sources.";
        }

        if ($facts === 0) {
            return 'Nothing new — the picture is unchanged since the last check.';
        }

        return $facts === 1
            ? '1 new fact, nothing that contradicts what you knew.'
            : "{$facts} new facts, nothing that contradicts what you knew.";
    }
}
