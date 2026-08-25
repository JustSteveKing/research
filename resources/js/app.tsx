import { createInertiaApp } from '@inertiajs/react';
import WorkspaceShell from '@/layouts/workspace-shell';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    // Every page renders inside the persistent workspace frame.
    layout: () => WorkspaceShell,
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#b45309',
        delay: 150,
    },
});
