<?php

namespace App\Http\Controllers;

use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Http\Requests\StoreResearchRunRequest;
use App\Models\ResearchRun;
use App\Models\Thread;
use App\Services\Research\RunDispatcher;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;

class ResearchRunController extends Controller
{
    public function store(StoreResearchRunRequest $request, Thread $thread, RunDispatcher $dispatcher): RedirectResponse
    {
        $mode = $request->filled('mode')
            ? ResearchMode::from($request->string('mode')->toString())
            : $thread->mode;

        $run = $dispatcher->dispatch(
            thread: $thread,
            query: $request->string('query')->toString(),
            mode: $mode,
            depth: (int) $thread->runs()->max('depth'),
        );

        if ($run === null) {
            return back()->withErrors([
                'query' => "This run needs {$mode->creditCost()} credits but only {$thread->availableCredits()} remain in the budget.",
            ]);
        }

        return Toast::attach(back(), "Queued · {$mode->creditCost()} cr", 'signal');
    }

    /**
     * Cancel a run that has not finished. The job checks the status before
     * writing, so a cancelled run stops persisting events.
     */
    public function destroy(Thread $thread, ResearchRun $run): RedirectResponse
    {
        abort_unless($run->thread_id === $thread->id, 404);

        if ($run->status->isFinished()) {
            return back();
        }

        $run->forceFill([
            'status' => RunStatus::Cancelled,
            'completed_at' => now(),
        ])->save();

        $thread->forceFill([
            'credits_reserved' => max(0, $thread->credits_reserved - $run->credits_cost),
            'status' => ThreadStatus::Idle,
        ])->save();

        return Toast::attach(back(), "Run stopped · {$run->credits_cost} cr released");
    }
}
