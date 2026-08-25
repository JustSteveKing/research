<?php

use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Enums\WatchCadence;
use App\Jobs\DetectContradictionsJob;
use App\Jobs\DistillRunJob;
use App\Jobs\MapCoverageJob;
use App\Jobs\ProposeFollowUpsJob;
use App\Jobs\ReportWatchChangesJob;
use App\Jobs\RunResearchJob;
use App\Jobs\SynthesizeThreadBriefJob;
use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Source;
use App\Models\Thread;
use App\Services\Research\ThreadWatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;

it('queues a run for a thread that has never been checked', function () {
    Queue::fake();

    $thread = Thread::factory()->create([
        'watch_cadence' => WatchCadence::Weekly,
        'seed_question' => 'When do solid state cells reach volume production?',
    ]);

    expect(app(ThreadWatcher::class)->sweep())->toHaveCount(1);

    $run = $thread->runs()->sole();

    expect($run->query)->toBe('When do solid state cells reach volume production?')
        ->and($run->is_watch)->toBeTrue()
        ->and($run->depth)->toBe(0)
        ->and($thread->fresh()->watched_at)->not->toBeNull();

    Queue::assertPushed(RunResearchJob::class);
});

it('waits for the cadence to elapse', function () {
    Queue::fake();

    $thread = Thread::factory()->create([
        'watch_cadence' => WatchCadence::Weekly,
        'watched_at' => now()->subDays(3),
    ]);

    expect(app(ThreadWatcher::class)->sweep())->toHaveCount(0)
        ->and($thread->runs()->count())->toBe(0);

    $thread->forceFill(['watched_at' => now()->subDays(8)])->save();

    expect(app(ThreadWatcher::class)->sweep())->toHaveCount(1);
});

it('leaves paused and unwatched threads alone', function () {
    Queue::fake();

    Thread::factory()->create(['watch_cadence' => null]);
    Thread::factory()->create([
        'watch_cadence' => WatchCadence::Daily,
        'status' => ThreadStatus::Paused,
    ]);

    expect(app(ThreadWatcher::class)->sweep())->toHaveCount(0);
});

it('does not stack a watch run behind one already in flight', function () {
    Queue::fake();

    $thread = Thread::factory()->create(['watch_cadence' => WatchCadence::Daily]);
    ResearchRun::factory()->for($thread)->create(['status' => RunStatus::Streaming]);

    expect(app(ThreadWatcher::class)->sweep())->toHaveCount(0)
        ->and($thread->runs()->count())->toBe(1);
});

it('stops watching when the budget will not cover another check', function () {
    Queue::fake();

    $thread = Thread::factory()->create([
        'watch_cadence' => WatchCadence::Daily,
        'credit_budget' => 250,
        'credits_spent' => 250,
    ]);

    expect(app(ThreadWatcher::class)->sweep())->toHaveCount(0);

    $thread->refresh();

    // Switched off rather than left to fail nightly, and it says why.
    expect($thread->watch_cadence)->toBeNull()
        ->and($thread->last_watch_report['stopped'])->toContain('Watching stopped')
        ->and($thread->last_watch_report['stopped'])->toContain('250 of its 250 credits');
});

it('reports what the check changed, disagreements first', function () {
    $thread = Thread::factory()->create(['title' => 'Solid state batteries']);
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create(['is_watch' => true]);

    Source::factory()->for($thread)->create(['first_run_id' => $run->id]);

    $fresh = Fact::factory()->for($thread)->count(2)->create(['research_run_id' => $run->id]);
    $older = Fact::factory()->for($thread)->create();

    Contradiction::factory()->create([
        'fact_id' => min($fresh[0]->id, $older->id),
        'other_fact_id' => max($fresh[0]->id, $older->id),
        'detected_run_id' => $run->id,
    ]);

    ReportWatchChangesJob::dispatchSync($run);

    $report = $thread->fresh()->last_watch_report;

    expect($report['facts'])->toBe(2)
        ->and($report['sources'])->toBe(1)
        ->and($report['disputes'])->toBe(1)
        ->and($report['headline'])->toContain('contradicts what you knew')
        ->and($report['run_id'])->toBe($run->id);
});

it('says so plainly when a check found nothing new', function () {
    $thread = Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create(['is_watch' => true]);

    ReportWatchChangesJob::dispatchSync($run);

    expect($thread->fresh()->last_watch_report['headline'])->toContain('Nothing new');
});

it('leaves ordinary runs out of the watch digest', function () {
    $thread = Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create(['is_watch' => false]);

    ReportWatchChangesJob::dispatchSync($run);

    expect($thread->fresh()->last_watch_report)->toBeNull();
});

it('chains the digest onto a watch run only', function () {
    Bus::fake([
        DistillRunJob::class,
        DetectContradictionsJob::class,
        ProposeFollowUpsJob::class,
        SynthesizeThreadBriefJob::class,
        MapCoverageJob::class,
        ReportWatchChangesJob::class,
    ]);
    fakeTabstack();

    $watch = ResearchRun::factory()->create(['is_watch' => true]);
    RunResearchJob::dispatchSync($watch);

    Bus::assertChained([
        DistillRunJob::class,
        DetectContradictionsJob::class,
        ProposeFollowUpsJob::class,
        SynthesizeThreadBriefJob::class,
        MapCoverageJob::class,
        ReportWatchChangesJob::class,
    ]);
});

it('turns the watch on and off from the workspace', function () {
    fakeOllama();

    $thread = Thread::factory()->create();

    $this->patch(route('threads.update', $thread), ['watch_cadence' => 'weekly'])->assertRedirect();

    expect($thread->fresh()->watch_cadence)->toBe(WatchCadence::Weekly);

    $this->patch(route('threads.update', $thread), ['watch_cadence' => null])->assertRedirect();

    expect($thread->fresh()->watch_cadence)->toBeNull();
});

it('sweeps from the scheduled command', function () {
    Queue::fake();

    Thread::factory()->create(['watch_cadence' => WatchCadence::Daily]);

    $this->artisan('research:watch')->assertSuccessful();

    Queue::assertPushed(RunResearchJob::class);
});
