import { Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import Icon from '@/components/ui/icon';
import Pill from '@/components/ui/pill';
import { cn } from '@/lib/utils';
import type { RailThread } from '@/types/workspace';

function subtitle(thread: RailThread): string {
    if (thread.status === 'running') {
        return 'researching';
    }

    if (thread.knowledge_state === 'failed') {
        return 'stalled';
    }

    if (thread.knowledge_state !== 'idle') {
        return thread.knowledge_state;
    }

    return `${thread.facts_count} facts · ${thread.sources_count} sources`;
}

function Dot({ thread }: { thread: RailThread }) {
    return (
        <span className="relative mt-[7px] flex h-1.5 w-1.5 shrink-0">
            {thread.is_busy ? (
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-signal opacity-60" />
            ) : null}
            <span
                className={cn(
                    'relative h-1.5 w-1.5 rounded-full',
                    thread.is_busy
                        ? 'bg-signal'
                        : thread.knowledge_state === 'failed'
                          ? 'bg-red-600'
                          : thread.unread > 0
                            ? 'bg-knowledge'
                            : 'bg-rule',
                )}
            />
        </span>
    );
}

export default function ThreadRail({
    threads,
    activeId,
    collapsed,
    onNew,
}: {
    threads: RailThread[];
    activeId: number | null;
    collapsed: boolean;
    onNew: () => void;
}) {
    const [filter, setFilter] = useState('');

    const visible = useMemo(() => {
        const term = filter.trim().toLowerCase();

        return term === ''
            ? threads
            : threads.filter((thread) =>
                  thread.title.toLowerCase().includes(term),
              );
    }, [threads, filter]);

    if (collapsed) {
        return (
            <nav
                aria-label="Threads"
                className="flex flex-col items-center gap-1.5 border-r border-rule py-2"
            >
                <button
                    type="button"
                    onClick={onNew}
                    aria-label="New thread"
                    className="focus-ring flex h-7 w-7 items-center justify-center rounded text-muted transition-colors hover:bg-panel hover:text-ink"
                >
                    <Icon name="plus" />
                </button>

                {threads.map((thread) => (
                    <Link
                        key={thread.id}
                        href={ThreadController.show(thread.id).url}
                        prefetch="hover"
                        cacheFor="30s"
                        aria-label={thread.title}
                        aria-current={
                            thread.id === activeId ? 'page' : undefined
                        }
                        title={thread.title}
                        className={cn(
                            'focus-ring relative flex h-7 w-7 items-center justify-center rounded font-mono text-[10px] transition-colors',
                            thread.id === activeId
                                ? 'bg-ink text-ground'
                                : 'text-muted hover:bg-panel hover:text-ink',
                        )}
                    >
                        {thread.title
                            .replace(/[^a-z]/gi, '')
                            .slice(0, 2)
                            .toUpperCase()}
                        {thread.is_busy ? (
                            <span className="absolute top-0.5 right-0.5 h-1 w-1 rounded-full bg-signal" />
                        ) : null}
                    </Link>
                ))}
            </nav>
        );
    }

    return (
        <nav
            aria-label="Threads"
            className="flex min-h-0 flex-col border-r border-rule"
        >
            <div className="flex items-center gap-1.5 px-2.5 pt-2.5 pb-2">
                <div className="relative min-w-0 flex-1">
                    <span className="pointer-events-none absolute top-1/2 left-2 -translate-y-1/2 text-muted">
                        <Icon name="search" />
                    </span>
                    <input
                        value={filter}
                        onChange={(event) => setFilter(event.target.value)}
                        placeholder="Filter"
                        aria-label="Filter threads"
                        className="focus-ring h-7 w-full rounded border border-transparent bg-panel pr-2 pl-7 text-xs transition-colors outline-none placeholder:text-muted hover:border-rule focus:border-rule"
                    />
                </div>

                <button
                    type="button"
                    onClick={onNew}
                    title="New thread (⌘N)"
                    aria-label="New thread"
                    className="focus-ring flex h-7 w-7 items-center justify-center rounded border border-rule text-muted transition-colors hover:border-ink hover:text-ink"
                >
                    <Icon name="plus" />
                </button>
            </div>

            <ul className="pane min-h-0 flex-1 px-1.5 pb-3">
                {visible.length === 0 ? (
                    <li className="label px-2 py-3">
                        {threads.length === 0 ? 'No threads yet' : 'No matches'}
                    </li>
                ) : null}

                {visible.map((thread) => {
                    const active = thread.id === activeId;

                    return (
                        <li key={thread.id} className="group relative">
                            <Link
                                href={ThreadController.show(thread.id).url}
                                prefetch="hover"
                                cacheFor="30s"
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'focus-ring relative flex gap-2 rounded py-1.5 pr-7 pl-2.5 transition-colors duration-[--duration-instant]',
                                    active ? 'bg-panel' : 'hover:bg-panel/60',
                                )}
                            >
                                {active ? (
                                    <span
                                        aria-hidden
                                        className="absolute top-1.5 bottom-1.5 left-0 w-0.5 rounded-full bg-ink"
                                    />
                                ) : null}

                                <Dot thread={thread} />

                                <span className="min-w-0 flex-1">
                                    <span className="flex items-baseline gap-1.5">
                                        <span
                                            className={cn(
                                                'min-w-0 flex-1 truncate text-[13px] leading-5',
                                                active
                                                    ? 'text-ink'
                                                    : 'text-ink/85',
                                            )}
                                        >
                                            {thread.title}
                                        </span>
                                        {thread.unread > 0 ? (
                                            <Pill tone="knowledge">
                                                +{thread.unread}
                                            </Pill>
                                        ) : null}
                                    </span>
                                    <span className="label mt-px block truncate">
                                        {subtitle(thread)}
                                    </span>
                                </span>
                            </Link>

                            <button
                                type="button"
                                title={
                                    thread.pinned
                                        ? 'Unpin thread'
                                        : 'Pin to top'
                                }
                                aria-label={
                                    thread.pinned
                                        ? 'Unpin thread'
                                        : 'Pin to top'
                                }
                                onClick={() =>
                                    router.post(
                                        ThreadController.pin(thread.id).url,
                                        {},
                                        {
                                            preserveScroll: true,
                                            only: ['rail'],
                                        },
                                    )
                                }
                                className={cn(
                                    'focus-ring absolute top-1.5 right-1 rounded p-1 transition-opacity',
                                    thread.pinned
                                        ? 'text-knowledge opacity-100'
                                        : 'text-muted opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:text-ink',
                                )}
                            >
                                <Icon name="pin" />
                            </button>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
