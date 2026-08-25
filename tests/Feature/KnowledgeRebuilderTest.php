<?php

use App\Jobs\DetectContradictionsJob;
use App\Jobs\DistillRunJob;
use App\Jobs\MapCoverageJob;
use App\Jobs\SynthesizeThreadBriefJob;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Thread;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

it('re-distils stored reports and rewrites the brief', function () {
    fakeOllama([
        'facts' => [['statement' => 'Toyota targets 2027.', 'confidence' => 0.9, 'tags' => ['timeline']]],
        'brief' => '## What we know',
    ]);

    $thread = Thread::factory()->create();
    ResearchRun::factory()->for($thread)->completed('Toyota targets 2027 for volume production.')->create();
    Fact::factory()->for($thread)->create(['statement' => 'A stale fact.']);

    $this->post(route('threads.rebuild', $thread))->assertRedirect();

    $thread->refresh();

    expect($thread->facts()->pluck('statement')->all())->toBe(['Toyota targets 2027.'])
        ->and($thread->brief)->toContain('What we know');
});

it('chains one distillation per completed run, then the brief', function () {
    Bus::fake();

    $thread = Thread::factory()->create();
    ResearchRun::factory()->for($thread)->completed('One.')->create();
    ResearchRun::factory()->for($thread)->completed('Two.')->create();
    ResearchRun::factory()->for($thread)->create(['report' => null]);

    $this->post(route('threads.rebuild', $thread));

    Bus::assertChained([
        DistillRunJob::class,
        DetectContradictionsJob::class,
        DistillRunJob::class,
        DetectContradictionsJob::class,
        SynthesizeThreadBriefJob::class,
        MapCoverageJob::class,
    ]);
});

it('records why the chain stopped so the ui can explain it', function () {
    Http::fake(['*' => Http::response('model unavailable', 500)]);

    $thread = Thread::factory()->create(['knowledge_error' => null]);
    ResearchRun::factory()->for($thread)->completed('A report.')->create();

    $this->post(route('threads.rebuild', $thread));

    expect($thread->fresh()->knowledge_error)->toContain('Ollama chat failed');
});

it('clears a previous error when a rebuild succeeds', function () {
    fakeOllama([
        'facts' => [['statement' => 'A fresh fact.', 'confidence' => 0.9, 'tags' => []]],
        'brief' => 'brief',
    ]);

    $thread = Thread::factory()->create(['knowledge_error' => 'Ollama was down']);
    ResearchRun::factory()->for($thread)->completed('A report.')->create();

    $this->post(route('threads.rebuild', $thread));

    expect($thread->fresh()->knowledge_error)->toBeNull();
});

it('reports how much local model work is still queued', function () {
    fakeOllama();
    Cache::flush();

    $thread = Thread::factory()->create();
    ResearchRun::factory()->for($thread)->completed('A report.')->create();

    $this->get(route('threads.show', $thread))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('activity.knowledge_pending', 0));

    // NativePHP's workers consume the database driver, so queue for real
    // rather than running the chain inline.
    config()->set('queue.default', 'database');

    $this->post(route('threads.rebuild', $thread));

    $this->get(route('threads.show', $thread))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('activity.knowledge_pending', 1));
});
