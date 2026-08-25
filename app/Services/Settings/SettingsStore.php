<?php

namespace App\Services\Settings;

/**
 * Where the reader's own settings live.
 *
 * A packaged desktop app cannot use .env for this: the file sits inside the
 * bundle, is not editable by the person running the app, and is replaced
 * wholesale by every update. Implementations therefore keep settings outside
 * the bundle and are responsible for encrypting the values marked secret.
 */
interface SettingsStore
{
    public function get(string $key, bool $secret = false): ?string;

    /**
     * A null or empty value removes the key rather than storing a blank.
     */
    public function set(string $key, ?string $value, bool $secret = false): void;
}
