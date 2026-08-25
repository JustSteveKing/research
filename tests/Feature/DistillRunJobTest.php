<?php

use App\Jobs\DistillRunJob;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Source;
use App\Models\Thread;
use Illuminate\Support\Facades\Http;

it('distils the report into embedded facts attributed to their source', function () {
    fakeOllama(['facts' => [
        [
            'statement' => 'Toyota targets 2027 for volume production of solid-state cells.',
            'confidence' => 0.9,
            'tags' => ['toyota', 'timeline'],
            'source_url' => 'https://reuters.com/toyota',
        ],
        [
            'statement' => 'Sulfide electrolytes require dry-room assembly.',
            'confidence' => 0.8,
            'tags' => ['chemistry'],
            'source_url' => null,
        ],
    ]]);

    $thread = Thread::factory()->create();
    $source = Source::factory()->for($thread)->create(['url' => 'https://reuters.com/toyota']);
    $run = ResearchRun::factory()->for($thread)->completed('Toyota targets 2027.')->create();

    DistillRunJob::dispatchSync($run);

    expect($thread->facts()->count())->toBe(2);

    $attributed = $thread->facts()->whereNotNull('source_id')->sole();

    expect($attributed->source_id)->toBe($source->id)
        ->and($attributed->tags)->toBe(['toyota', 'timeline'])
        ->and($attributed->confidence)->toBe(0.9)
        ->and($attributed->embedding)->toBeArray()
        ->and($thread->facts()->whereNull('source_id')->count())->toBe(1);
});

it('works on a fast mode run where cited pages carry no claims', function () {
    fakeOllama(['facts' => [
        ['statement' => 'Laravel API resources transform models into JSON.', 'confidence' => 0.8, 'tags' => ['laravel']],
    ]]);

    $thread = Thread::factory()->create();
    Source::factory()->for($thread)->create(['claims' => [], 'summary' => null, 'relevance' => null]);
    $run = ResearchRun::factory()->for($thread)->completed('## Report\n\nAPI resources transform models.')->create([
        'metadata' => ['mode' => 'fast', 'citedPages' => [['id' => 'a', 'url' => 'https://laravel.com/docs', 'title' => 'Docs', 'claims' => []]]],
    ]);

    DistillRunJob::dispatchSync($run);

    expect($thread->facts()->count())->toBe(1)
        ->and(Source::query()->sole()->distilled)->toBeTrue();
});

it('discards a statement the thread already holds word for word', function () {
    $statement = 'Toyota targets 2027 for volume production of solid-state cells.';

    fakeOllama(['facts' => [['statement' => '  toyota TARGETS 2027 for volume  production of solid-state cells. ', 'confidence' => 0.9, 'tags' => []]]]);

    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->create([
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);
    $run = ResearchRun::factory()->for($thread)->completed('A report mentioning the same thing.')->create();

    DistillRunJob::dispatchSync($run);

    expect($thread->facts()->count())->toBe(1);
});

it('keeps a near-duplicate for the adjudicator instead of guessing', function () {
    // Cosine cannot tell these apart from a reword, so distillation must not
    // decide: a dropped fact here is a lost contradiction.
    fakeOllama(['facts' => [['statement' => 'Toyota targets 2030 for volume production of solid-state cells.', 'confidence' => 0.9, 'tags' => []]]]);

    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->create([
        'statement' => 'Toyota targets 2027 for volume production of solid-state cells.',
        'embedding' => embeddingFor('Toyota targets 2027 for volume production of solid-state cells.'),
    ]);
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create();

    DistillRunJob::dispatchSync($run);

    expect($thread->facts()->count())->toBe(2);
});

it('still refuses to bring back a statement the reader dismissed', function () {
    $statement = 'A claim the reader threw out.';

    fakeOllama(['facts' => [['statement' => $statement, 'confidence' => 0.9, 'tags' => []]]]);

    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->dismissed()->create(['statement' => $statement]);
    $run = ResearchRun::factory()->for($thread)->completed('A report.')->create();

    DistillRunJob::dispatchSync($run);

    expect($thread->facts()->count())->toBe(1)
        ->and($thread->facts()->active()->count())->toBe(0);
});

it('does nothing when the run produced no report', function () {
    fakeOllama(['facts' => [['statement' => 'Should not be extracted.', 'confidence' => 0.5]]]);

    $run = ResearchRun::factory()->create(['report' => null]);

    DistillRunJob::dispatchSync($run);

    expect(Fact::query()->count())->toBe(0);
    Http::assertNothingSent();
});
