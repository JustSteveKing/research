<?php

use App\Enums\RunStatus;
use App\Enums\ThreadStatus;
use App\Models\Thread;
use App\Services\Research\RunDispatcher;

it('keeps researching in the background until the credit budget runs out', function () {
    fakeTabstack();
    fakeOllama([
        'facts' => [['statement' => 'Toyota targets 2027 for volume production.', 'confidence' => 0.9, 'tags' => ['timeline']]],
        'questions' => [['question' => 'What will solid-state cells cost per kWh in 2030?', 'score' => 0.9]],
        'brief' => 'Living brief: pilot lines are running.',
    ]);

    // Budget covers exactly two fast runs.
    $thread = Thread::factory()->budget(500)->create(['max_depth' => 5]);

    app(RunDispatcher::class)->dispatch($thread, $thread->seed_question);

    $thread->refresh();

    expect($thread->runs()->count())->toBe(2)
        ->and($thread->runs()->where('status', RunStatus::Completed)->count())->toBe(2)
        ->and($thread->runs()->where('depth', 1)->sole()->query)->toBe('What will solid-state cells cost per kWh in 2030?')
        ->and($thread->credits_spent)->toBe(500)
        ->and($thread->credits_reserved)->toBe(0)
        ->and($thread->availableCredits())->toBe(0)
        ->and($thread->status)->toBe(ThreadStatus::Complete)
        ->and($thread->facts()->count())->toBeGreaterThan(0)
        ->and($thread->brief)->toContain('Living brief');
});

it('builds facts and a brief from a fast run that returns bare cited pages', function () {
    fakeTabstack('research-fast');
    fakeOllama([
        'facts' => [
            ['statement' => 'API resources transform Eloquent models into JSON.', 'confidence' => 0.9, 'tags' => ['laravel'], 'source_url' => 'https://laravel.com/docs/eloquent-resources'],
            ['statement' => 'Sanctum is the default for first-party token auth.', 'confidence' => 0.8, 'tags' => ['auth'], 'source_url' => 'https://laravel.com/docs/sanctum'],
        ],
        'questions' => [['question' => 'How should rate limiting be configured for public APIs?', 'score' => 0.9]],
        'brief' => '## What we know\n\n- API resources shape JSON output.',
    ]);

    $thread = Thread::factory()->budget(250)->create();

    app(RunDispatcher::class)->dispatch($thread, $thread->seed_question);

    $thread->refresh();

    expect($thread->sources()->count())->toBe(3)
        ->and($thread->sources()->whereNotNull('summary')->count())->toBe(0)
        ->and($thread->facts()->count())->toBe(2)
        ->and($thread->facts()->whereNotNull('source_id')->count())->toBe(2)
        ->and($thread->brief)->toContain('What we know')
        ->and($thread->followUpQuestions()->count())->toBe(1);
});

it('stops the chain at the configured depth even with budget left', function () {
    fakeTabstack();
    fakeOllama([
        'questions' => [['question' => 'A follow up question about cost?', 'score' => 0.9]],
        'brief' => 'brief one',
    ]);

    $thread = Thread::factory()->budget(5000)->create(['max_depth' => 1]);

    app(RunDispatcher::class)->dispatch($thread, $thread->seed_question);

    expect($thread->fresh()->runs()->count())->toBe(2)
        ->and($thread->fresh()->status)->toBe(ThreadStatus::Complete);
});
