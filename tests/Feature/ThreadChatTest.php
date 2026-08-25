<?php

use App\Enums\ChatRole;
use App\Models\ChatMessage;
use App\Models\Fact;
use App\Models\Source;
use App\Models\Thread;
use Illuminate\Support\Facades\Http;

function fakeOllamaStream(array $tokens): void
{
    $ndjson = collect($tokens)
        ->map(fn (string $token): string => json_encode(['message' => ['content' => $token], 'done' => false]))
        ->push(json_encode(['message' => ['content' => ''], 'done' => true]))
        ->implode("\n")."\n";

    Http::fake([
        '*/api/chat' => Http::response($ndjson),
        '*/api/embed' => function ($request) {
            $inputs = (array) ($request->data()['input'] ?? []);

            return Http::response(['embeddings' => array_map(embeddingFor(...), $inputs)]);
        },
    ]);
}

it('stores a question without spending research credits', function () {
    $thread = Thread::factory()->create();

    $this->post(route('threads.chat.store', $thread), ['content' => 'What did we learn about cost?'])
        ->assertRedirect();

    expect($thread->chatMessages()->sole()->role)->toBe(ChatRole::User)
        ->and($thread->fresh()->credits_spent)->toBe(0);
});

it('streams a grounded answer and stores it with citations', function () {
    fakeOllamaStream(['Cost ', 'is roughly ', '3x lithium-ion.']);

    $thread = Thread::factory()->create();
    $source = Source::factory()->for($thread)->create(['url' => 'https://example.com/cost']);
    Fact::factory()->for($thread)->for($source)->create([
        'statement' => 'Solid-state cells cost about three times lithium-ion cells.',
        'embedding' => embeddingFor('Solid-state cells cost about three times lithium-ion cells.'),
    ]);
    ChatMessage::factory()->for($thread)->create(['content' => 'How much do they cost?']);

    $response = $this->get(route('threads.chat.stream', $thread));

    $response->assertOk();
    $body = $response->streamedContent();

    expect($body)->toContain('event: token')
        ->and($body)->toContain('Cost ', '3x lithium-ion.')
        ->and($body)->toContain('event: citations')
        ->and($body)->toContain('example.com');

    $answer = $thread->chatMessages()->where('role', ChatRole::Assistant)->sole();

    expect($answer->content)->toBe('Cost is roughly 3x lithium-ion.')
        ->and($answer->citations)->toBe([['url' => 'https://example.com/cost', 'title' => $source->title, 'thread' => null]]);
});

it('keeps newlines out of the sse framing', function () {
    fakeOllamaStream(["Line one\n\n", 'Line two']);

    $thread = Thread::factory()->create();
    ChatMessage::factory()->for($thread)->create();

    $body = $this->get(route('threads.chat.stream', $thread))->streamedContent();

    expect($body)->toContain('data: {"token":"Line one\\n\\n"}')
        ->and($thread->chatMessages()->where('role', ChatRole::Assistant)->sole()->content)
        ->toBe("Line one\n\nLine two");
});

it('keeps a partial answer when the reader disconnects', function () {
    fakeOllamaStream(['Half an ']);

    $thread = Thread::factory()->create();
    ChatMessage::factory()->for($thread)->create();

    $this->get(route('threads.chat.stream', $thread))->streamedContent();

    expect($thread->chatMessages()->where('role', ChatRole::Assistant)->sole()->content)
        ->toBe('Half an ');
});

it('grounds the answer in the facts closest to the question', function () {
    fakeOllamaStream(['answer']);

    $thread = Thread::factory()->create();
    Fact::factory()->for($thread)->create([
        'statement' => 'Sulfide electrolytes degrade in ambient moisture.',
        'embedding' => embeddingFor('Sulfide electrolytes degrade in ambient moisture.'),
    ]);
    Fact::factory()->for($thread)->create([
        'statement' => 'Toyota targets 2027 for volume production.',
        'embedding' => embeddingFor('Toyota targets 2027 for volume production.'),
    ]);
    ChatMessage::factory()->for($thread)->create(['content' => 'What did Toyota say about 2027 volume production?']);

    $this->get(route('threads.chat.stream', $thread))->streamedContent();

    Http::assertSent(function ($request) {
        $system = $request->data()['messages'][0]['content'] ?? '';

        return str_contains($system, 'Toyota targets 2027');
    });
});

it('tells the user when the local model is unreachable', function () {
    Http::fake(['*' => Http::response('nope', 500)]);

    $thread = Thread::factory()->create();
    ChatMessage::factory()->for($thread)->create();

    $body = $this->get(route('threads.chat.stream', $thread))->streamedContent();

    expect($body)->toContain('event: failed')
        ->and($thread->chatMessages()->where('role', ChatRole::Assistant)->count())->toBe(0);
});

it('has nothing to answer before a question is asked', function () {
    $this->get(route('threads.chat.stream', Thread::factory()->create()))->assertNotFound();
});

it('answers the question it was asked, not whichever landed last', function () {
    fakeOllamaStream(['An answer to the first question.']);

    $thread = Thread::factory()->create();
    $first = ChatMessage::factory()->for($thread)->create(['content' => 'What did we learn about cost?']);
    ChatMessage::factory()->for($thread)->create(['content' => 'And what about weight?']);

    $this->get(route('threads.chat.stream', ['thread' => $thread, 'message' => $first->id]))
        ->streamedContent();

    expect($thread->chatMessages()->where('role', ChatRole::Assistant)->sole()->replies_to_id)
        ->toBe($first->id);
});

it('refuses to answer the same question twice', function () {
    fakeOllamaStream(['An answer.']);

    $thread = Thread::factory()->create();
    $question = ChatMessage::factory()->for($thread)->create();

    $this->get(route('threads.chat.stream', ['thread' => $thread, 'message' => $question->id]))
        ->streamedContent();

    $this->get(route('threads.chat.stream', ['thread' => $thread, 'message' => $question->id]))
        ->assertNotFound();

    expect($thread->chatMessages()->where('role', ChatRole::Assistant)->count())->toBe(1);
});

it('will not stream a question from another thread', function () {
    $question = ChatMessage::factory()->for(Thread::factory())->create();

    $this->get(route('threads.chat.stream', [
        'thread' => Thread::factory()->create(),
        'message' => $question->id,
    ]))->assertNotFound();
});
