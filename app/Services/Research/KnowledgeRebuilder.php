<?php

namespace App\Services\Research;

use App\Enums\KnowledgeState;
use App\Enums\RunStatus;
use App\Jobs\DetectContradictionsJob;
use App\Jobs\DistillRunJob;
use App\Jobs\MapCoverageJob;
use App\Jobs\SynthesizeThreadBriefJob;
use App\Models\ResearchRun;
use App\Models\Thread;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Re-derives a thread's local knowledge from the reports already stored.
 * Needed when the local model was down while runs finished, or after the
 * distillation prompt changes.
 */
class KnowledgeRebuilder
{
    public function rebuild(Thread $thread, bool $fresh = true): void
    {
        if ($fresh) {
            // Anything the reader pinned, edited or dismissed is theirs, not
            // the model's, so a rebuild leaves it alone. Dismissed rows have
            // to stay or distillation would happily add them back.
            $thread->facts()
                ->whereNull('pinned_at')
                ->whereNull('edited_at')
                ->whereNull('dismissed_at')
                ->delete();
            $thread->sources()->update(['distilled' => false]);
        }

        $distillations = $thread->runs()
            ->where('status', RunStatus::Completed)
            ->whereNotNull('report')
            ->orderBy('id')
            ->get()
            ->flatMap(fn (ResearchRun $run): array => [
                new DistillRunJob($run),
                new DetectContradictionsJob($run),
            ])
            ->all();

        $thread->recordKnowledgeError(null);
        $thread->markKnowledgeState(KnowledgeState::Queued);

        Bus::chain([...$distillations, new SynthesizeThreadBriefJob($thread), new MapCoverageJob($thread)])
            ->catch(fn (Throwable $e) => $thread->recordKnowledgeError($e->getMessage()))
            ->dispatch();
    }
}
