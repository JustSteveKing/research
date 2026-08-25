<?php

use App\Jobs\SynthesizeThreadBriefJob;
use App\Models\Fact;
use App\Models\Thread;
use Illuminate\Support\Facades\Http;

it('writes a living brief from the thread facts', function () {
    fakeOllama(['brief' => "## What we know\n\n- Toyota targets 2027."]);

    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->create(['statement' => 'Toyota targets 2027.']);

    SynthesizeThreadBriefJob::dispatchSync($thread);

    $thread->refresh();

    expect($thread->brief)->toContain('What we know')
        ->and($thread->brief_generated_at)->not->toBeNull();
});

it('leaves the brief alone when there is nothing to synthesise', function () {
    fakeOllama(['brief' => 'should not be called']);

    $thread = Thread::factory()->create();

    SynthesizeThreadBriefJob::dispatchSync($thread);

    expect($thread->fresh()->brief)->toBeNull();
    Http::assertNothingSent();
});

it('keeps the previous brief when ollama is down', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    $thread = Thread::factory()->create(['brief' => 'Earlier brief']);
    Fact::factory()->for($thread)->create();

    SynthesizeThreadBriefJob::dispatchSync($thread);

    expect($thread->fresh()->brief)->toBe('Earlier brief');
});
