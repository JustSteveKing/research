<?php

use App\Jobs\DetectContradictionsJob;
use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Thread;

/**
 * Two statements about the same subject, worded closely enough to be compared
 * but not closely enough to be deduplicated by distillation.
 */
function clashingPair(?Thread $thread = null): array
{
    $thread ??= Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create();

    $fresh = Fact::factory()->for($thread)->create([
        'research_run_id' => $run->getKey(),
        'statement' => 'Toyota targets 2027 for volume production of solid state cells.',
        'embedding' => embeddingFor('Toyota targets 2027 for volume production of solid state cells.'),
    ]);

    $known = Fact::factory()->for($thread)->create([
        'statement' => 'Toyota targets 2030 for volume production of solid state cells.',
        'embedding' => embeddingFor('Toyota targets 2030 for volume production of solid state cells.'),
    ]);

    return [$run, $fresh, $known];
}

it('records a pair the local model says cannot both be true', function () {
    fakeOllama(['verdicts' => [['index' => 0, 'relation' => 'contradiction', 'note' => 'Different target years.']]]);

    [$run, $fresh, $known] = clashingPair();

    DetectContradictionsJob::dispatchSync($run);

    $clash = Contradiction::query()->sole();

    expect([$clash->fact_id, $clash->other_fact_id])->toBe(Contradiction::orderPair($fresh->id, $known->id))
        ->and($clash->note)->toBe('Different target years.')
        ->and($clash->detected_run_id)->toBe($run->id)
        ->and($clash->similarity)->toBeGreaterThan(0.62);
});

it('leaves pairs the model clears alone', function () {
    fakeOllama(['verdicts' => [['index' => 0, 'relation' => 'distinct']]]);

    [$run] = clashingPair();

    DetectContradictionsJob::dispatchSync($run);

    expect(Contradiction::query()->count())->toBe(0);
});

it('does not judge the same pair twice', function () {
    fakeOllama(['verdicts' => [['index' => 0, 'relation' => 'contradiction']]]);

    [$run, $fresh, $known] = clashingPair();

    DetectContradictionsJob::dispatchSync($run);
    DetectContradictionsJob::dispatchSync($run);

    expect(Contradiction::query()->count())->toBe(1);
});

it('deletes the new fact when the model calls the pair a duplicate', function () {
    fakeOllama(['verdicts' => [['index' => 0, 'relation' => 'duplicate']]]);

    [$run, $fresh, $known] = clashingPair();

    DetectContradictionsJob::dispatchSync($run);

    expect(Fact::query()->find($fresh->id))->toBeNull()
        ->and(Fact::query()->find($known->id))->not->toBeNull()
        ->and(Contradiction::query()->count())->toBe(0);
});

it('judges near-identical pairs rather than assuming they are duplicates', function () {
    // The highest-similarity band is where a reword and a flat contradiction
    // are indistinguishable by vector, so it must still reach the model.
    fakeOllama(['verdicts' => [['index' => 0, 'relation' => 'contradiction']]]);

    $thread = Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create();
    $statement = 'Sulfide electrolytes degrade in ambient moisture.';

    Fact::factory()->for($thread)->create([
        'research_run_id' => $run->getKey(),
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);
    Fact::factory()->for($thread)->create([
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);

    DetectContradictionsJob::dispatchSync($run);

    expect(Contradiction::query()->count())->toBe(1);
});

it('leaves a fact alone once it has been dropped as a duplicate', function () {
    fakeOllama(['verdicts' => [
        ['index' => 0, 'relation' => 'duplicate'],
        ['index' => 1, 'relation' => 'contradiction'],
    ]]);

    $thread = Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create();
    $statement = 'Toyota targets 2027 for volume production of solid state cells.';

    $fresh = Fact::factory()->for($thread)->create([
        'research_run_id' => $run->getKey(),
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);
    Fact::factory()->for($thread)->count(2)->sequence(
        ['statement' => 'Toyota targets 2030 for volume production of solid state cells.'],
        ['statement' => 'Toyota targets 2031 for volume production of solid state cells.'],
    )->create(['embedding' => embeddingFor($statement)]);

    DetectContradictionsJob::dispatchSync($run);

    expect(Fact::query()->find($fresh->id))->toBeNull()
        ->and(Contradiction::query()->count())->toBe(0);
});

it('flags a clash with another thread, not just its own', function () {
    fakeOllama(['verdicts' => [['index' => 0, 'relation' => 'contradiction']]]);

    $thread = Thread::factory()->create();
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create();

    Fact::factory()->for($thread)->create([
        'research_run_id' => $run->getKey(),
        'statement' => 'Toyota targets 2027 for volume production of solid state cells.',
        'embedding' => embeddingFor('Toyota targets 2027 for volume production of solid state cells.'),
    ]);

    $elsewhere = Fact::factory()->for(Thread::factory())->create([
        'statement' => 'Toyota targets 2030 for volume production of solid state cells.',
        'embedding' => embeddingFor('Toyota targets 2030 for volume production of solid state cells.'),
    ]);

    DetectContradictionsJob::dispatchSync($run);

    expect(Contradiction::query()->sole())
        ->and(Contradiction::forFacts([$elsewhere->id])->count())->toBe(1);
});

it('skips dismissed facts, which the reader already ruled out', function () {
    fakeOllama(['verdicts' => [['index' => 0, 'relation' => 'contradiction']]]);

    [$run, , $known] = clashingPair();
    $known->forceFill(['dismissed_at' => now()])->save();

    DetectContradictionsJob::dispatchSync($run);

    expect(Contradiction::query()->count())->toBe(0);
});
