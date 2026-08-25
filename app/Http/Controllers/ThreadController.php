<?php

namespace App\Http\Controllers;

use App\Enums\QuestionStatus;
use App\Enums\ResearchMode;
use App\Http\Requests\StoreThreadRequest;
use App\Http\Requests\UpdateThreadRequest;
use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\RunEvent;
use App\Models\Thread;
use App\Services\Research\KnowledgeRebuilder;
use App\Services\Research\RelatedThreads;
use App\Services\Research\RunDispatcher;
use App\Services\Research\ThreadAutopilot;
use App\Services\Research\WorkspaceState;
use App\Services\Settings\AppSettings;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ThreadController extends Controller
{
    public function index(): Response
    {
        $threads = Thread::query()
            ->withCount(['runs', 'sources', 'facts'])
            ->latest('updated_at')
            ->get()
            ->map(fn (Thread $thread): array => [
                'id' => $thread->id,
                'title' => $thread->title,
                'seed_question' => $thread->seed_question,
                'status' => $thread->status,
                'mode' => $thread->mode,
                'credit_budget' => $thread->credit_budget,
                'credits_spent' => $thread->credits_spent,
                'credits_reserved' => $thread->credits_reserved,
                'auto_follow_up' => $thread->auto_follow_up,
                'runs_count' => $thread->runs_count,
                'sources_count' => $thread->sources_count,
                'facts_count' => $thread->facts_count,
                'updated_at' => $thread->updated_at,
            ]);

        return Inertia::render('threads/index', [
            'threads' => $threads,
            'defaults' => $this->defaults(),
        ]);
    }

    public function store(StoreThreadRequest $request, RunDispatcher $dispatcher): RedirectResponse
    {
        $validated = $request->validated();

        $thread = Thread::query()->create([
            'title' => $validated['title'] ?? str($validated['seed_question'])->limit(80)->toString(),
            'seed_question' => $validated['seed_question'],
            'mode' => $validated['mode'],
            'credit_budget' => $validated['credit_budget'],
            'max_depth' => $validated['max_depth'],
            'auto_follow_up' => $validated['auto_follow_up'] ?? true,
        ]);

        $dispatcher->dispatch($thread, $thread->seed_question);

        return to_route('threads.show', $thread);
    }

    public function show(Request $request, Thread $thread, WorkspaceState $workspace, RelatedThreads $related): Response
    {
        $thread->loadCount(['sources', 'facts']);

        $sinceLastOpened = $workspace->sinceLastOpened($thread);

        // Only a real visit counts as "seen"; polling must not clear the diff.
        if (! $request->header('X-Inertia-Partial-Data')) {
            $thread->forceFill(['last_opened_at' => now()])->save();
        }

        return Inertia::render('threads/show', [
            'thread' => fn (): array => [
                ...$thread->only([
                    'id', 'title', 'seed_question', 'credit_budget', 'credits_spent',
                    'credits_reserved', 'auto_follow_up', 'max_depth', 'brief',
                    'knowledge_error',
                ]),
                'status' => $thread->status,
                'mode' => $thread->mode,
                'brief_generated_at' => $thread->brief_generated_at,
                'coverage' => $thread->coverage ?? [],
                'coverage_mapped_at' => $thread->coverage_mapped_at,
                'watch_cadence' => $thread->watch_cadence,
                'watched_at' => $thread->watched_at,
                'last_watch_report' => $thread->last_watch_report,
                'sources_count' => $thread->sources_count,
                'facts_count' => $thread->facts_count,
                'available_credits' => $thread->availableCredits(),
                'is_running' => $thread->hasActiveRun(),
                // NativePHP runs one worker per queue, so pending jobs here mean
                // the local model is still working through this thread.
                'knowledge_state' => $thread->knowledge_state,
                'knowledge_state_label' => $thread->knowledge_state->label(),
                'is_busy' => $thread->isBusy(),
                'pinned' => $thread->pinned_at !== null,
            ],
            'runs' => fn (): array => $thread->runs()
                ->with(['events' => fn ($query) => $query->orderByDesc('sequence')->limit(40)])
                ->orderBy('id')
                ->get()
                ->map(fn ($run): array => [
                    'id' => $run->id,
                    'parent_run_id' => $run->parent_run_id,
                    'query' => $run->query,
                    'mode' => $run->mode,
                    'depth' => $run->depth,
                    'status' => $run->status,
                    'phase' => $run->phase,
                    'report' => $run->report,
                    'error' => $run->error,
                    'credits_cost' => $run->credits_cost,
                    'started_at' => $run->started_at,
                    'completed_at' => $run->completed_at,
                    'pages_analyzed' => data_get($run->metadata, 'totalPagesAnalyzed'),
                    'events' => $run->events
                        ->sortBy('sequence')
                        ->values()
                        ->map(fn (RunEvent $event): array => [
                            'id' => $event->id,
                            'event' => $event->event,
                            'message' => $event->message,
                            'created_at' => $event->created_at,
                        ])
                        ->all(),
                ])
                ->all(),
            'sinceLastOpened' => $sinceLastOpened,
            'followUps' => fn (): array => $thread->followUpQuestions()
                ->whereIn('status', [QuestionStatus::Pending, QuestionStatus::Queued])
                ->orderByDesc('score')
                ->get(['id', 'question', 'origin', 'status', 'score', 'rationale'])
                ->all(),
            'chat' => fn (): array => $thread->chatMessages()
                ->orderBy('id')
                ->get(['id', 'role', 'content', 'citations', 'created_at'])
                ->all(),
            'sources' => Inertia::defer(fn (): array => $thread->sources()
                ->orderByDesc('cited_count')
                ->orderBy('id')
                ->get(['id', 'url', 'domain', 'title', 'summary', 'relevance', 'reliability', 'published_at', 'claims', 'cited_count'])
                ->all(), 'knowledge'),
            'facts' => Inertia::defer(fn (): array => $this->facts($thread), 'knowledge'),
            // Dismissed facts ride along so the reader can undo, and every
            // open disagreement is sent once rather than per fact.
            'contradictions' => Inertia::defer(fn (): array => $this->contradictions($thread), 'knowledge'),
            'related' => Inertia::defer(fn (): array => $related->for($thread), 'knowledge'),
            'borrowed' => Inertia::defer(fn (): array => $related->borrowedFacts($thread)
                ->map(fn (Fact $fact): array => [
                    'id' => $fact->id,
                    'statement' => $fact->statement,
                    'confidence' => $fact->confidence,
                    'thread' => ['id' => $fact->thread_id, 'title' => $fact->thread->title],
                    'source' => $fact->source === null ? null : [
                        'url' => $fact->source->url,
                        'domain' => $fact->source->domain,
                    ],
                ])
                ->all(), 'knowledge'),
        ]);
    }

    /**
     * Every fact the thread holds, dismissed ones included so the reader can
     * put one back. Staleness is computed here because it depends on the
     * source's publication date, not on when the row was written.
     *
     * @return list<array<string, mixed>>
     */
    protected function facts(Thread $thread): array
    {
        return array_values($thread->facts()
            ->with('source:id,url,title,domain')
            ->orderByDesc('pinned_at')
            ->orderByDesc('confidence')
            ->get()
            ->map(fn (Fact $fact): array => [
                'id' => $fact->id,
                'source_id' => $fact->source_id,
                'statement' => $fact->statement,
                'tags' => $fact->tags,
                'confidence' => $fact->confidence,
                'source' => $fact->source,
                'pinned' => $fact->isPinned(),
                'edited' => $fact->edited_at !== null,
                'dismissed' => $fact->dismissed_at !== null,
                'stale' => $fact->isStale(),
                'age_days' => $fact->ageInDays(),
                'asserted_at' => $fact->assertedAt(),
            ])
            ->all());
    }

    /**
     * Open disagreements touching this thread's facts. The other side can
     * belong to another thread, so it carries its own thread label.
     *
     * @return list<array<string, mixed>>
     */
    protected function contradictions(Thread $thread): array
    {
        $factIds = array_values(array_map(intval(...), $thread->facts()->pluck('id')->all()));

        if ($factIds === []) {
            return [];
        }

        return array_values(Contradiction::forFacts($factIds)
            ->with(['fact.thread:id,title', 'otherFact.thread:id,title'])
            ->orderByDesc('similarity')
            ->get()
            ->map(fn (Contradiction $clash): array => [
                'id' => $clash->id,
                'note' => $clash->note,
                'similarity' => $clash->similarity,
                'fact_ids' => [$clash->fact_id, $clash->other_fact_id],
                'sides' => collect([$clash->fact, $clash->otherFact])
                    ->map(fn (Fact $fact): array => [
                        'id' => $fact->id,
                        'statement' => $fact->statement,
                        'thread' => $fact->thread_id === $thread->id
                            ? null
                            : ['id' => $fact->thread_id, 'title' => $fact->thread->title],
                    ])
                    ->all(),
            ])
            ->all());
    }

    public function update(UpdateThreadRequest $request, Thread $thread, ThreadAutopilot $autopilot): RedirectResponse
    {
        $thread->update($request->validated());

        if ($thread->auto_follow_up) {
            $autopilot->advance($thread->fresh());
        }

        return back();
    }

    /**
     * Pin or unpin a thread to the top of the rail.
     */
    public function pin(Thread $thread): RedirectResponse
    {
        $pinned = $thread->pinned_at === null;

        $thread->forceFill(['pinned_at' => $pinned ? now() : null])->save();

        return Toast::attach(back(), $pinned ? 'Pinned to the top' : 'Unpinned');
    }

    /**
     * Re-distil every stored report and rewrite the brief.
     */
    public function rebuild(Thread $thread, KnowledgeRebuilder $rebuilder): RedirectResponse
    {
        $rebuilder->rebuild($thread);

        return Toast::attach(back(), 'Rebuilding knowledge from stored reports', 'knowledge');
    }

    public function destroy(Thread $thread): RedirectResponse
    {
        $title = $thread->title;

        $thread->delete();

        return Toast::attach(to_route('threads.index'), "Deleted “{$title}”");
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            ...app(AppSettings::class)->researchDefaults(),
            'costs' => [
                ResearchMode::Fast->value => ResearchMode::Fast->creditCost(),
                ResearchMode::Balanced->value => ResearchMode::Balanced->creditCost(),
            ],
        ];
    }
}
