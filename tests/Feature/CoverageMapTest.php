<?php

use App\Enums\KnowledgeState;
use App\Jobs\MapCoverageJob;
use App\Models\Fact;
use App\Models\Thread;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

it('counts the facts that speak to each sub-topic instead of asking the model', function () {
    fakeOllama(['topics' => [
        ['topic' => 'Sulfide electrolyte moisture sensitivity', 'why' => 'Handling drives cost.'],
        ['topic' => 'Municipal composting collection rounds', 'why' => 'Nobody has looked.'],
    ]]);

    $thread = Thread::factory()->create();

    foreach ([
        'Sulfide electrolyte moisture sensitivity forces dry-room assembly.',
        'Sulfide electrolyte moisture sensitivity raises pack cost.',
        'Sulfide electrolyte moisture sensitivity is measured in dew point.',
    ] as $statement) {
        Fact::factory()->for($thread)->create([
            'statement' => $statement,
            'embedding' => embeddingFor($statement),
        ]);
    }

    MapCoverageJob::dispatchSync($thread);

    $coverage = $thread->fresh()->coverage;

    expect($coverage)->toHaveCount(2)
        ->and($coverage[0]['topic'])->toBe('Sulfide electrolyte moisture sensitivity')
        ->and($coverage[0]['facts_count'])->toBe(3)
        ->and($coverage[0]['status'])->toBe('covered')
        ->and($coverage[0]['why'])->toBe('Handling drives cost.')
        // A sub-topic nobody has researched is the useful half of the panel.
        ->and($coverage[1]['facts_count'])->toBe(0)
        ->and($coverage[1]['status'])->toBe('open')
        ->and($thread->fresh()->coverage_mapped_at)->not->toBeNull();
});

it('calls a sub-topic thin when only one fact touches it', function () {
    fakeOllama(['topics' => [['topic' => 'Sulfide electrolyte moisture sensitivity']]]);

    $thread = Thread::factory()->create();
    $statement = 'Sulfide electrolyte moisture sensitivity forces dry-room assembly.';
    Fact::factory()->for($thread)->create([
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);

    MapCoverageJob::dispatchSync($thread);

    expect($thread->fresh()->coverage[0]['status'])->toBe('thin');
});

it('ignores dismissed facts when measuring coverage', function () {
    fakeOllama(['topics' => [['topic' => 'Sulfide electrolyte moisture sensitivity']]]);

    $thread = Thread::factory()->create();
    $statement = 'Sulfide electrolyte moisture sensitivity forces dry-room assembly.';
    Fact::factory()->for($thread)->create([
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);
    Fact::factory()->for($thread)->dismissed()->create([
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);

    MapCoverageJob::dispatchSync($thread);

    expect($thread->fresh()->coverage[0]['facts_count'])->toBe(1);
});

it('maps nothing for a thread with no facts yet', function () {
    fakeOllama(['topics' => [['topic' => 'Anything at all']]]);

    $thread = Thread::factory()->create();

    MapCoverageJob::dispatchSync($thread);

    expect($thread->fresh()->coverage)->toBeNull();
});

it('leaves the last map in place when the model cannot be reached', function () {
    $thread = Thread::factory()->create([
        'coverage' => [['topic' => 'Known ground', 'why' => null, 'facts_count' => 2, 'status' => 'thin']],
    ]);
    Fact::factory()->for($thread)->create(['embedding' => embeddingFor('anything')]);

    Http::fake(['*' => Http::response('down', 500)]);

    MapCoverageJob::dispatchSync($thread);

    expect($thread->fresh()->coverage)->toHaveCount(1)
        ->and($thread->fresh()->knowledge_state)->toBe(KnowledgeState::Idle);
});

it('sends the coverage map to the workspace', function () {
    fakeOllama();

    $thread = Thread::factory()->create([
        'coverage' => [['topic' => 'Cell chemistry', 'why' => null, 'facts_count' => 0, 'status' => 'open']],
    ]);

    $this->get(route('threads.show', $thread))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('thread.coverage.0.topic', 'Cell chemistry')
            ->where('thread.coverage.0.status', 'open')
        );
});
