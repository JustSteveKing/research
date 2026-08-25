<?php

use App\Enums\QuestionStatus;
use App\Enums\ResearchMode;
use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Jobs\DistillRunJob;
use App\Jobs\ProposeFollowUpsJob;
use App\Jobs\RunResearchJob;
use App\Jobs\SynthesizeThreadBriefJob;
use App\Models\ChatMessage;
use App\Models\Fact;
use App\Models\FollowUpQuestion;
use App\Models\ResearchRun;
use App\Models\RunEvent;
use App\Models\Source;
use App\Models\Thread;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Queue::fake();
});

it('lists threads with their credit and knowledge counts', function () {
    $thread = Thread::factory()->create(['credits_spent' => 500]);
    Source::factory()->for($thread)->count(2)->create();
    Fact::factory()->for($thread)->count(3)->create();

    $this->get(route('threads.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('threads/index')
            ->has('threads', 1)
            ->where('threads.0.credits_spent', 500)
            ->where('threads.0.sources_count', 2)
            ->where('threads.0.facts_count', 3)
            ->where('defaults.costs.balanced', 350)
        );
});

it('opens a thread and immediately queues the first run', function () {
    $response = $this->post(route('threads.store'), [
        'seed_question' => 'What is the state of solid-state battery production?',
        'mode' => 'balanced',
        'credit_budget' => 1400,
        'max_depth' => 2,
        'auto_follow_up' => true,
    ]);

    $thread = Thread::query()->sole();

    $response->assertRedirect(route('threads.show', $thread));

    expect($thread->mode)->toBe(ResearchMode::Balanced)
        ->and($thread->status)->toBe(ThreadStatus::Running)
        ->and($thread->credits_reserved)->toBe(350)
        ->and($thread->runs()->sole()->query)->toBe('What is the state of solid-state battery production?');

    Queue::assertPushed(RunResearchJob::class);
});

it('rejects a thread whose budget cannot cover a single run', function () {
    $this->post(route('threads.store'), [
        'seed_question' => 'A perfectly reasonable question to research?',
        'mode' => 'fast',
        'credit_budget' => 10,
        'max_depth' => 1,
    ])->assertSessionHasErrors('credit_budget');

    expect(Thread::query()->count())->toBe(0);
});

it('shows the thread with runs, live events and pending follow-ups', function () {
    $thread = Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('## Report body')->create();
    RunEvent::factory()->for($run, 'run')->create(['sequence' => 0, 'event' => 'start', 'message' => 'Starting research']);
    RunEvent::factory()->for($run, 'run')->create(['sequence' => 1, 'event' => 'writing:end', 'message' => 'Report written']);
    FollowUpQuestion::factory()->for($thread)->create(['question' => 'What about cost?']);
    FollowUpQuestion::factory()->for($thread)->create(['status' => QuestionStatus::Rejected]);

    $this->get(route('threads.show', $thread))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('threads/show')
            ->where('thread.is_running', false)
            ->where('runs.0.report', '## Report body')
            ->has('runs.0.events', 2)
            ->where('runs.0.events.0.event', 'start')
            ->has('followUps', 1)
            ->where('followUps.0.question', 'What about cost?')
            ->has('chat', 0)
        );
});

it('defers sources and facts to a follow-up request', function () {
    $thread = Thread::factory()->create();
    Source::factory()->for($thread)->create();
    Fact::factory()->for($thread)->create();

    $version = null;

    $this->get(route('threads.show', $thread))
        ->assertInertia(function (AssertableInertia $page) use (&$version) {
            $version = $page->toArray()['version'];

            $page->missing('sources')->missing('facts');
        });

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'threads/show',
        'X-Inertia-Partial-Data' => 'sources,facts',
    ])->getJson(route('threads.show', $thread))
        ->assertOk()
        ->assertJsonCount(1, 'props.sources')
        ->assertJsonCount(1, 'props.facts');
});

it('runs a manual query against the thread budget', function () {
    $thread = Thread::factory()->create();

    $this->post(route('threads.runs.store', $thread), ['query' => 'How do sodium-ion cells compare?'])
        ->assertRedirect();

    expect($thread->fresh()->credits_reserved)->toBe(250)
        ->and($thread->runs()->sole()->query)->toBe('How do sodium-ion cells compare?');
});

