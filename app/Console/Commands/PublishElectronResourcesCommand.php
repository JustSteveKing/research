<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Stamp this project's own build resources into the published Electron project.
 *
 * Two upstream behaviours make this necessary, and both bite silently:
 *
 * 1. `installIcon` resolves its destination through
 *    ElectronServiceProvider::electronPath('build/icon.png'), which tests for a
 *    package.json *inside the path it was handed* and so always falls through
 *    to the copy in vendor. electron-builder reads nativephp/electron/build, so
 *    a custom icon never reaches the packaged app.
 * 2. `native:install --force` mirrors nativephp/electron from vendor with
 *    delete: true, so any edit made in place — such as the notarize hook fix in
 *    resources/electron — is reverted on the next install. CI runs that install
 *    on every build.
 *
 * Running this from nativephp.prebuild puts the files where electron-builder
 * looks, after any install has had its way.
 */
class PublishElectronResourcesCommand extends Command
{
    protected $signature = 'app:publish-electron-resources';

    protected $description = 'Copy this project\'s icons and Electron build resources into the published Electron project';

    /**
     * Source path (relative to the project root) => destination filename inside
     * the Electron project's build resources.
     *
     * @var array<string, string>
     */
    protected const RESOURCES = [
        'public/icon.png' => 'icon.png',
        'public/icon.icns' => 'icon.icns',
        'public/icon.ico' => 'icon.ico',
        'resources/electron/notarize.js' => 'notarize.js',
    ];

    public function handle(): int
    {
        $buildResources = base_path('nativephp/electron/build');

        if (! File::isDirectory($buildResources)) {
            $this->components->warn('No published Electron project; NativePHP will use its own build resources.');

            return self::SUCCESS;
        }

        foreach (self::RESOURCES as $source => $destination) {
            $path = base_path($source);

            if (! File::isFile($path)) {
                $this->components->warn("Missing {$source}; the build will fall back to the NativePHP default.");

                continue;
            }

            File::copy($path, $buildResources.DIRECTORY_SEPARATOR.$destination);

            $this->components->twoColumnDetail($destination, 'copied');
        }

        return self::SUCCESS;
    }
}
