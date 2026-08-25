<?php

namespace App\Services\Research;

use App\Enums\QuestionStatus;
use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Jobs\RunResearchJob;
use App\Models\FollowUpQuestion;
use App\Models\ResearchRun;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;

/**
 * Creates research runs and reserves their credits up front, so several
 * queued runs can never overshoot a thread's budget.
 */
class RunDispatcher
{
    /**
     * Reserve credits and queue a run. Returns null when the thread cannot
     * afford another run in the requested mode.
     */
    public function dispatch(
        Thread $thread,
        string $query,
        ?ResearchMode $mode = null,
        int $depth = 0,
        ?ResearchRun $parent = null,
        ?FollowUpQuestion $question = null,
        bool $watch = false,
    ): ?ResearchRun {
        $mode ??= $thread->mode;

        $run = DB::transaction(function () use ($thread, $query, $mode, $depth, $parent, $question, $watch): ?ResearchRun {
            /** @var Thread $locked */
            $locked = Thread::query()->lockForUpdate()->findOrFail($thread->getKey());

            if (! $locked->canAfford($mode)) {
                return null;
            }

            $run = $locked->runs()->create([
                'parent_run_id' => $parent?->getKey(),
                'query' => $query,
                'mode' => $mode,
                'depth' => $depth,
                'is_watch' => $watch,
                'status' => RunStatus::Queued,
                'credits_cost' => $mode->creditCost(),
            ]);

            $locked->forceFill([
                'credits_reserved' => $locked->credits_reserved + $mode->creditCost(),
                'status' => ThreadStatus::Running,
            ])->save();

            $question?->forceFill([
                'status' => QuestionStatus::Queued,
                'spawned_run_id' => $run->getKey(),
            ])->save();

            return $run;
        });

        if ($run !== null) {
            RunResearchJob::dispatch($run);
            $thread->refresh();
        }

        return $run;
    }
}
