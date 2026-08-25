<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

/**
 * Small confirmations for actions whose effect happens off-screen.
 */
class Toast
{
    public static function attach(
        RedirectResponse $response,
        string $message,
        string $tone = 'neutral',
    ): RedirectResponse {
        return $response->with('toast', [
            'id' => (string) str()->uuid(),
            'message' => $message,
            'tone' => $tone,
        ]);
    }
}
