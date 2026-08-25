<?php

namespace App\Services\Research;

use App\Enums\ThreadStatus;
use App\Models\Thread;
use App\Support\DesktopNotifier;
use Illuminate\Support\Collection;

/**
 * Re-researches watched threads on their cadence.
 *
 * A watch spends real credits, so it is bounded by exactly the same budget as
 * everything else: when a thread can no longer afford a run the watch stops
 * and says so once, rather than quietly failing every night. Nothing is
 * queued while a thread is already working, so a slow run never stacks up
 * behind itself.
 */
class ThreadWatcher
{
    public function __construct(
        protected RunDispatcher $dispatcher,
        protected DesktopNotifier $notifier,
    ) {}

    /**
     * Queue a watch run for every thread that is due one.
     *
     * @return Collection<int, Thread>
     */
    public function sweep(): Collection
    {
        return Thread::query()
            ->whereNotNull('watch_cadence')
            ->where('status', '!=', ThreadStatus::Paused)
            ->get()
            ->filter(fn (Thread $thread): bool => $this->watch($thread))
            ->values();
    }

    /**
     * Returns whether a run was actually queued.
     */
    public function watch(Thread $thread): bool
    {
        if (! $thread->watchIsDue() || $thread->isBusy()) {
            return false;
        }

        if (! $thread->canAfford($thread->mode)) {
            $this->pause($thread);

            return false;
        }

        $run = $this->dispatcher->dispatch(
            thread: $thread,
            query: $thread->seed_question,
            depth: 0,
            watch: true,
        );

        if ($run === null) {
            $this->pause($thread);

            return false;
        }

        $thread->forceFill(['watched_at' => now()])->save();

        return true;
    }

    /**
     * Stop watching a thread that can no longer pay for it, and say so once.
     * Turning the watch off is what makes this a single notification rather
     * than one every sweep.
     */
    protected function pause(Thread $thread): void
    {
        $thread->forceFill([
            'watch_cadence' => null,
            'last_watch_report' => [
                'checked_at' => now()->toIso8601String(),
                'stopped' => "Watching stopped: this thread has spent {$thread->credits_spent} of its {$thread->credit_budget} credits.",
            ],
        ])->save();

        $this->notifier->send('Watch stopped', "\"{$thread->title}\" is out of credits.");
    }
}
