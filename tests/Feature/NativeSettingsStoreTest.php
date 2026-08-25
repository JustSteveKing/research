<?php

use App\Services\Settings\NativeSettingsStore;
use Illuminate\Support\Facades\Http;

/**
 * The store talks to the Electron process over its local HTTP API, so faking
 * that API is enough to exercise the keychain path without a running app.
 */
function fakeElectron(array $overrides = []): void
{
    Http::fake([
        '*/api/system/can-encrypt' => Http::response(['result' => true]),
        '*/api/system/encrypt' => fn ($request) => Http::response([
            'result' => base64_encode((string) $request->data()['string']),
        ]),
        '*/api/system/decrypt' => fn ($request) => Http::response([
            'result' => base64_decode((string) $request->data()['string']),
        ]),
        ...$overrides,
    ]);
}

it('wraps a secret with the platform keychain before storing it', function (): void {
    $stored = null;

    fakeElectron([
        '*/api/settings/*' => function ($request) use (&$stored) {
            if ($request->method() === 'POST') {
                $stored = $request->data()['value'];

                return Http::response(status: 200);
            }

            return Http::response(['value' => $stored]);
        },
    ]);

    $store = app(NativeSettingsStore::class);
    $store->set('tabstack.api_key', 'tbk_live', secret: true);

    expect($stored)->toBe('enc:'.base64_encode('tbk_live'))
        ->and($store->get('tabstack.api_key', secret: true))->toBe('tbk_live');
});

it('stores a value the keychain cannot wrap as it is, and still reads it back', function (): void {
    $stored = null;

    fakeElectron([
        '*/api/system/can-encrypt' => Http::response(['result' => false]),
        '*/api/settings/*' => function ($request) use (&$stored) {
            if ($request->method() === 'POST') {
                $stored = $request->data()['value'];

                return Http::response(status: 200);
            }

            return Http::response(['value' => $stored]);
        },
    ]);

    $store = app(NativeSettingsStore::class);
    $store->set('tabstack.api_key', 'tbk_live', secret: true);

    expect($stored)->toBe('tbk_live')
        ->and($store->get('tabstack.api_key', secret: true))->toBe('tbk_live');
});

it('deletes the key rather than storing a blank', function (): void {
    fakeElectron(['*/api/settings/*' => Http::response(status: 200)]);

    app(NativeSettingsStore::class)->set('tabstack.api_key', '', secret: true);

    Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
        && str_contains($request->url(), 'settings/tabstack.api_key'));
});

it('reads an absent setting as null', function (): void {
    fakeElectron(['*/api/settings/*' => Http::response(['value' => null])]);

    expect(app(NativeSettingsStore::class)->get('tabstack.api_key', secret: true))->toBeNull();
});
