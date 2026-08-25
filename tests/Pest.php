<?php

use App\Services\Tabstack\FakeTabstackClient;
use App\Services\Tabstack\TabstackClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Swap in the recorded Tabstack stream so no credits are spent in tests.
 */
function fakeTabstack(string $fixture = 'research-complete'): FakeTabstackClient
{
    $client = new FakeTabstackClient(database_path("fixtures/{$fixture}.sse"));

    app()->instance(TabstackClient::class, $client);

    return $client;
}

/**
 * Fake the local Ollama API. Responses are routed by the kind of prompt the
 * app sends, so tests do not have to predict how many calls each job makes.
 *
 * @param  array{facts?: list<array<string, mixed>>, questions?: list<array<string, mixed>>, verdicts?: list<array<string, mixed>>, topics?: list<array<string, mixed>>, brief?: string, chat?: string}  $responses
 */
function fakeOllama(array $responses = []): void
{
    Http::fake([
        '*/api/chat' => function (Request $request) use ($responses) {
            $prompt = (string) collect($request->data()['messages'] ?? [])->pluck('content')->implode(' ');

            $body = match (true) {
                str_contains($prompt, 'atomic') => json_encode(['facts' => $responses['facts'] ?? []]),
                str_contains($prompt, 'decide how they relate') => json_encode(['verdicts' => $responses['verdicts'] ?? []]),
                str_contains($prompt, 'sub-topics') => json_encode(['topics' => $responses['topics'] ?? []]),
                str_contains($prompt, 'plan the next steps') => json_encode(['questions' => $responses['questions'] ?? []]),
                str_contains($prompt, 'living research brief') => $responses['brief'] ?? 'A living brief.',
                default => $responses['chat'] ?? 'An answer grounded in the thread.',
            };

            return Http::response(['message' => ['content' => $body]]);
        },
        '*/api/embed' => function (Request $request) {
            $inputs = (array) ($request->data()['input'] ?? []);

            return Http::response(['embeddings' => array_map(embeddingFor(...), $inputs)]);
        },
        '*/api/tags' => Http::response(['models' => [['name' => 'gemma4:latest'], ['name' => 'nomic-embed-text']]]),
    ]);
}

/**
 * A deterministic pseudo-embedding: strings sharing words land close together,
 * strings with different words do not.
 *
 * @return array<int, float>
 */
function embeddingFor(string $text): array
{
    $vector = array_fill(0, 64, 0.0);
    $words = preg_split('/\W+/', mb_strtolower($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];

    foreach ($words as $word) {
        $vector[crc32($word) % 64] += 1.0;
    }

    return $vector;
}
