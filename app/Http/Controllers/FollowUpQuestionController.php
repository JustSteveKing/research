<?php

namespace App\Http\Controllers;

use App\Enums\QuestionStatus;
use App\Models\FollowUpQuestion;
use App\Models\Thread;
use App\Services\Research\RunDispatcher;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowUpQuestionController extends Controller
{
    /**
     * Run a proposed question now, or drop it from the queue.
     */
    public function update(Request $request, Thread $thread, FollowUpQuestion $question, RunDispatcher $dispatcher): RedirectResponse
    {
        abort_unless($question->thread_id === $thread->id, 404);

        $action = $request->validate([
            'action' => ['required', 'in:run,reject'],
        ])['action'];

        if ($action === 'reject') {
            $question->forceFill(['status' => QuestionStatus::Rejected])->save();

            return Toast::attach(back(), 'Question dismissed');
        }

        $run = $dispatcher->dispatch(
            thread: $thread,
            query: $question->question,
            depth: (int) $thread->runs()->max('depth') + 1,
            parent: $question->originRun,
            question: $question,
        );

        if ($run === null) {
            return back()->withErrors([
                'follow_up' => "Not enough credits left in this thread's budget to run that question.",
            ]);
        }

        return Toast::attach(back(), "Queued · {$run->credits_cost} cr", 'signal');
    }
}
