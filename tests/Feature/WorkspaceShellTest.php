<?php

use App\Enums\KnowledgeState;
use App\Enums\RunStatus;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Thread;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Cache::flush();
    fakeOllama();
});

it('shares the rail and activity with every page', function () {
    $thread = Thread::factory()->create(['title' => 'Solid-state batteries']);
    Fact::factory()->for($thread)->count(2)->create();

    $this->get(route('settings.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('rail', 1)
            ->where('rail.0.title', 'Solid-state batteries')
            ->where('rail.0.facts_count', 2)
            ->where('activity.ollama_reachable', true)
            ->where('activity.knowledge_pending', 0)
        );
});

it('puts pinned threads first and busy threads on notice', function () {
    $quiet = Thread::factory()->create(['title' => 'Quiet']);
    $pinned = Thread::factory()->create(['title' => 'Pinned']);
    ResearchRun::factory()->for($quiet)->create(['status' => RunStatus::Streaming]);

    $this->post(route('threads.pin', $pinned))->assertRedirect();

    $this->get(route('threads.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rail.0.title', 'Pinned')
            ->where('rail.0.pinned', true)
            ->where('rail.1.is_busy', true)
        );

    $this->post(route('threads.pin', $pinned));

    expect($pinned->fresh()->pinned_at)->toBeNull();
});

it('reports the running phase in the activity line', function () {
    $thread = Thread::factory()->create();
    ResearchRun::factory()->for($thread)->create([
        'status' => RunStatus::Streaming,
        'phase' => 'searching:start',
    ]);

    $this->get(route('threads.show', $thread))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activity.active_run.phase', 'searching:start')
            ->where('activity.active_run.thread_id', $thread->id)
        );
});

it('tells you what landed while you were away, once', function () {
    $thread = Thread::factory()->create(['last_opened_at' => now()->subDay()]);
    ResearchRun::factory()->for($thread)->completed('A report.')->create([
        'completed_at' => now()->subHour(),
        'credits_cost' => 250,
    ]);
    Fact::factory()->for($thread)->count(3)->create();

    $this->get(route('threads.show', $thread))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sinceLastOpened.runs', 1)
            ->where('sinceLastOpened.facts', 3)
            ->where('sinceLastOpened.credits', 250)
        );

    // The visit above counts as seen, so the next one is quiet.
    $this->get(route('threads.show', $thread))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('sinceLastOpened', null));
});

it('does not mark a thread as seen when the page only polls', function () {
    $thread = Thread::factory()->create(['last_opened_at' => now()->subDay()]);
    $opened = $thread->last_opened_at;

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Partial-Component' => 'threads/show',
        'X-Inertia-Partial-Data' => 'thread,runs',
    ])->getJson(route('threads.show', $thread));

    expect($thread->fresh()->last_opened_at->timestamp)->toBe($opened->timestamp);
});

it('exposes the knowledge state so the shell can name it', function () {
    $thread = Thread::factory()->create(['knowledge_state' => KnowledgeState::Distilling]);

    $this->get(route('threads.show', $thread))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('thread.knowledge_state', 'distilling')
            ->where('thread.knowledge_state_label', 'Distilling sources')
            ->where('thread.is_busy', true)
        );
});
