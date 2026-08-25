<?php

namespace App\Providers;

use App\Services\Ollama\OllamaClient;
use App\Services\Settings\AppSettings;
use App\Services\Settings\FileSettingsStore;
use App\Services\Settings\NativeSettingsStore;
use App\Services\Settings\SettingsStore;
use App\Services\Tabstack\FakeTabstackClient;
use App\Services\Tabstack\HttpTabstackClient;
use App\Services\Tabstack\TabstackClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SettingsStore::class, fn (Application $app): SettingsStore => config('nativephp-internal.running')
            ? $app->make(NativeSettingsStore::class)
            : new FileSettingsStore,
        );

        // Deliberately bind() and not singleton(). The desktop app starts a
        // long-lived worker per queue at boot; a singleton resolved before the
        // reader had entered a key would keep the fixture-replaying client for
        // the life of that worker, and every run would silently replay the
        // sample stream instead of researching. Resolving per job costs a
        // couple of settings reads and keeps the credentials current.
        $this->app->bind(TabstackClient::class, function (Application $app): TabstackClient {
            $apiKey = $app->make(AppSettings::class)->tabstackKey();

            if (config('research.offline') || blank($apiKey)) {
                return new FakeTabstackClient(database_path('fixtures/research-fast.sse'));
            }

            return new HttpTabstackClient(
                apiKey: $apiKey,
                baseUrl: config('services.tabstack.base_url'),
                timeout: config('services.tabstack.timeout'),
                fetchTimeout: config('services.tabstack.fetch_timeout'),
            );
        });

        $this->app->bind(OllamaClient::class, function (Application $app): OllamaClient {
            $settings = $app->make(AppSettings::class);

            return new OllamaClient(
                baseUrl: $settings->ollamaBaseUrl(),
                chatModel: $settings->ollamaChatModel(),
                embedModel: $settings->ollamaEmbedModel(),
                timeout: config('services.ollama.timeout'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
