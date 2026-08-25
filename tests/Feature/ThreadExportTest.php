<?php

use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Source;
use App\Models\Thread;

function exportableThread(): Thread
{
    $thread = Thread::factory()->create([
        'title' => 'Solid state batteries',
        'seed_question' => 'When do solid state cells reach volume production?',
        'brief' => "## What we know\n\nCells are close.",
    ]);

    $source = Source::factory()->for($thread)->create([
        'url' => 'https://example.com/cells',
        'domain' => 'example.com',
        'title' => 'Cell roadmaps',
        'published_at' => now()->subYears(2),
    ]);

    Fact::factory()->for($thread)->for($source)->create(['statement' => 'Toyota targets 2027 for volume production.']);
    Fact::factory()->for($thread)->dismissed()->create(['statement' => 'A claim the reader threw out.']);

    ResearchRun::factory()->for($thread)->completed('A report.')->create(['query' => 'Cell roadmaps 2027']);

    return $thread;
}

it('exports a readable markdown report', function () {
    $thread = exportableThread();

    $response = $this->get(route('threads.export', [$thread, 'markdown']));

    $response->assertOk()
        ->assertHeader('content-type', 'text/markdown; charset=utf-8')
        ->assertDownload('solid-state-batteries.md');

    $body = $response->streamedContent();

    expect($body)->toContain('# Solid state batteries')
        ->and($body)->toContain('## Brief')
        ->and($body)->toContain('Toyota targets 2027 for volume production.')
        ->and($body)->toContain('[Cell roadmaps](https://example.com/cells)')
        ->and($body)->toContain('Cell roadmaps 2027')
        // Dismissed facts are the reader's own verdict; they do not travel.
        ->and($body)->not->toContain('A claim the reader threw out.');
});

it('marks pinned and stale facts in the report', function () {
    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->pinned()->create(['statement' => 'A fact the reader confirmed.']);
    Fact::factory()->for($thread)->stale()->create(['statement' => 'A fact from long ago.']);

    $body = $this->get(route('threads.export', [$thread, 'markdown']))->streamedContent();

    expect($body)->toContain('A fact the reader confirmed. _(pinned)_')
        ->and($body)->toContain('stale,');
});

it('carries disputed pairs into the export rather than hiding them', function () {
    $thread = exportableThread();
    $first = $thread->facts()->active()->sole();
    $second = Fact::factory()->for($thread)->create(['statement' => 'Toyota targets 2030 for volume production.']);

    Contradiction::factory()->create([
        'fact_id' => $first->id,
        'other_fact_id' => $second->id,
        'note' => 'Different target years.',
    ]);

    $body = $this->get(route('threads.export', [$thread, 'markdown']))->streamedContent();

    expect($body)->toContain('## Disputed')
        ->and($body)->toContain('Different target years.');
});

it('exports the whole thread as json', function () {
    $thread = exportableThread();

    $response = $this->get(route('threads.export', [$thread, 'json']));

    $response->assertOk()->assertDownload('solid-state-batteries.json');

    $payload = json_decode($response->streamedContent(), true);

    expect($payload['thread']['title'])->toBe('Solid state batteries')
        ->and($payload['facts'])->toHaveCount(1)
        ->and($payload['facts'][0]['source_url'])->toBe('https://example.com/cells')
        ->and($payload['sources'][0]['published_at'])->toBe(now()->subYears(2)->toDateString())
        ->and($payload['runs'][0]['query'])->toBe('Cell roadmaps 2027');
});

it('exports the sources as bibtex', function () {
    $thread = exportableThread();
    $source = $thread->sources()->sole();

    $response = $this->get(route('threads.export', [$thread, 'bibtex']));

    $response->assertOk()->assertDownload('solid-state-batteries.bib');

    $body = $response->streamedContent();

    expect($body)->toContain("@misc{examplecom{$source->id},")
        ->and($body)->toContain('title = {Cell roadmaps},')
        ->and($body)->toContain('url = {https://example.com/cells},')
        ->and($body)->toContain('year = {'.now()->subYears(2)->format('Y').'},');
});

it('rejects a format it cannot write', function () {
    $this->get(route('threads.export', [Thread::factory()->create(), 'docx']))->assertNotFound();
});
