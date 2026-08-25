<?php

use App\Enums\RunStatus;
use App\Jobs\DistillRunJob;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Source;
use App\Models\Thread;
use App\Services\Research\SourceRecorder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

it('dates a fact from the page it came from, not from when it was distilled', function () {
    fakeOllama(['facts' => [[
        'statement' => 'Toyota targets 2027 for volume production.',
        'confidence' => 0.9,
        'source_url' => 'https://example.com/toyota',
    ]]]);

    $thread = Thread::factory()->create();
    Source::factory()->for($thread)->create([
        'url' => 'https://example.com/toyota',
        'published_at' => now()->subYears(3),
    ]);

    DistillRunJob::dispatchSync(ResearchRun::factory()->for($thread)->completed('A report.')->create());

    $fact = $thread->facts()->sole();

    expect($fact->assertedAt()->toDateString())->toBe(now()->subYears(3)->toDateString())
        ->and($fact->isStale())->toBeTrue();
});

it('falls back to the distillation date when the page carries none', function () {
    fakeOllama(['facts' => [['statement' => 'A fresh finding about cells.', 'confidence' => 0.9]]]);

    $thread = Thread::factory()->create();

    DistillRunJob::dispatchSync(ResearchRun::factory()->for($thread)->completed('A report.')->create());

    expect($thread->facts()->sole()->isStale())->toBeFalse();
});

it('never calls a pinned fact stale, because the reader stands behind it', function () {
    $fact = Fact::factory()->stale()->pinned()->create();

    expect($fact->isStale())->toBeFalse();
});

it('reads the publication date off the cited page', function () {
    $thread = Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create([
        'metadata' => ['citedPages' => [[
            'url' => 'https://example.com/dated',
            'title' => 'A dated page',
            'publishedDate' => '2019-04-02',
        ]]],
    ]);

    app(SourceRecorder::class)->record($run);

    expect($thread->sources()->sole()->published_at->toDateString())->toBe('2019-04-02');
});

it('shows the workspace which facts have aged out', function () {
    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->stale()->create();

    $version = null;

    $this->get(route('threads.show', $thread))
        ->assertInertia(function (AssertableInertia $page) use (&$version) {
            $version = $page->toArray()['version'];
        });

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'threads/show',
        'X-Inertia-Partial-Data' => 'facts',
    ])->getJson(route('threads.show', $thread))
        ->assertOk()
        ->assertJsonPath('props.facts.0.stale', true)
        ->assertJsonPath('props.facts.0.pinned', false);
});

it('queues a run to re-check a fact that has aged out', function () {
    Queue::fake();

    $thread = Thread::factory()->create();
    $fact = Fact::factory()->for($thread)->stale()->create([
        'statement' => 'Toyota targets 2027 for volume production.',
    ]);

    $this->post(route('threads.facts.recheck', [$thread, $fact]))->assertRedirect();

    $run = $thread->runs()->sole();

    expect($run->query)->toContain('Toyota targets 2027 for volume production.')
        ->and($run->query)->toContain('still accurate')
        ->and($run->status)->toBe(RunStatus::Queued);
});

it('refuses a re-check the budget cannot cover', function () {
    $thread = Thread::factory()->create(['credit_budget' => 250, 'credits_spent' => 250]);
    $fact = Fact::factory()->for($thread)->stale()->create();

    $this->from(route('threads.show', $thread))
        ->post(route('threads.facts.recheck', [$thread, $fact]))
        ->assertSessionHasErrors('fact');

    expect($thread->runs()->count())->toBe(0);
});

it('will not re-check a fact from another thread', function () {
    $fact = Fact::factory()->for(Thread::factory())->create();

    $this->post(route('threads.facts.recheck', [Thread::factory()->create(), $fact]))
        ->assertNotFound();
});
