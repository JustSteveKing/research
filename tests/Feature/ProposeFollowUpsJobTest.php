<?php

use App\Enums\QuestionOrigin;
use App\Enums\QuestionStatus;
use App\Jobs\ProposeFollowUpsJob;
use App\Models\FollowUpQuestion;
use App\Models\ResearchRun;
use App\Models\Thread;
use Illuminate\Support\Facades\Http;

function completedRunWithGaps(Thread $thread): ResearchRun
{
    return ResearchRun::factory()->for($thread)->completed()->create([
        'metadata' => [
            'gapEvaluations' => [[
                'gapDescription' => 'Cost trajectory beyond 2028 is unquantified.',
                'shouldContinueResearch' => true,
                'questionAssessments' => [
                    ['question' => 'How does cost compare to lithium-ion?', 'status' => 'partial', 'findings' => 'Roughly 3x today.'],
                ],
                'searchQueries' => ['solid state battery cost per kWh forecast 2030'],
            ]],
        ],
    ]);
}

it('stores scored follow-up questions proposed by the local model', function () {
    fakeOllama(['questions' => [
        ['question' => 'What is the forecast cost per kWh of solid-state cells in 2030?', 'score' => 0.9, 'rationale' => 'Closes the cost gap.'],
        ['question' => 'Who supplies battery-grade lithium sulfide at scale?', 'score' => 0.6, 'rationale' => 'Supply chain risk.'],
    ]]);

    $thread = Thread::factory()->autopilotOff()->create();

    ProposeFollowUpsJob::dispatchSync(completedRunWithGaps($thread));

    expect($thread->followUpQuestions()->count())->toBe(2);

    $top = $thread->followUpQuestions()->orderByDesc('score')->first();

    expect($top->score)->toBe(0.9)
        ->and($top->status)->toBe(QuestionStatus::Pending)
        ->and($top->origin)->toBe(QuestionOrigin::Ollama)
        ->and($top->embedding)->toBeArray();
});

it('drops questions that repeat an existing question or an earlier run', function () {
    $repeat = 'What is the forecast cost per kWh of solid-state cells in 2030?';

    fakeOllama(['questions' => [
        ['question' => $repeat, 'score' => 0.9],
        ['question' => strtoupper($repeat), 'score' => 0.8],
        ['question' => 'Which oxide electrolytes are in pilot production?', 'score' => 0.7],
    ]]);

    $thread = Thread::factory()->autopilotOff()->create();
    FollowUpQuestion::factory()->for($thread)->create(['question' => $repeat]);

    ProposeFollowUpsJob::dispatchSync(completedRunWithGaps($thread));

    expect($thread->followUpQuestions()->count())->toBe(2);
});

it('falls back to the engine gap queries when ollama is unavailable', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    $thread = Thread::factory()->autopilotOff()->create();

    ProposeFollowUpsJob::dispatchSync(completedRunWithGaps($thread));

    $question = $thread->followUpQuestions()->sole();

    expect($question->question)->toBe('solid state battery cost per kWh forecast 2030')
        ->and($question->origin)->toBe(QuestionOrigin::Gap);
});

it('honours the per-run follow-up limit', function () {
    config()->set('research.follow_ups_per_run', 2);

    fakeOllama(['questions' => [
        ['question' => 'Question one about cost?', 'score' => 0.9],
        ['question' => 'Question two about supply?', 'score' => 0.8],
        ['question' => 'Question three about safety?', 'score' => 0.7],
    ]]);

    $thread = Thread::factory()->autopilotOff()->create();

    ProposeFollowUpsJob::dispatchSync(completedRunWithGaps($thread));

    expect($thread->followUpQuestions()->count())->toBe(2);
});
