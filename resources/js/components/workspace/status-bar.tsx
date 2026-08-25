import { Link } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import Icon from '@/components/ui/icon';
import { cn } from '@/lib/utils';
import type { ActivitySnapshot } from '@/types/workspace';

function Divider() {
    return <span aria-hidden className="h-3 w-px bg-rule" />;
}

/**
 * The single place that tells the truth about background work, on every screen.
 */
export default function StatusBar({
    activity,
}: {
    activity: ActivitySnapshot;
}) {
    const run = activity.active_run;
    const remaining = Math.max(
        0,
        activity.credits_budgeted - activity.credits_spent,
    );

    const segment =
        'label focus-ring flex h-full items-center gap-1.5 rounded px-2 transition-colors hover:text-ink';

    return (
        <footer
            aria-label="Background activity"
            className="flex h-[var(--statusbar-h)] items-center gap-1 border-t border-rule bg-panel px-1.5"
        >
            <span aria-live="polite" className="flex h-full items-center">
                {run ? (
                    <Link
                        href={ThreadController.show(run.thread_id).url}
                        title="Open the running thread"
                        className={cn(segment, 'text-signal hover:text-signal')}
                    >
                        <span className="relative flex h-1.5 w-1.5">
                            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-signal opacity-60" />
                            <span className="relative h-1.5 w-1.5 rounded-full bg-signal" />
                        </span>
                        {run.phase ?? run.status}
                    </Link>
                ) : (
                    <span className={cn(segment, 'hover:text-muted')}>
                        <span className="h-1.5 w-1.5 rounded-full bg-rule" />
                        idle
                    </span>
                )}
            </span>

            {activity.knowledge_pending > 0 ? (
                <>
                    <Divider />
                    <span
                        className={cn(
                            segment,
                            'text-knowledge hover:text-knowledge',
                        )}
                        title="Jobs waiting for the local model"
                    >
                        <Icon name="spark" className="h-3 w-3" />
                        <span className="tnum">
                            {activity.knowledge_pending}
                        </span>{' '}
                        distilling
                    </span>
                </>
            ) : null}

            <span className="ml-auto flex h-full items-center gap-1">
                <Link
                    href={SettingsController.edit().url}
                    title="Local model status"
                    className={cn(
                        segment,
                        !activity.ollama_reachable &&
                            'text-red-700 dark:text-red-400',
                    )}
                >
                    {activity.ollama_reachable ? null : (
                        <Icon name="warning" className="h-3 w-3" />
                    )}
                    ollama {activity.ollama_reachable ? 'ok' : 'offline'}
                </Link>

                <Divider />

                <span
                    className={cn(segment, 'hover:text-muted')}
                    title="Across all threads"
                >
                    <span className="tnum text-ink">
                        {activity.credits_spent.toLocaleString()}
                    </span>
                    spent
                    <span className="text-rule">/</span>
                    <span className="tnum">
                        {remaining.toLocaleString()}
                    </span>{' '}
                    left
                </span>
            </span>
        </footer>
    );
}
