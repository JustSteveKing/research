<?php

use App\Models\FollowUpQuestion;
use App\Models\ResearchRun;
use App\Models\Thread;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Cache::flush();
    fakeOllama();
    Queue::fake();
});

it('confirms a queued run with its price', function () {
    $thread = Thread::factory()->create();

    $this->post(route('threads.runs.store', $thread), ['query' => 'How do sodium-ion cells compare?'])
        ->assertSessionHas('toast.message', 'Queued · 250 cr')
        ->assertSessionHas('toast.tone', 'signal');
});

it('confirms pinning, rebuilding and deleting', function () {
    $thread = Thread::factory()->create(['title' => 'Solid-state']);

    $this->post(route('threads.pin', $thread))->assertSessionHas('toast.message', 'Pinned to the top');
    $this->post(route('threads.pin', $thread))->assertSessionHas('toast.message', 'Unpinned');
    $this->post(route('threads.rebuild', $thread))
        ->assertSessionHas('toast.message', 'Rebuilding knowledge from stored reports');
    $this->delete(route('threads.destroy', $thread))
        ->assertSessionHas('toast.message', 'Deleted “Solid-state”');
});

it('confirms stopping a run and dismissing a question', function () {
    $thread = Thread::factory()->create(['credits_reserved' => 250]);
    $run = ResearchRun::factory()->for($thread)->create(['status' => 'streaming']);
    $question = FollowUpQuestion::factory()->for($thread)->create();

    $this->delete(route('threads.runs.destroy', [$thread, $run]))
        ->assertSessionHas('toast.message', 'Run stopped · 250 cr released');

    $this->patch(route('threads.follow-ups.update', [$thread, $question]), ['action' => 'reject'])
        ->assertSessionHas('toast.message', 'Question dismissed');
});

it('hands the toast to the shell exactly once', function () {
    $thread = Thread::factory()->create();

    $this->post(route('threads.pin', $thread));

    $this->get(route('threads.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('toast.message', 'Pinned to the top'));

    // Flash data is gone on the next request, so it cannot re-announce itself.
    $this->get(route('threads.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('toast', null));
});
