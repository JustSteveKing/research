<?php

use App\Services\Ollama\OllamaClient;
use App\Services\Settings\AppSettings;
use App\Services\Tabstack\FakeTabstackClient;
use App\Services\Tabstack\HttpTabstackClient;
use App\Services\Tabstack\TabstackClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');

    // Nothing here may reach Tabstack: the clients are only ever built and
    // inspected, never called.
    Http::preventStrayRequests();
});

/**
 * The desktop app starts one long-lived worker per queue when it boots. If the
 * clients were singletons, a worker that started before the reader entered a
 * key would hold the fixture-replaying client until the app restarted, and
 * every run would quietly replay the sample stream instead of researching.
 */
it('builds a live Tabstack client once a key is saved, without a restart', function (): void {
    config(['research.offline' => false, 'services.tabstack.key' => null]);

    expect(app(TabstackClient::class))->toBeInstanceOf(FakeTabstackClient::class);

    app(AppSettings::class)->put([AppSettings::TABSTACK_API_KEY => 'tbk_live']);

    expect(app(TabstackClient::class))->toBeInstanceOf(HttpTabstackClient::class);
});

it('picks up an Ollama model change on the next resolve', function (): void {
    config(['services.ollama.chat_model' => 'gemma4:latest']);

    expect(app(OllamaClient::class)->chatModel())->toBe('gemma4:latest');

    app(AppSettings::class)->put([AppSettings::OLLAMA_CHAT_MODEL => 'qwen3:8b']);

    expect(app(OllamaClient::class)->chatModel())->toBe('qwen3:8b');
});

it('keeps replaying fixtures while offline mode is on, key or not', function (): void {
    config(['research.offline' => true]);

    app(AppSettings::class)->put([AppSettings::TABSTACK_API_KEY => 'tbk_live']);

    expect(app(TabstackClient::class))->toBeInstanceOf(FakeTabstackClient::class);
});

it('does not share client instances between resolves', function (): void {
    expect(app(TabstackClient::class))->not->toBe(app(TabstackClient::class))
        ->and(app(OllamaClient::class))->not->toBe(app(OllamaClient::class));
});
