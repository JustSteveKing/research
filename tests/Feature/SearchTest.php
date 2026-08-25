<?php

use App\Models\Fact;
use App\Models\Source;
use App\Models\Thread;

it('finds threads, facts and sources for the palette', function () {
    $thread = Thread::factory()->create(['title' => 'Solid-state batteries']);
    Fact::factory()->for($thread)->create(['statement' => 'Toyota targets 2027 for volume production.']);
    Source::factory()->for($thread)->create([
        'title' => 'Toyota pilots a solid-state line',
        'url' => 'https://reuters.com/toyota',
    ]);

    $this->getJson(route('search', ['q' => 'toyota']))
        ->assertOk()
        ->assertJsonCount(0, 'threads')
        ->assertJsonCount(1, 'facts')
        ->assertJsonCount(1, 'sources')
        ->assertJsonPath('facts.0.thread_title', 'Solid-state batteries');

    $this->getJson(route('search', ['q' => 'solid-state']))
        ->assertJsonCount(1, 'threads');
});

it('stays quiet until the term is worth searching', function () {
    Thread::factory()->create(['title' => 'Abc']);

    $this->getJson(route('search', ['q' => 'a']))
        ->assertOk()
        ->assertExactJson(['threads' => [], 'facts' => [], 'sources' => [], 'semantic' => false]);
});

it('matches whole words through the index rather than substrings', function () {
    $thread = Thread::factory()->create(['title' => 'Battery research']);
    Fact::factory()->for($thread)->create(['statement' => 'Sulfide electrolytes degrade in ambient moisture.']);

    $this->getJson(route('search', ['q' => 'electrolytes']))
        ->assertOk()
        ->assertJsonCount(1, 'facts')
        ->assertJsonPath('facts.0.thread_title', 'Battery research')
        ->assertJsonPath('semantic', false);
});

it('narrows as the reader keeps typing', function () {
    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->create(['statement' => 'Sulfide electrolytes degrade in ambient moisture.']);
    Fact::factory()->for($thread)->create(['statement' => 'Oxide ceramics tolerate ambient air.']);

    $this->getJson(route('search', ['q' => 'ambient']))->assertJsonCount(2, 'facts');
    $this->getJson(route('search', ['q' => 'ambient mois']))->assertJsonCount(1, 'facts');
});

it('leaves dismissed facts out of the palette', function () {
    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->dismissed()->create(['statement' => 'Sulfide electrolytes degrade in moisture.']);

    $this->getJson(route('search', ['q' => 'electrolytes']))->assertJsonCount(0, 'facts');
});

it('reads through punctuation that would be syntax to the index', function () {
    Fact::factory()->create(['statement' => 'Cells cost about three times lithium-ion.']);

    $this->getJson(route('search', ['q' => '"lithium-ion", cost']))
        ->assertOk()
        ->assertJsonCount(1, 'facts');
});

it('answers a half-typed query instead of erroring on it', function () {
    Fact::factory()->create(['statement' => 'Cells cost about three times lithium-ion.']);

    foreach (['*', '"', 'cost AND (', 'NEAR/', '   '] as $term) {
        $this->getJson(route('search', ['q' => $term]))->assertOk();
    }
});

it('falls back to meaning when the words do not match', function () {
    fakeOllama();

    $thread = Thread::factory()->create();
    $statement = 'Sulfide electrolytes degrade in ambient moisture.';
    Fact::factory()->for($thread)->create([
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);

    $this->getJson(route('search', ['q' => 'sulfide electrolytes ambient moisture degrade humidity']))
        ->assertOk()
        ->assertJsonPath('semantic', true)
        ->assertJsonCount(1, 'facts');
});
