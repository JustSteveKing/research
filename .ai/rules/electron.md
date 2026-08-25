---
paths:
  - 'nativephp/**, config/nativephp.php, resources/electron/**'
---

# Electron

## Never edit nativephp/electron in place — stamp resources in from prebuild
`native:install --force` mirrors nativephp/electron from vendor with delete: true, so anything edited in place is reverted. CI runs that install on every build (composer install does not fire post-update-cmd, and electron-plugin/dist is gitignored upstream but needed by electron-vite for the `#plugin` import).

Custom icons have a second problem: `installIcon` resolves through ElectronServiceProvider::electronPath('build/icon.png'), which tests for package.json *inside the path it was given*, so for any non-empty path it falls through to the vendor copy. electron-builder reads nativephp/electron/build, so the icon silently never reaches the packaged app.

Both are handled by `php artisan app:publish-electron-resources`, wired into nativephp.prebuild, which copies public/icon.{png,icns,ico} and resources/electron/notarize.js into nativephp/electron/build after any install. Add new Electron build resources to that command's RESOURCES map, never to nativephp/ directly. tests/Feature/PublishElectronResourcesTest.php covers it.
