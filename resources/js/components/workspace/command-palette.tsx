import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import SettingsController from '@/actions/App/Http/Controllers/SettingsController';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import Icon from '@/components/ui/icon';
import Keycap from '@/components/ui/keycap';
import Overlay from '@/components/workspace/overlay';
import { cn } from '@/lib/utils';
import type { RailThread, SearchResults } from '@/types/workspace';

interface Command {
    id: string;
    label: string;
    hint?: string;
    cost?: number;
    disabled?: string;
    run: () => void;
}

interface Row {
    section: string;
    key: string;
    label: string;
    hint?: string;
    cost?: number;
    disabled?: string;
    run: () => void;
}

const empty: SearchResults = {
    threads: [],
    facts: [],
    sources: [],
    semantic: false,
};

export default function CommandPalette({
    open,
    onClose,
    threads,
    context,
    onNewThread,
}: {
    open: boolean;
    onClose: () => void;
    threads: RailThread[];
    context: {
        threadId: number | null;
        isRunning: boolean;
        autoFollowUp: boolean;
        availableCredits: number;
        runCost: number;
    };
    onNewThread: () => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResults>(empty);
    const [active, setActive] = useState(0);
    const listRef = useRef<HTMLUListElement>(null);

    useEffect(() => {
        const term = query.trim();

        if (term.length < 2) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch(`/search?q=${encodeURIComponent(term)}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json())
                .then((data: SearchResults) => setResults(data))
                .catch(() => undefined);
        }, 120);

        return () => {
            controller.abort();
            clearTimeout(timer);
        };
    }, [query]);

    const commands = useMemo<Command[]>(() => {
        const list: Command[] = [
            { id: 'new', label: 'New thread', hint: '⌘N', run: onNewThread },
            {
                id: 'settings',
                label: 'Open settings',
                hint: '⌘,',
                run: () => router.visit(SettingsController.edit().url),
            },
        ];

        if (context.threadId) {
            const id = context.threadId;
            const unaffordable = context.availableCredits < context.runCost;

            list.unshift({
                id: 'rebuild',
                label: 'Rebuild knowledge from stored reports',
                hint: 'no credits',
                run: () =>
                    router.post(
                        ThreadController.rebuild(id).url,
                        {},
                        { preserveScroll: true },
                    ),
            });

            list.push({
                id: 'autopilot',
                label: context.autoFollowUp
                    ? 'Pause autopilot'
                    : 'Resume autopilot',
                run: () =>
                    router.patch(
                        ThreadController.update(id).url,
                        { auto_follow_up: !context.autoFollowUp },
                        { preserveScroll: true },
                    ),
            });

            list.push({
                id: 'pin',
                label: 'Pin or unpin this thread',
                run: () =>
                    router.post(
                        ThreadController.pin(id).url,
                        {},
                        { preserveScroll: true },
                    ),
            });

            list.push({
                id: 'spend',
                label: 'Research a follow-up question here',
                cost: context.runCost,
                disabled: unaffordable
                    ? `needs ${context.runCost}, ${context.availableCredits} left`
                    : undefined,
                run: () => {
                    onClose();
                    document.getElementById('thread-query-input')?.focus();
                },
            });
        }

        return list;
    }, [context, onClose, onNewThread]);

    const rows = useMemo<Row[]>(() => {
        const term = query.trim().toLowerCase();
        const matches = term.length < 2 ? empty : results;

        const commandRows: Row[] = commands
            .filter(
                (command) =>
                    term === '' || command.label.toLowerCase().includes(term),
            )
            .map((command) => ({
                section: 'Actions',
                key: `c-${command.id}`,
                ...command,
            }));

        const threadRows: Row[] = (
            term === ''
                ? threads.slice(0, 5)
                : matches.threads.length > 0
                  ? matches.threads
                  : threads.filter((thread) =>
                        thread.title.toLowerCase().includes(term),
                    )
        ).map((thread) => ({
            section: 'Threads',
            key: `t-${thread.id}`,
            label: thread.title,
            run: () => router.visit(ThreadController.show(thread.id).url),
        }));

        const factRows: Row[] = matches.facts.map((fact) => ({
            // Named differently when the match was by meaning, so a loose hit
            // is not read as an exact one.
            section: matches.semantic ? 'Facts · by meaning' : 'Facts',
            key: `f-${fact.id}`,
            label: fact.statement,
            hint: fact.thread_title,
            run: () =>
                router.visit(ThreadController.show(fact.thread_id).url, {
                    onSuccess: () =>
                        window.dispatchEvent(
                            new CustomEvent('inspector:facts'),
                        ),
                }),
        }));

        const sourceRows: Row[] = matches.sources.map((source) => ({
            section: 'Sources',
            key: `s-${source.id}`,
            label: source.title ?? source.url,
            hint: source.domain ?? undefined,
            run: () =>
                router.visit(ThreadController.show(source.thread_id).url),
        }));

        return [...commandRows, ...threadRows, ...factRows, ...sourceRows];
    }, [commands, query, results, threads]);

    // Keep the cursor inside the list as results change, without an effect.
    const activeIndex = Math.min(active, Math.max(0, rows.length - 1));

    const select = (row: Row) => {
        if (row.disabled) {
            return;
        }

        onClose();
        row.run();
    };

    let lastSection = '';

    return (
        <Overlay
            open={open}
            onClose={onClose}
            label="Command palette"
            align="top"
        >
            <div className="-m-5">
                <div className="flex items-center gap-2 border-b border-rule px-4">
                    <Icon name="search" className="text-muted" />
                    <input
                        autoFocus
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'ArrowDown') {
                                event.preventDefault();
                                setActive(
                                    Math.min(activeIndex + 1, rows.length - 1),
                                );
                            }

                            if (event.key === 'ArrowUp') {
                                event.preventDefault();
                                setActive(Math.max(activeIndex - 1, 0));
                            }

                            if (event.key === 'Enter' && rows[activeIndex]) {
                                event.preventDefault();
                                select(rows[activeIndex]);
                            }
                        }}
                        role="combobox"
                        aria-expanded
                        aria-controls="palette-results"
                        aria-activedescendant={
                            rows[activeIndex]
                                ? `row-${rows[activeIndex].key}`
                                : undefined
                        }
                        placeholder="Search threads, facts and sources, or run a command"
                        className="w-full bg-transparent py-3 text-sm outline-none placeholder:text-muted"
                    />
                    <Keycap keys="esc" />
                </div>

                <ul
                    id="palette-results"
                    ref={listRef}
                    role="listbox"
                    className="pane max-h-[52vh] py-1.5"
                >
                    {rows.length === 0 ? (
                        <li className="px-4 py-6">
                            <p className="text-sm">
                                Nothing stored about “{query}”
                            </p>
                            <button
                                type="button"
                                onClick={() => {
                                    onClose();
                                    onNewThread();
                                }}
                                className="focus-ring mt-1 rounded text-xs text-knowledge underline underline-offset-2"
                            >
                                Open a thread to research it
                            </button>
                        </li>
                    ) : null}

                    {rows.map((row, index) => {
                        const header =
                            row.section !== lastSection ? row.section : null;
                        lastSection = row.section;

                        return (
                            <li key={row.key}>
                                {header ? (
                                    <p className="label px-4 pt-3 pb-1">
                                        {header}
                                    </p>
                                ) : null}

                                <button
                                    id={`row-${row.key}`}
                                    role="option"
                                    aria-selected={index === activeIndex}
                                    type="button"
                                    disabled={Boolean(row.disabled)}
                                    onMouseEnter={() => setActive(index)}
                                    onClick={() => select(row)}
                                    className={cn(
                                        'flex w-full items-baseline gap-3 px-4 py-1.5 text-left text-sm transition',
                                        index === activeIndex && 'bg-ground',
                                        row.disabled && 'opacity-50',
                                    )}
                                >
                                    <span className="min-w-0 flex-1 truncate">
                                        {row.label}
                                    </span>
                                    {row.cost ? (
                                        <span className="label text-signal">
                                            {row.cost} cr
                                        </span>
                                    ) : null}
                                    {row.disabled ? (
                                        <span className="label">
                                            {row.disabled}
                                        </span>
                                    ) : row.hint ? (
                                        <span className="label">
                                            {row.hint}
                                        </span>
                                    ) : null}
                                </button>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </Overlay>
    );
}
