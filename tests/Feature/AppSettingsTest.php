<?php

use App\Services\Settings\AppSettings;
use App\Services\Settings\FileSettingsStore;
use App\Services\Settings\SettingsStore;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function appSettings(): AppSettings
{
    return new AppSettings(new FileSettingsStore);
}

it('falls back to config when nothing has been saved', function (): void {
    config(['services.tabstack.key' => 'from-env']);

    expect(appSettings()->tabstackKey())->toBe('from-env');
});

it('prefers a saved value over config', function (): void {
    config(['services.ollama.chat_model' => 'gemma4:latest']);

    appSettings()->put([AppSettings::OLLAMA_CHAT_MODEL => 'qwen3:8b']);

    expect(appSettings()->ollamaChatModel())->toBe('qwen3:8b');
});

it('never writes a secret to disk in the clear', function (): void {
    appSettings()->put([AppSettings::TABSTACK_API_KEY => 'tbk_super_secret']);

    $onDisk = Storage::disk('local')->get('settings.json');

    expect($onDisk)->not->toContain('tbk_super_secret')
        ->and(appSettings()->tabstackKey())->toBe('tbk_super_secret');
});

it('stores plain values without encrypting them', function (): void {
    appSettings()->put([AppSettings::OLLAMA_BASE_URL => 'http://127.0.0.1:11434']);

    expect(Storage::disk('local')->get('settings.json'))
        ->toContain('http://127.0.0.1:11434');
});

it('treats a blank value as removing the setting', function (): void {
    config(['services.tabstack.key' => null]);

    $settings = appSettings();
    $settings->put([AppSettings::TABSTACK_API_KEY => 'tbk_live']);
    expect($settings->tabstackKey())->toBe('tbk_live');

    appSettings()->put([AppSettings::TABSTACK_API_KEY => '']);

    expect(appSettings()->tabstackKey())->toBeNull();
});

it('reads a secret back as absent when it was written under another key', function (): void {
    appSettings()->put([AppSettings::TABSTACK_API_KEY => 'tbk_live']);

    Storage::disk('local')->put('settings.json', json_encode([
        AppSettings::TABSTACK_API_KEY => 'not-a-valid-payload',
    ]));

    config(['services.tabstack.key' => null]);

    expect(appSettings()->tabstackKey())->toBeNull();
});

it('rejects a key it does not know about', function (): void {
    appSettings()->put(['tabstack.secret_backdoor' => 'nope']);
})->throws(InvalidArgumentException::class);

it('asks for setup only when there is no key and offline mode is off', function (): void {
    config(['services.tabstack.key' => null, 'research.offline' => false]);
    expect(appSettings()->needsSetup())->toBeTrue();

    config(['research.offline' => true]);
    expect(appSettings()->needsSetup())->toBeFalse();

    config(['research.offline' => false]);
    appSettings()->put([AppSettings::TABSTACK_API_KEY => 'tbk_live']);
    expect(appSettings()->needsSetup())->toBeFalse();
});

it('stops asking for setup once it has been dismissed', function (): void {
    config(['services.tabstack.key' => null, 'research.offline' => false]);

    appSettings()->dismissSetup();

    expect(appSettings()->needsSetup())->toBeFalse();
});

it('reports when the key is coming from the environment', function (): void {
    config(['services.tabstack.key' => 'from-env']);
    expect(appSettings()->tabstackKeyComesFromEnv())->toBeTrue();

    appSettings()->put([AppSettings::TABSTACK_API_KEY => 'tbk_saved']);
    expect(appSettings()->tabstackKeyComesFromEnv())->toBeFalse();
});

it('resolves the file store outside the native runtime', function (): void {
    config(['nativephp-internal.running' => false]);

    expect(app(SettingsStore::class))->toBeInstanceOf(FileSettingsStore::class);
});
