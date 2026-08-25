<?php

use Illuminate\Support\Facades\File;

/**
 * Guards the workaround for NativePHP writing custom build resources into
 * vendor, and for `native:install --force` reverting anything edited in place.
 */
it('stamps every project-owned resource into the Electron build resources', function (string $source, string $destination): void {
    $buildResources = base_path('nativephp/electron/build');

    expect(File::isDirectory($buildResources))->toBeTrue(
        'The Electron project should be published; run `php artisan native:install --publish`.',
    );

    $this->artisan('app:publish-electron-resources')->assertSuccessful();

    expect(File::isFile(base_path($source)))->toBeTrue("{$source} is missing.")
        ->and(File::hash($buildResources.'/'.$destination))
        ->toBe(File::hash(base_path($source)), "{$source} did not reach the build resources.");
})->with([
    ['public/icon.png', 'icon.png'],
    ['public/icon.icns', 'icon.icns'],
    ['public/icon.ico', 'icon.ico'],
    ['resources/electron/notarize.js', 'notarize.js'],
]);

/**
 * Upstream calls notarize() with an empty Apple ID on unsigned builds because
 * it tests for the presence of the keys rather than their values, and
 * BuildCommand always sets them.
 */
it('skips notarization on the values of the Apple credentials, not their presence', function (): void {
    $hook = File::get(base_path('resources/electron/notarize.js'));

    expect($hook)->not->toContain("'NATIVEPHP_APPLE_ID' in process.env")
        ->and($hook)->toContain('if (!appleId || !appleIdPassword || !teamId)');
});
