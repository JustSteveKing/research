<?php

use App\Services\Settings\AppSettings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Storage::fake('local');

    fakeOllama();
});

it('shows the key as missing before one is entered', function (): void {
    config(['services.tabstack.key' => null]);

    $this->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('settings')
            ->where('tabstack.configured', false)
            ->where('tabstack.replaying_fixtures', true),
        );
});

it('never sends the key itself to the client', function (): void {
    app(AppSettings::class)->put([AppSettings::TABSTACK_API_KEY => 'tbk_super_secret']);

    $response = $this->get(route('settings.edit'))->assertOk();

    expect($response->content())->not->toContain('tbk_super_secret');
});

it('saves a Tabstack key from the settings page', function (): void {
    config(['services.tabstack.key' => null]);

    $this->patch(route('settings.update'), ['tabstack_api_key' => '  tbk_live  '])
        ->assertRedirect();

    expect(app(AppSettings::class)->tabstackKey())->toBe('tbk_live');
});

it('clears the key when an empty value is saved', function (): void {
    config(['services.tabstack.key' => null]);

    app(AppSettings::class)->put([AppSettings::TABSTACK_API_KEY => 'tbk_live']);

    $this->patch(route('settings.update'), ['tabstack_api_key' => ''])
        ->assertRedirect();

    expect(app(AppSettings::class)->tabstackKey())->toBeNull();
});

it('saves the Ollama connection and the new thread defaults', function (): void {
    $this->patch(route('settings.update'), [
        'ollama_base_url' => 'http://127.0.0.1:11500',
        'ollama_chat_model' => 'qwen3:8b',
        'ollama_embed_model' => 'mxbai-embed-large',
        'default_mode' => 'balanced',
        'default_budget' => 3500,
        'max_depth' => 5,
    ])->assertRedirect();

    $settings = app(AppSettings::class);

    expect($settings->ollamaBaseUrl())->toBe('http://127.0.0.1:11500')
        ->and($settings->ollamaChatModel())->toBe('qwen3:8b')
        ->and($settings->ollamaEmbedModel())->toBe('mxbai-embed-large')
        ->and($settings->researchDefaults())->toMatchArray([
            'mode' => 'balanced',
            'credit_budget' => 3500,
            'max_depth' => 5,
        ]);
});

it('rejects settings that would break a run', function (array $payload): void {
    $this->patch(route('settings.update'), $payload)
        ->assertSessionHasErrors(array_key_first($payload));
})->with([
    'a mode that does not exist' => [['default_mode' => 'exhaustive']],
    'a budget below one run' => [['default_budget' => 10]],
    'a negative follow-up depth' => [['max_depth' => -1]],
    'a host that is not a url' => [['ollama_base_url' => 'localhost:11434']],
    'a blank chat model' => [['ollama_chat_model' => '']],
]);

it('feeds the saved defaults to the thread composer', function (): void {
    app(AppSettings::class)->put([
        AppSettings::RESEARCH_DEFAULT_MODE => 'balanced',
        AppSettings::RESEARCH_DEFAULT_BUDGET => 4000,
    ]);

    $this->get(route('threads.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('defaults.mode', 'balanced')
            ->where('defaults.credit_budget', 4000),
        );
});
