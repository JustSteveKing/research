<?php

namespace App\Services\Research;

use App\Enums\RunStatus;
use App\Models\ResearchRun;
use App\Models\Thread;
use App\Services\Ollama\OllamaClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Everything the persistent shell needs: the thread rail and the activity
 * line. Shared on every Inertia response so switching pages never blanks it.
 */
class WorkspaceState
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function rail(): array
    {
        return Thread::query()
            ->forRail()
            ->withCount(['facts', 'sources'])
            ->get()
            ->map(fn (Thread $thread): array => [
                'id' => $thread->id,
                'title' => $thread->title,
                'status' => $thread->status,
                'knowledge_state' => $thread->knowledge_state,
                'facts_count' => $thread->facts_count,
                'sources_count' => $thread->sources_count,
                'is_busy' => $thread->isBusy(),
                'pinned' => $thread->pinned_at !== null,
                'unread' => $this->unreadCount($thread),
                'updated_at' => $thread->updated_at,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function activity(): array
    {
        $activeRun = ResearchRun::query()
            ->whereIn('status', [RunStatus::Queued, RunStatus::Streaming])
            ->latest('id')
            ->first();

        return [
            'active_run' => $activeRun === null ? null : [
                'id' => $activeRun->id,
                'thread_id' => $activeRun->thread_id,
                'phase' => $activeRun->phase,
                'status' => $activeRun->status,
                'started_at' => $activeRun->started_at,
            ],
            'knowledge_pending' => DB::table('jobs')
                ->where('queue', config('research.knowledge_queue'))
                ->count(),
            'ollama_reachable' => $this->ollamaReachable(),
            'credits_spent' => (int) Thread::query()->sum('credits_spent'),
            'credits_budgeted' => (int) Thread::query()->sum('credit_budget'),
            'checked_at' => now(),
        ];
    }

    /**
     * New work landed since the thread was last opened.
     *
     * @return array{runs: int, facts: int, questions: int, credits: int, since: string}|null
     */
    public function sinceLastOpened(Thread $thread): ?array
    {
        if ($thread->last_opened_at === null) {
            return null;
        }

        $since = $thread->last_opened_at;

        $summary = [
            'runs' => $thread->runs()->where('created_at', '>', $since)->count(),
            'facts' => $thread->facts()->where('created_at', '>', $since)->count(),
            'questions' => $thread->followUpQuestions()->where('created_at', '>', $since)->count(),
            'credits' => (int) $thread->runs()
                ->where('completed_at', '>', $since)
                ->where('status', RunStatus::Completed)
                ->sum('credits_cost'),
        ];

        return array_sum($summary) === 0 ? null : [...$summary, 'since' => $since->toIso8601String()];
    }

    /**
     * A cheap unread marker for the rail.
     */
    protected function unreadCount(Thread $thread): int
    {
        if ($thread->last_opened_at === null) {
            return 0;
        }

        return $thread->facts()->where('created_at', '>', $thread->last_opened_at)->count();
    }

    /**
     * Health checks hit the network, so hold the answer briefly.
     */
    protected function ollamaReachable(): bool
    {
        return Cache::remember(
            'workspace.ollama-reachable',
            now()->addSeconds(20),
            fn (): bool => app(OllamaClient::class)->isReachable(),
        );
    }
}
