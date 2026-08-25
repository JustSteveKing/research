import { notarize } from '@electron/notarize';

/**
 * Project-owned replacement for the notarize hook NativePHP publishes.
 *
 * Upstream guards on the Apple credential keys being present in the
 * environment rather than on their values. BuildCommand::getEnvironmentVariables()
 * always sets those three keys — empty when no credentials are configured — so
 * the guard passes and notarize() is
 * called with an empty Apple ID. Unsigned builds still succeed, but every log
 * carries "The appleId property is required when using notarization with
 * password credentials". Checking the values rather than the keys skips
 * notarization properly.
 *
 * `php artisan app:publish-electron-resources` copies this over the published
 * copy before each build, because `native:install --force` rewrites
 * nativephp/electron wholesale from vendor.
 */
export default async (context) => {
    // Only notarize when process is running on a Mac
    if (process.platform !== 'darwin') return;

    // And the current build target is macOS
    if (context.packager.platform.name !== 'mac') return;

    const appleId = process.env.NATIVEPHP_APPLE_ID;
    const appleIdPassword = process.env.NATIVEPHP_APPLE_ID_PASS;
    const teamId = process.env.NATIVEPHP_APPLE_TEAM_ID;

    if (!appleId || !appleIdPassword || !teamId) {
        console.warn(
            'skipping notarizing, NATIVEPHP_APPLE_ID, NATIVEPHP_APPLE_ID_PASS and NATIVEPHP_APPLE_TEAM_ID env variables must be set.',
        );
        return;
    }

    console.log('aftersign hook triggered, start to notarize app.');

    const appId = process.env.NATIVEPHP_APP_ID;

    const { appOutDir } = context;

    const appName = context.packager.appInfo.productFilename;

    try {
        await notarize({
            appBundleId: appId,
            appPath: `${appOutDir}/${appName}.app`,
            appleId,
            appleIdPassword,
            teamId,
            tool: 'notarytool',
        });
    } catch (error) {
        console.error(error);
    }

    console.log(`done notarizing ${appId}.`);
};
