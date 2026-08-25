<?php

namespace App\Http\Controllers;

use App\Enums\ResearchMode;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Ollama\OllamaClient;
use App\Services\Settings\AppSettings;
use App\Services\Tabstack\FakeTabstackClient;
use App\Services\Tabstack\TabstackClient;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    public function edit(OllamaClient $ollama, TabstackClient $tabstack, AppSettings $settings): Response
    {
        try {
            $models = collect($ollama->models())->pluck('name')->values()->all();
        } catch (Throwable) {
            $models = [];
        }

        $defaults = $settings->researchDefaults();

        return Inertia::render('settings', [
            'tabstack' => [
                // The key itself is never sent to the client; only whether
                // there is one and where it came from.
                'configured' => filled($settings->tabstackKey()),
                'from_env' => $settings->tabstackKeyComesFromEnv(),
                'base_url' => config('services.tabstack.base_url'),
                'replaying_fixtures' => $tabstack instanceof FakeTabstackClient,
            ],
            'ollama' => [
                'base_url' => $settings->ollamaBaseUrl(),
                'chat_model' => $settings->ollamaChatModel(),
                'embed_model' => $settings->ollamaEmbedModel(),
                'reachable' => $models !== [],
                'models' => $models,
            ],
            'defaults' => [
                ...$defaults,
                'costs' => [
                    ResearchMode::Fast->value => ResearchMode::Fast->creditCost(),
                    ResearchMode::Balanced->value => ResearchMode::Balanced->creditCost(),
                ],
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request, AppSettings $settings): RedirectResponse
    {
        $settings->put($request->settings());

        return Toast::attach(back(), 'Settings saved');
    }

    /**
     * Stop asking for a Tabstack key on start-up. Runs then replay the
     * recorded sample stream until a key is entered on this page.
     */
    public function skipSetup(AppSettings $settings): RedirectResponse
    {
        $settings->dismissSetup();

        return Toast::attach(back(), 'You can add a key in Settings whenever you like');
    }
}
