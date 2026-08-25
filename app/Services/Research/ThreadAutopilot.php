<?php

namespace App\Services\Research;

use App\Enums\QuestionStatus;
use App\Enums\ThreadStatus;
use App\Models\FollowUpQuestion;
use App\Models\Thread;
use App\Support\DesktopNotifier;

/**
 * Decides whether a thread should keep researching on its own. Runs are only
 * spawned while the thread has budget left and has not exceeded its depth.
 */
class ThreadAutopilot
{
    public function __construct(
        protected RunDispatcher $dispatcher,
        protected DesktopNotifier $notifier,
    ) {}

    /**
     * Queue the next follow-up run, if the thread is allowed one.
     */
    public function advance(?Thread $thread): void
    {
        if ($thread === null || $thread->hasActiveRun()) {
            return;
        }

        if ($thread->status === ThreadStatus::Paused || ! $thread->auto_follow_up) {
            $this->settle($thread, ThreadStatus::Idle);

            return;
        }

        if (! $thread->canAfford($thread->mode)) {
            $this->settle($thread, ThreadStatus::Complete);
            $this->notifier->send('Research budget reached', "\"{$thread->title}\" used {$thread->credits_spent} of {$thread->credit_budget} credits.");

            return;
        }

        $depth = $this->nextDepth($thread);

        if ($depth > $thread->max_depth) {
            $this->settle($thread, ThreadStatus::Complete);
            $this->notifier->send('Research complete', "\"{$thread->title}\" reached its maximum depth.");

            return;
        }

        $question = $this->nextQuestion($thread);

        if ($question === null) {
            $this->settle($thread, ThreadStatus::Complete);

            return;
        }

        $run = $this->dispatcher->dispatch(
            thread: $thread,
            query: $question->question,
            depth: $depth,
            parent: $question->originRun,
            question: $question,
        );

        if ($run === null) {
            $this->settle($thread, ThreadStatus::Complete);
        }
    }

    /**
     * Highest scoring question that has not been researched yet.
     */
    protected function nextQuestion(Thread $thread): ?FollowUpQuestion
    {
        return $thread->followUpQuestions()
            ->where('status', QuestionStatus::Pending)
            ->orderByDesc('score')
            ->orderBy('id')
            ->first();
    }

    protected function nextDepth(Thread $thread): int
    {
        return (int) $thread->runs()->max('depth') + 1;
    }

    /**
     * Park the thread: idle means "waiting on you", complete means the
     * autopilot has nothing left it is allowed to do.
     */
    protected function settle(Thread $thread, ThreadStatus $status): void
    {
        $thread->forceFill(['status' => $status])->save();
    }
}
