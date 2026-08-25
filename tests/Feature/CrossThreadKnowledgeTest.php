<?php

use App\Enums\ChatRole;
use App\Jobs\SynthesizeThreadBriefJob;
use App\Models\ChatMessage;
use App\Models\Fact;
use App\Models\Source;
use App\Models\Thread;
use App\Services\Research\RelatedThreads;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

it('answers with a fact another thread researched, and says where it came from', function () {
    fakeOllamaStream(['An answer.']);

    $thread = Thread::factory()->create(['title' => 'Battery supply chains']);
    $elsewhere = Thread::factory()->create(['title' => 'Solid state batteries']);
    $source = Source::factory()->for($elsewhere)->create(['url' => 'https://example.com/toyota']);

    Fact::factory()->for($elsewhere)->for($source)->create([
        'statement' => 'Toyota targets 2027 for volume production of solid state cells.',
        'embedding' => embeddingFor('Toyota targets 2027 for volume production of solid state cells.'),
    ]);

    ChatMessage::factory()->for($thread)->create([
        'content' => 'Toyota targets which year for volume production of solid state cells?',
    ]);

    $body = $this->get(route('threads.chat.stream', $thread))->streamedContent();

    Http::assertSent(function ($request) {
        $system = $request->data()['messages'][0]['content'] ?? '';

        return str_contains($system, 'Toyota targets 2027')
            && str_contains($system, 'from the thread "Solid state batteries"');
    });

    expect($body)->toContain('Solid state batteries');

    expect($thread->chatMessages()->where('role', ChatRole::Assistant)->sole()->citations)
        ->toBe([[
            'url' => 'https://example.com/toyota',
            'title' => $source->title,
            'thread' => 'Solid state batteries',
        ]]);
});

it('leaves unrelated threads out of the answer', function () {
    fakeOllamaStream(['An answer.']);

    $thread = Thread::factory()->create();

    Fact::factory()->for(Thread::factory())->create([
        'statement' => 'Municipal composting diverts kitchen waste from landfill.',
        'embedding' => embeddingFor('Municipal composting diverts kitchen waste from landfill.'),
    ]);

    ChatMessage::factory()->for($thread)->create(['content' => 'What did Toyota say about solid state cells?']);

    $this->get(route('threads.chat.stream', $thread))->streamedContent();

    Http::assertSent(function ($request) {
        $system = $request->data()['messages'][0]['content'] ?? '';

        return ! str_contains($system, 'Municipal composting');
    });
});

it('never borrows a fact the reader dismissed elsewhere', function () {
    fakeOllamaStream(['An answer.']);

    $thread = Thread::factory()->create();
    $statement = 'Toyota targets 2027 for volume production of solid state cells.';

    Fact::factory()->for(Thread::factory())->dismissed()->create([
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);

    ChatMessage::factory()->for($thread)->create(['content' => $statement]);

    $this->get(route('threads.chat.stream', $thread))->streamedContent();

    Http::assertSent(function ($request) {
        $system = $request->data()['messages'][0]['content'] ?? '';

        return ! str_contains($system, 'Known facts:'."\n".'- Toyota targets 2027');
    });
});

it('writes a topic vector with the brief so the thread can be matched', function () {
    fakeOllama(['brief' => 'A living brief about cells.']);

    $thread = Thread::factory()->create(['title' => 'Solid state batteries']);
    Fact::factory()->for($thread)->create();

    SynthesizeThreadBriefJob::dispatchSync($thread);

    expect($thread->fresh()->embedding)->toBeArray()->not->toBeEmpty();
});

it('surfaces threads working the same ground', function () {
    fakeOllama();

    $topic = embeddingFor('Solid state battery production timelines and cell chemistry.');

    $thread = Thread::factory()->create(['title' => 'Cell chemistry', 'embedding' => $topic]);
    $related = Thread::factory()->create(['title' => 'Production timelines', 'embedding' => $topic]);
    Thread::factory()->create([
        'title' => 'Municipal composting',
        'embedding' => embeddingFor('Kitchen waste diversion and municipal compost schemes.'),
    ]);

    Fact::factory()->for($related)->create([
        'statement' => 'Solid state battery production timelines slipped to 2030.',
        'embedding' => embeddingFor('Solid state battery production timelines slipped to 2030.'),
    ]);

    $matches = app(RelatedThreads::class)->for($thread);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]['title'])->toBe('Production timelines')
        ->and($matches[0]['facts_count'])->toBe(1);
});

it('defers related threads and borrowed facts to a follow-up request', function () {
    fakeOllama();

    $topic = embeddingFor('Solid state battery production timelines and cell chemistry.');
    $thread = Thread::factory()->create(['embedding' => $topic]);
    $other = Thread::factory()->create(['title' => 'Production timelines', 'embedding' => $topic]);

    Fact::factory()->for($other)->create([
        'statement' => 'Solid state battery production timelines and cell chemistry both slipped.',
        'embedding' => embeddingFor('Solid state battery production timelines and cell chemistry both slipped.'),
    ]);

    $version = null;

    $this->get(route('threads.show', $thread))
        ->assertInertia(function (AssertableInertia $page) use (&$version) {
            $version = $page->toArray()['version'];

            $page->missing('related')->missing('borrowed')->missing('contradictions');
        });

    $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'threads/show',
        'X-Inertia-Partial-Data' => 'related,borrowed',
    ])->getJson(route('threads.show', $thread))
        ->assertOk()
        ->assertJsonCount(1, 'props.related')
        ->assertJsonPath('props.related.0.title', 'Production timelines')
        ->assertJsonCount(1, 'props.borrowed')
        ->assertJsonPath('props.borrowed.0.thread.title', 'Production timelines');
});

it('offers facts from a related thread that this one never researched', function () {
    $topic = embeddingFor('Solid state battery production timelines and cell chemistry.');

    $thread = Thread::factory()->create(['embedding' => $topic]);
    $other = Thread::factory()->create(['title' => 'Production timelines']);

    Fact::factory()->for($other)->create([
        'statement' => 'Solid state battery production timelines and cell chemistry both slipped.',
        'embedding' => embeddingFor('Solid state battery production timelines and cell chemistry both slipped.'),
    ]);
    Fact::factory()->for($other)->dismissed()->create([
        'statement' => 'Solid state battery production timelines and cell chemistry were dropped.',
        'embedding' => embeddingFor('Solid state battery production timelines and cell chemistry were dropped.'),
    ]);

    $borrowed = app(RelatedThreads::class)->borrowedFacts($thread);

    expect($borrowed)->toHaveCount(1)
        ->and($borrowed->first()->thread->title)->toBe('Production timelines');
});
