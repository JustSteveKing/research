<?php

namespace App\Services\Settings;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

/**
 * Settings for everything that is not the packaged app: `composer dev`, the
 * test suite, artisan. There is no Electron process to talk to, so the values
 * go to a JSON file on the local disk and secrets are wrapped with APP_KEY.
 *
 * That is weaker than the keychain {@see NativeSettingsStore} uses, which is
 * acceptable here because this store never ships to a reader.
 */
class FileSettingsStore implements SettingsStore
{
    public function __construct(
        protected string $disk = 'local',
        protected string $path = 'settings.json',
    ) {}

    public function get(string $key, bool $secret = false): ?string
    {
        $value = $this->read()[$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (! $secret) {
            return $value;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Written under a different APP_KEY: treat it as absent rather
            // than failing every request that reads a setting.
            return null;
        }
    }

    public function set(string $key, ?string $value, bool $secret = false): void
    {
        $settings = $this->read();

        if (blank($value)) {
            unset($settings[$key]);
        } else {
            $settings[$key] = $secret ? Crypt::encryptString($value) : $value;
        }

        Storage::disk($this->disk)->put(
            $this->path,
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function read(): array
    {
        $disk = Storage::disk($this->disk);

        if (! $disk->exists($this->path)) {
            return [];
        }

        $decoded = json_decode((string) $disk->get($this->path), associative: true);

        return is_array($decoded) ? $decoded : [];
    }
}
