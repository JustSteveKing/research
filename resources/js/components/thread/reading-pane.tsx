import { Form } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import ResearchRunController from '@/actions/App/Http/Controllers/ResearchRunController';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import Markdown from '@/components/markdown';
import RunTimeline from '@/components/run-timeline';
import Skeleton from '@/components/skeleton';
import CoverageMap from '@/components/thread/coverage-map';
import WatchReport from '@/components/thread/watch-report';
import Button from '@/components/ui/button';
import EmptyState from '@/components/ui/empty-state';
import Icon from '@/components/ui/icon';
import Pill from '@/components/ui/pill';
import { useElapsed } from '@/hooks/use-elapsed';
import { cn } from '@/lib/utils';
import type { Run, Thread } from '@/types/research';

type Mode = 'brief' | 'run';

/**
 * One thing at a time: the living brief, or one run's report and live tape.
 * Owns its own scroll so the shell never moves.
 */
export default function ReadingPane({
    thread,
    runs,
    mode,
    onModeChange,
    selectedRunId,
    onSourceClick,
}: {
    thread: Thread;
    runs: Run[];
    mode: Mode;
    onModeChange: (mode: Mode) => void;
    selectedRunId: number | null;
    onSourceClick: (url: string) => void;
}) {
    const run =
        runs.find((item) => item.id === selectedRunId) ?? runs.at(-1) ?? null;
    const runCost = thread.mode === 'balanced' ? 350 : 250;
    const live = run?.status === 'streaming';
    const scroller = useRef<HTMLDivElement>(null);
    const [pinnedToBottom, setPinnedToBottom] = useState(true);
    const [scrolled, setScrolled] = useState(false);
    const elapsed = useElapsed(live ? run?.started_at : null);

    // Follow the tape only while the reader is already at the bottom.
    useEffect(() => {
        if (!live || !pinnedToBottom || !scroller.current) {
            return;
        }

        scroller.current.scrollTop = scroller.current.scrollHeight;
    }, [live, pinnedToBottom, run?.events.length]);

    const briefStale =
        thread.brief !== null &&
        runs.some(
            (item) =>
                item.status === 'completed' &&
                item.completed_at !== null &&
                thread.brief_generated_at !== null &&
                new Date(item.completed_at) >
                    new Date(thread.brief_generated_at),
        );

    return (
        <section className="flex min-h-0 min-w-0 flex-col">
            <header
                className={cn(
                    'flex h-9 items-center gap-3 border-b px-4 transition-colors duration-[--duration-fast]',
                    scrolled
                        ? 'border-rule bg-panel/70 backdrop-blur'
                        : 'border-rule',
                )}
            >
                <div
                    className="flex rounded border border-rule p-px"
                    role="tablist"
                    aria-label="Reading mode"
                >
                    {(['brief', 'run'] as Mode[]).map((option) => (
                        <button
                            key={option}
                            role="tab"
                            aria-selected={mode === option}
                            type="button"
                            onClick={() => onModeChange(option)}
                            className={cn(
                                'focus-ring label rounded-[3px] px-2 py-0.5 capitalize transition-colors duration-[--duration-instant]',
                                mode === option
                                    ? 'bg-ink text-ground'
                                    : 'hover:text-ink',
                            )}
                        >
                            {option === 'run' && run
                                ? `Run · depth ${run.depth}`
                                : option}
                        </button>
                    ))}
                </div>

                <div className="ml-auto flex min-w-0 items-center gap-2">
                    {mode === 'brief' && briefStale ? (
                        <Pill tone="signal">stale</Pill>
                    ) : null}

                    {elapsed ? (
                        <span className="label tnum text-signal">
                            {elapsed}
                        </span>
                    ) : null}

                    <span className="label tnum truncate">
                        {mode === 'brief'
                            ? thread.brief_generated_at
                                ? `updated ${new Date(thread.brief_generated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
                                : 'not written yet'
                            : (run?.phase ?? run?.status)}
                    </span>
                </div>
            </header>

            <div
                ref={scroller}
                onScroll={(event) => {
                    const element = event.currentTarget;

                    setPinnedToBottom(
                        element.scrollHeight -
                            element.scrollTop -
                            element.clientHeight <
                            40,
                    );
                    setScrolled(element.scrollTop > 4);
                }}
                className="pane min-h-0 flex-1 px-5 py-5"
            >
                <div className="mx-auto max-w-[72ch]">
                    {mode === 'brief' ? (
                        thread.brief ? (
                            <>
                                {thread.last_watch_report ? (
                                    <WatchReport
                                        report={thread.last_watch_report}
                                    />
                                ) : null}
                                <CoverageMap
                                    threadId={thread.id}
                                    coverage={thread.coverage}
                                    canSpend={
                                        thread.available_credits >= runCost
                                    }
                                    runCost={runCost}
                                />
                                <Markdown onSourceClick={onSourceClick}>
                                    {thread.brief}
                                </Markdown>
                            </>
                        ) : thread.is_busy ? (
                            <Skeleton rows={6} />
                        ) : (
                            <EmptyState
                                title="No brief yet"
                                body="The brief is written locally from the facts distilled out of each report, and rewrites itself as the thread grows."
                                action={
                                    <Form
                                        action={ThreadController.rebuild(
                                            thread.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        <Button
                                            type="submit"
                                            size="xs"
                                            icon="refresh"
                                            mono
                                        >
                                            Rebuild from stored reports
                                        </Button>
                                    </Form>
                                }
                            />
                        )
                    ) : null}

                    {mode === 'run' && run ? (
                        <>
                            <h2 className="text-[15px] leading-6 font-medium tracking-tight text-pretty">
                                {run.query}
                            </h2>
                            <p className="label tnum mt-1.5">
                                {run.mode} · {run.credits_cost} cr
                                {run.pages_analyzed
                                    ? ` · ${run.pages_analyzed} pages`
                                    : ''}
                            </p>

                            {run.error ? (
                                <div className="mt-4 rounded border border-red-600/40 bg-red-600/5 p-3">
                                    <p className="label flex items-center gap-1.5 text-red-700 dark:text-red-400">
                                        <Icon
                                            name="warning"
                                            className="h-3 w-3"
                                        />
                                        Run failed
                                    </p>
                                    <p className="mt-1.5 text-xs leading-relaxed">
                                        {run.error}
                                    </p>
                                    <Form
                                        action={ResearchRunController.store(
                                            thread.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        className="mt-2"
                                    >
                                        <input
                                            type="hidden"
                                            name="query"
                                            value={run.query}
                                        />
                                        <Button
                                            type="submit"
                                            size="xs"
                                            icon="refresh"
                                            mono
                                        >
                                            Retry · {run.credits_cost} cr
                                        </Button>
                                    </Form>
                                </div>
                            ) : null}

                            {run.report ? (
                                <Markdown
                                    className="mt-5"
                                    onSourceClick={onSourceClick}
                                >
                                    {run.report}
                                </Markdown>
                            ) : (
                                <div className="mt-5">
                                    <RunTimeline
                                        events={run.events}
                                        live={live}
                                    />
                                </div>
                            )}
                        </>
                    ) : null}

                    {mode === 'run' && !run ? (
                        <EmptyState
                            title="No runs yet"
                            body="Ask something below and the first run starts immediately."
                        />
                    ) : null}
                </div>
            </div>

            {live && !pinnedToBottom ? (
                <button
                    type="button"
                    onClick={() => {
                        setPinnedToBottom(true);

                        if (scroller.current) {
                            scroller.current.scrollTop =
                                scroller.current.scrollHeight;
                        }
                    }}
                    className="focus-ring rise-in label mx-auto mb-3 flex items-center gap-1.5 rounded-full border border-signal/50 bg-panel px-3 py-1 text-signal shadow-[0_4px_12px_-4px_rgb(0_0_0/0.2)]"
                >
                    ↓ new activity
                </button>
            ) : null}
        </section>
    );
}
