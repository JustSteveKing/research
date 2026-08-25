<?php

use App\Jobs\DistillRunJob;
use App\Models\Contradiction;
use App\Models\Fact;
use App\Models\ResearchRun;
use App\Models\Thread;

it('pins a fact so a rebuild keeps it', function () {
    fakeOllama();

    $thread = Thread::factory()->create();
    $pinned = Fact::factory()->for($thread)->create();
    $distilled = Fact::factory()->for($thread)->create();

    $this->patch(route('threads.facts.update', [$thread, $pinned]), ['pinned' => true])
        ->assertRedirect();

    expect($pinned->fresh()->isPinned())->toBeTrue();

    $this->post(route('threads.rebuild', $thread));

    expect(Fact::query()->find($pinned->id))->not->toBeNull()
        ->and(Fact::query()->find($distilled->id))->toBeNull();
});

it('re-embeds a rewritten fact so retrieval still finds it', function () {
    fakeOllama();

    $thread = Thread::factory()->create();
    $fact = Fact::factory()->for($thread)->create([
        'statement' => 'Cells cost about three times lithium-ion.',
        'embedding' => embeddingFor('Cells cost about three times lithium-ion.'),
    ]);

    $this->patch(route('threads.facts.update', [$thread, $fact]), [
        'statement' => 'Cells cost about twice as much as lithium-ion packs.',
    ])->assertRedirect();

    $fact->refresh();

    expect($fact->statement)->toBe('Cells cost about twice as much as lithium-ion packs.')
        ->and($fact->edited_at)->not->toBeNull()
        ->and($fact->embedding)->toEqual(embeddingFor('Cells cost about twice as much as lithium-ion packs.'));
});

it('keeps an edited fact through a rebuild', function () {
    fakeOllama();

    $thread = Thread::factory()->create();
    $fact = Fact::factory()->for($thread)->create();

    $this->patch(route('threads.facts.update', [$thread, $fact]), ['statement' => 'A corrected statement of fact.']);
    $this->post(route('threads.rebuild', $thread));

    expect(Fact::query()->find($fact->id))->not->toBeNull();
});

it('keeps a dismissed fact out of the workspace but not out of the table', function () {
    $thread = Thread::factory()->create();
    $fact = Fact::factory()->for($thread)->create();

    $this->patch(route('threads.facts.update', [$thread, $fact]), ['dismissed' => true]);

    expect($thread->facts()->active()->count())->toBe(0)
        ->and($thread->facts()->count())->toBe(1);

    $this->patch(route('threads.facts.update', [$thread, $fact]), ['dismissed' => false]);

    expect($thread->facts()->active()->count())->toBe(1);
});

it('will not re-distil a fact the reader dismissed', function () {
    $statement = 'Sulfide electrolytes degrade in ambient moisture.';

    fakeOllama(['facts' => [['statement' => $statement, 'confidence' => 0.9]]]);

    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->dismissed()->create([
        'statement' => $statement,
        'embedding' => embeddingFor($statement),
    ]);

    DistillRunJob::dispatchSync(ResearchRun::factory()->for($thread)->completed('A report.')->create());

    expect($thread->facts()->count())->toBe(1)
        ->and($thread->facts()->active()->count())->toBe(0);
});

it('deletes a fact outright when asked', function () {
    $thread = Thread::factory()->create();
    $fact = Fact::factory()->for($thread)->create();

    $this->delete(route('threads.facts.destroy', [$thread, $fact]))->assertRedirect();

    expect(Fact::query()->count())->toBe(0);
});

it('refuses to curate a fact belonging to another thread', function () {
    $fact = Fact::factory()->for(Thread::factory())->create();

    $this->patch(route('threads.facts.update', [Thread::factory()->create(), $fact]), ['pinned' => true])
        ->assertNotFound();
});

it('settles a disputed pair without forgetting it', function () {
    $thread = Thread::factory()->create();
    $first = Fact::factory()->for($thread)->create();
    $second = Fact::factory()->for($thread)->create();

    $clash = Contradiction::factory()->create([
        'fact_id' => $first->id,
        'other_fact_id' => $second->id,
    ]);

    $this->patch(route('threads.contradictions.update', [$thread, $clash]), ['resolved' => true])
        ->assertRedirect();

    expect($clash->fresh()->resolved_at)->not->toBeNull()
        ->and(Contradiction::forFacts([$first->id])->count())->toBe(0)
        ->and(Contradiction::query()->count())->toBe(1);
});
