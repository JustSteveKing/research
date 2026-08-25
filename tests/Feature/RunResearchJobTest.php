<?php

use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Jobs\DetectContradictionsJob;
use App\Jobs\DistillRunJob;
use App\Jobs\MapCoverageJob;
use App\Jobs\ProposeFollowUpsJob;
use App\Jobs\RunResearchJob;
use App\Jobs\SynthesizeThreadBriefJob;
use App\Models\ResearchRun;
use App\Models\Thread;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    Bus::fake([DistillRunJob::class, ProposeFollowUpsJob::class, SynthesizeThreadBriefJob::class]);
});

it('streams a run, stores the report and its cited sources', function () {
    fakeTabstack();

    $thread = Thread::factory()->create(['credits_reserved' => 250]);
    $run = ResearchRun::factory()->for($thread)->create();

    RunResearchJob::dispatchSync($run);

    $run->refresh();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->report)->toContain('Solid-state batteries')
        ->and($run->citedPages())->toHaveCount(3)
        ->and($run->completed_at)->not->toBeNull();

    expect($thread->fresh()->sources)->toHaveCount(3);

    $source = $thread->sources()->where('domain', 'www.reuters.com')->sole();

    expect($source->title)->toBe('Toyota pilots solid-state battery line')
        ->and($source->claims)->toHaveCount(2)
        ->and($source->reliability)->toBe('high');
});

it('records every sse event in order for the run timeline', function () {
    fakeTabstack();

    $run = ResearchRun::factory()->create();

    RunResearchJob::dispatchSync($run);

    $events = $run->events()->orderBy('sequence')->pluck('event');

    expect($events->first())->toBe('start')
        ->and($events->last())->toBe('complete')
        ->and($events)->toContain('searching:end', 'analyzing:end', 'writing:end');
});

it('moves reserved credits to spent when the run completes', function () {
    fakeTabstack();

    $thread = Thread::factory()->create(['credits_reserved' => 250, 'credits_spent' => 0]);
    $run = ResearchRun::factory()->for($thread)->create(['credits_cost' => 250]);

    RunResearchJob::dispatchSync($run);

    $thread->refresh();

    expect($thread->credits_reserved)->toBe(0)
        ->and($thread->credits_spent)->toBe(250)
        ->and($thread->availableCredits())->toBe(1750);
});

it('releases the reservation and marks the thread failed when the stream errors', function () {
    fakeTabstack('research-error');

    $thread = Thread::factory()->create(['credits_reserved' => 350]);
    $run = ResearchRun::factory()->for($thread)->create([
        'mode' => ResearchMode::Balanced,
        'credits_cost' => 350,
    ]);

    RunResearchJob::dispatchSync($run);

    $run->refresh();
    $thread->refresh();

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('Upstream search provider unavailable')
        ->and($thread->credits_reserved)->toBe(0)
        ->and($thread->credits_spent)->toBe(0)
        ->and($thread->status)->toBe(ThreadStatus::Failed);
});

it('chains distillation, follow-up planning and the brief once a report lands', function () {
    fakeTabstack();

    RunResearchJob::dispatchSync(ResearchRun::factory()->create());

    Bus::assertChained([
        DistillRunJob::class,
        DetectContradictionsJob::class,
        ProposeFollowUpsJob::class,
        SynthesizeThreadBriefJob::class,
        MapCoverageJob::class,
    ]);
});

it('builds no knowledge when the run failed', function () {
    fakeTabstack('research-error');

    RunResearchJob::dispatchSync(ResearchRun::factory()->create());

    Bus::assertNothingDispatched();
});

it('counts a repeat citation instead of duplicating the source', function () {
    fakeTabstack();

    $thread = Thread::factory()->create();

    RunResearchJob::dispatchSync(ResearchRun::factory()->for($thread)->create());
    RunResearchJob::dispatchSync(ResearchRun::factory()->for($thread)->create());

    expect($thread->sources()->count())->toBe(3)
        ->and($thread->sources()->where('domain', 'www.reuters.com')->sole()->cited_count)->toBe(2);
});
