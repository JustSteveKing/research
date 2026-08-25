<?php

namespace App\Providers;

use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     */
    public function boot(): void
    {
        Window::open()
            ->route('threads.index')
            ->title('Research')
            ->width(1440)
            ->height(920)
            ->minWidth(860)
            ->minHeight(600)
            // The workspace draws its own title bar, so the frame only keeps
            // the traffic lights.
            ->titleBarHiddenInset()
            ->rememberState();

        Menu::create(
            Menu::app(),
            Menu::make(
                // Digits belong to the inspector, so navigation uses shifted keys.
                Menu::route('threads.index', 'All threads', 'CmdOrCtrl+Shift+T'),
                Menu::route('settings.edit', 'Settings', 'CmdOrCtrl+Shift+,'),
                Menu::separator(),
                Menu::reload(),
                Menu::close(),
            )->label('Research'),
            Menu::edit(),
            Menu::window(),
        );
    }

    /**
     * Return an array of php.ini directives to be set.
     *
     * @return array<string, string>
     */
    public function phpIni(): array
    {
        return [
            // Reports and SSE payloads are held in memory while a run streams.
            'memory_limit' => '512M',
            'max_execution_time' => '0',
        ];
    }
}
