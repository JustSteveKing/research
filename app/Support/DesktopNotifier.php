<?php

namespace App\Support;

use Native\Desktop\Facades\Notification;
use Throwable;

/**
 * Sends a desktop notification when the app runs inside NativePHP, and quietly
 * does nothing when the same code runs in a browser or during tests.
 */
class DesktopNotifier
{
    public function send(string $title, string $message): void
    {
        try {
            Notification::title($title)->message($message)->show();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
