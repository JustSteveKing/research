<?php

use App\Enums\QuestionStatus;
use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Jobs\RunResearchJob;
use App\Models\FollowUpQuestion;
use App\Models\ResearchRun;
use App\Models\Thread;
use App\Services\Research\ThreadAutopilot;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function advance(Thread $thread): Thread
{
    app(ThreadAutopilot::class)->advance($thread);

    return $thread->fresh();
}

it('queues the highest scoring pending question as the next run', function () {
    $thread = Thread::factory()->create();
    ResearchRun::factory()->for($thread)->completed()->create(['depth' => 0]);
    FollowUpQuestion::factory()->for($thread)->create(['question' => 'Low value question?', 'score' => 0.2]);
    $best = FollowUpQuestion::factory()->for($thread)->create(['question' => 'High value question?', 'score' => 0.95]);

    $thread = advance($thread);

    $run = $thread->runs()->where('status', RunStatus::Queued)->sole();

    expect($run->query)->toBe('High value question?')
        ->and($run->depth)->toBe(1)
        ->and($thread->status)->toBe(ThreadStatus::Running)
        ->and($thread->credits_reserved)->toBe(250)
        ->and($best->fresh()->status)->toBe(QuestionStatus::Queued)
        ->and($best->fresh()->spawned_run_id)->toBe($run->id);

    Queue::assertPushed(RunResearchJob::class);
});

it('refuses to start a run the budget cannot cover', function () {
    $thread = Thread::factory()->budget(300)->create([
        'mode' => ResearchMode::Balanced,
        'credits_spent' => 0,
    ]);
    FollowUpQuestion::factory()->for($thread)->create();

    $thread = advance($thread);

    expect($thread->runs()->count())->toBe(0)
        ->and($thread->status)->toBe(ThreadStatus::Complete);

    Queue::assertNothingPushed();
});

it('stops once the thread hits its maximum depth', function () {
    $thread = Thread::factory()->create(['max_depth' => 2]);
    ResearchRun::factory()->for($thread)->completed()->create(['depth' => 2]);
    FollowUpQuestion::factory()->for($thread)->create();

    $thread = advance($thread);

    expect($thread->runs()->count())->toBe(1)
        ->and($thread->status)->toBe(ThreadStatus::Complete);
});

it('does nothing while a run is still in flight', function () {
    $thread = Thread::factory()->create();
    ResearchRun::factory()->for($thread)->create(['status' => RunStatus::Streaming]);
    FollowUpQuestion::factory()->for($thread)->create();

    advance($thread);

    Queue::assertNothingPushed();
});

it('leaves questions untouched when autopilot is off', function () {
    $thread = Thread::factory()->autopilotOff()->create();
    FollowUpQuestion::factory()->for($thread)->create();

    $thread = advance($thread);

    expect($thread->status)->toBe(ThreadStatus::Idle)
        ->and($thread->followUpQuestions()->sole()->status)->toBe(QuestionStatus::Pending);

    Queue::assertNothingPushed();
});

it('keeps the thread idle rather than complete while questions await approval', function () {
    $thread = Thread::factory()->autopilotOff()->create();
    FollowUpQuestion::factory()->for($thread)->create();

    expect(advance($thread)->status)->toBe(ThreadStatus::Idle);
});
