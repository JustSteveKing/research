<?php

namespace App\Services\Settings;

use Native\Desktop\Settings;
use Native\Desktop\System;
use RuntimeException;

/**
 * Settings for the packaged desktop app. Values go to Electron's store in the
 * OS application-support directory, which sits outside the bundle and so
 * survives updates. Secrets are wrapped by the platform keychain
 * (macOS Keychain, libsecret / kwallet on Linux) rather than by APP_KEY,
 * which is baked identically into every copy of the app and therefore
 * protects nothing.
 *
 * The concrete classes are injected rather than their facades: the facade
 * docblocks type the encryption calls as returning a plain string, when the
 * underlying methods return null if the Electron process refuses.
 */
class NativeSettingsStore implements SettingsStore
{
    /**
     * Marks a value the keychain wrapped, so a store written on a machine
     * without a keyring can still be read back.
     */
    private const ENCRYPTED_PREFIX = 'enc:';

    public function __construct(
        protected Settings $settings,
        protected System $system,
    ) {}

    public function get(string $key, bool $secret = false): ?string
    {
        $value = $this->settings->get($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $secret ? $this->decrypt($value) : $value;
    }

    public function set(string $key, ?string $value, bool $secret = false): void
    {
        if (blank($value)) {
            $this->settings->forget($key);

            return;
        }

        $this->settings->set($key, $secret ? $this->encrypt($value) : $value);
    }

    protected function encrypt(string $value): string
    {
        if (! $this->system->canEncrypt()) {
            return $value;
        }

        $encrypted = $this->system->encrypt($value);

        if ($encrypted === null) {
            throw new RuntimeException('The system reported encryption as available but refused to encrypt.');
        }

        return self::ENCRYPTED_PREFIX.$encrypted;
    }

    protected function decrypt(string $value): ?string
    {
        if (! str_starts_with($value, self::ENCRYPTED_PREFIX)) {
            return $value;
        }

        return $this->system->decrypt(substr($value, strlen(self::ENCRYPTED_PREFIX)));
    }
}
