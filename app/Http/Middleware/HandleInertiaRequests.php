<?php

namespace App\Http\Middleware;

use App\Services\Research\WorkspaceState;
use App\Services\Settings\AppSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            // The shell is persistent, so its data travels with every response
            // and can be refreshed on its own with a partial reload.
            // One-shot confirmation for actions that would otherwise be silent.
            'toast' => fn (): ?array => $request->session()->get('toast'),
            'rail' => fn (): array => app(WorkspaceState::class)->rail(),
            'activity' => fn (): array => app(WorkspaceState::class)->activity(),
            // A packaged app ships without credentials, so the shell asks for
            // them on first run rather than letting runs quietly do nothing.
            'setup' => fn (): array => [
                'needs_tabstack_key' => app(AppSettings::class)->needsSetup(),
            ],
        ];
    }
}