it('refuses a manual query the budget cannot cover', function () {
    $thread = Thread::factory()->budget(250)->create(['credits_spent' => 250]);

    $this->post(route('threads.runs.store', $thread), ['query' => 'One more question to research?'])
        ->assertSessionHasErrors('query');

    expect($thread->runs()->count())->toBe(0);
});

it('cancels an in-flight run and releases its credits', function () {
    $thread = Thread::factory()->create(['credits_reserved' => 250]);
    $run = ResearchRun::factory()->for($thread)->create(['status' => RunStatus::Streaming]);

    $this->delete(route('threads.runs.destroy', [$thread, $run]))->assertRedirect();

    expect($run->fresh()->status)->toBe(RunStatus::Cancelled)
        ->and($thread->fresh()->credits_reserved)->toBe(0);
});

it('runs or rejects a proposed follow-up question', function () {
    $thread = Thread::factory()->create();
    $run = FollowUpQuestion::factory()->for($thread)->create();
    $rejected = FollowUpQuestion::factory()->for($thread)->create();

    $this->patch(route('threads.follow-ups.update', [$thread, $run]), ['action' => 'run'])->assertRedirect();
    $this->patch(route('threads.follow-ups.update', [$thread, $rejected]), ['action' => 'reject'])->assertRedirect();

    expect($run->fresh()->status)->toBe(QuestionStatus::Queued)
        ->and($run->fresh()->spawned_run_id)->not->toBeNull()
        ->and($rejected->fresh()->status)->toBe(QuestionStatus::Rejected);
});

it('updates thread settings and resumes the autopilot', function () {
    $thread = Thread::factory()->autopilotOff()->create();
    FollowUpQuestion::factory()->for($thread)->create();

    $this->patch(route('threads.update', $thread), ['auto_follow_up' => true])->assertRedirect();

    expect($thread->fresh()->auto_follow_up)->toBeTrue()
        ->and($thread->fresh()->runs()->count())->toBe(1);
});

it('deletes a thread and everything it learned', function () {
    $thread = Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create();
    RunEvent::factory()->for($run, 'run')->create();
    $source = Source::factory()->for($thread)->create();
    Fact::factory()->for($thread)->for($source)->create();
    FollowUpQuestion::factory()->for($thread)->create();
    ChatMessage::factory()->for($thread)->create();

    $this->delete(route('threads.destroy', $thread))->assertRedirect(route('threads.index'));

    expect(Thread::query()->count())->toBe(0)
        ->and(ResearchRun::query()->count())->toBe(0)
        ->and(RunEvent::query()->count())->toBe(0)
        ->and(Source::query()->count())->toBe(0)
        ->and(Fact::query()->count())->toBe(0)
        ->and(FollowUpQuestion::query()->count())->toBe(0)
        ->and(ChatMessage::query()->count())->toBe(0);
});

it('leaves other threads untouched', function () {
    $keep = Thread::factory()->create();
    Fact::factory()->for($keep)->create();
    $drop = Thread::factory()->create();
    Fact::factory()->for($drop)->create();

    $this->delete(route('threads.destroy', $drop));

    expect(Thread::query()->pluck('id')->all())->toBe([$keep->id])
        ->and($keep->facts()->count())->toBe(1);
});

it('deletes a thread that still has a run in flight', function () {
    $thread = Thread::factory()->create(['credits_reserved' => 250]);
    ResearchRun::factory()->for($thread)->create(['status' => RunStatus::Streaming]);

    $this->delete(route('threads.destroy', $thread))->assertRedirect(route('threads.index'));

    expect(Thread::query()->count())->toBe(0)
        ->and(ResearchRun::query()->count())->toBe(0);
});

it('discards queued jobs whose thread was deleted instead of failing them', function () {
    $run = ResearchRun::factory()->create();

    expect((new RunResearchJob($run))->deleteWhenMissingModels)->toBeTrue()
        ->and((new DistillRunJob($run))->deleteWhenMissingModels)->toBeTrue()
        ->and((new ProposeFollowUpsJob($run))->deleteWhenMissingModels)->toBeTrue()
        ->and((new SynthesizeThreadBriefJob($run->thread))->deleteWhenMissingModels)->toBeTrue();
});

it('shows the settings screen with local model status', function () {
    fakeOllama();

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings')
            ->where('ollama.reachable', true)
            ->where('ollama.models.0', 'gemma4:latest')
            ->where('tabstack.replaying_fixtures', true)
        );
});
