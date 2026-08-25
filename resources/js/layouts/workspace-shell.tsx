import { router, usePage, usePoll } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCallback, useEffect, useState } from 'react';
import Icon from '@/components/ui/icon';
import CommandPalette from '@/components/workspace/command-palette';
import FirstRun from '@/components/workspace/first-run';
import ShortcutsSheet from '@/components/workspace/shortcuts-sheet';
import StatusBar from '@/components/workspace/status-bar';
import ThreadComposer from '@/components/workspace/thread-composer';
import ThreadRail from '@/components/workspace/thread-rail';
import type { ToastPayload } from '@/components/workspace/toaster';
import Toaster from '@/components/workspace/toaster';
import { useHotkeys } from '@/hooks/use-hotkeys';
import type {
    ThreadComposerDefaults,
    WorkspaceSharedProps,
} from '@/types/workspace';

export interface WorkspaceLayoutProps {
    /** Thread currently open in the workspace, if any. */
    threadId?: number | null;
    /** Window title shown in the drag bar. */
    title?: string;
    /** Right-hand side of the title bar. */
    titleAside?: React.ReactNode;
    /** Palette context for thread-scoped commands. */
    commandContext?: {
        isRunning: boolean;
        autoFollowUp: boolean;
        availableCredits: number;
        runCost: number;
    };
}

/**
 * The app frame: rail, title bar, status bar, palette and composer. It is a
 * persistent Inertia layout, so it survives navigation and keeps its state.
 */
export default function WorkspaceShell({
    children,
    threadId = null,
    title,
    titleAside,
    commandContext,
}: PropsWithChildren<WorkspaceLayoutProps>) {
    // Pages feed the shell through setLayoutProps, which Inertia merges into
    // this component's props.
    const page = usePage<
        WorkspaceSharedProps & { defaults?: ThreadComposerDefaults } & Record<
                string,
                unknown
            >
    >();
    const { rail, activity, setup } = page.props;

    const activeThreadId = threadId;
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [composerOpen, setComposerOpen] = useState(false);
    const [shortcutsOpen, setShortcutsOpen] = useState(false);
    const [railCollapsed, setRailCollapsed] = useState(
        () => typeof window !== 'undefined' && window.innerWidth < 900,
    );

    useEffect(() => {
        const onResize = () => setRailCollapsed(window.innerWidth < 900);

        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    // The shell keeps its own heartbeat so background truth stays current on
    // every screen, including settings.
    usePoll(
        5000,
        { only: ['rail', 'activity'] },
        { keepAlive: true, mode: 'rest' },
    );

    const openComposer = useCallback(() => setComposerOpen(true), []);

    useHotkeys({
        '!mod+k': () => setPaletteOpen((open) => !open),
        'mod+n': openComposer,
        'mod+b': () => setRailCollapsed((collapsed) => !collapsed),
        'mod+,': () => router.visit('/settings'),
        'shift+?': () => setShortcutsOpen((open) => !open),
    });

    const defaults: ThreadComposerDefaults = page.props.defaults ?? {
        mode: 'fast',
        credit_budget: 2000,
        max_depth: 3,
        costs: { fast: 250, balanced: 350 },
    };

    return (
        <div
            className="grid h-screen overflow-hidden"
            style={{
                gridTemplateColumns: `${railCollapsed ? 'var(--rail-collapsed-w)' : 'var(--rail-w)'} minmax(0, 1fr)`,
                gridTemplateRows:
                    'var(--titlebar-h) minmax(0, 1fr) var(--statusbar-h)',
            }}
        >
            <header className="drag-region col-span-2 flex items-center gap-2 border-b border-rule bg-panel pr-2 pl-[86px]">
                <span className="label shrink-0">research</span>
                <span aria-hidden className="text-rule">
                    /
                </span>
                <span className="truncate text-[13px] font-medium">
                    {title ?? 'Threads'}
                </span>

                {activity.active_run &&
                activity.active_run.thread_id === activeThreadId ? (
                    <span className="label flex shrink-0 items-center gap-1.5 text-signal">
                        <span className="relative flex h-1.5 w-1.5">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-signal opacity-60" />
                            <span className="relative h-1.5 w-1.5 rounded-full bg-signal" />
                        </span>
                        {activity.active_run.phase ?? 'running'}
                    </span>
                ) : null}

                <div className="no-drag ml-auto flex items-center gap-2">
                    {titleAside}

                    <button
                        type="button"
                        onClick={() => setPaletteOpen(true)}
                        title="Search and commands"
                        className="focus-ring flex h-6 items-center gap-2 rounded border border-rule px-2 text-muted transition-colors hover:border-ink hover:text-ink"
                    >
                        <Icon name="search" className="h-3 w-3" />
                        <span className="font-mono text-[10px]">⌘K</span>
                    </button>
                </div>
            </header>

            <ThreadRail
                threads={rail}
                activeId={activeThreadId}
                collapsed={railCollapsed}
                onNew={openComposer}
            />

            <main className="grid min-h-0 min-w-0">{children}</main>

            <div className="col-span-2">
                <StatusBar activity={activity} />
            </div>

            {paletteOpen ? (
                <CommandPalette
                    open
                    onClose={() => setPaletteOpen(false)}
                    threads={rail}
                    onNewThread={openComposer}
                    context={{
                        threadId: activeThreadId,
                        isRunning: commandContext?.isRunning ?? false,
                        autoFollowUp: commandContext?.autoFollowUp ?? false,
                        availableCredits: commandContext?.availableCredits ?? 0,
                        runCost: commandContext?.runCost ?? 250,
                    }}
                />
            ) : null}

            <ShortcutsSheet
                open={shortcutsOpen}
                onClose={() => setShortcutsOpen(false)}
            />

            <Toaster
                toast={
                    (page.props.toast as ToastPayload | null | undefined) ??
                    null
                }
            />

            <ThreadComposer
                open={composerOpen}
                onClose={() => setComposerOpen(false)}
                defaults={defaults}
            />

            <FirstRun open={setup?.needs_tabstack_key ?? false} />
        </div>
    );
}
