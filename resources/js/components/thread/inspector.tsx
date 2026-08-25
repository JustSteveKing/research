import { Deferred } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import RunTimeline from '@/components/run-timeline';
import Skeleton from '@/components/skeleton';
import DisputeList from '@/components/thread/dispute-list';
import FactList from '@/components/thread/fact-list';
import FollowUpQueue from '@/components/thread/follow-up-queue';
import RelatedThreads from '@/components/thread/related-threads';
import SourceList from '@/components/thread/source-list';
import EmptyState from '@/components/ui/empty-state';
import Icon from '@/components/ui/icon';
import Pill from '@/components/ui/pill';
import { cn } from '@/lib/utils';
import type {
    BorrowedFact,
    Contradiction,
    Fact,
    FollowUp,
    RelatedThread,
    Run,
    Source,
    Thread,
} from '@/types/research';

export type InspectorSection =
    'sources' | 'facts' | 'disputed' | 'related' | 'timeline' | 'next';

const sections: { id: InspectorSection; label: string; key: string }[] = [
    { id: 'sources', label: 'Sources', key: '⌘1' },
    { id: 'facts', label: 'Facts', key: '⌘2' },
    { id: 'disputed', label: 'Disputed', key: '⌘3' },
    { id: 'related', label: 'Related', key: '⌘4' },
    { id: 'timeline', label: 'Timeline', key: '⌘5' },
    { id: 'next', label: 'Up next', key: '⌘6' },
];

/**
 * The reference column: everything you cross-check while reading, in one
 * scroll, so nothing needs a tab switch.
 */
export default function Inspector({
    thread,
    runs,
    followUps,
    sources,
    facts,
    contradictions,
    related,
    borrowed,
    open,
    onToggle,
    focusedSourceUrl,
}: {
    thread: Thread;
    runs: Run[];
    followUps: FollowUp[];
    sources?: Source[];
    facts?: Fact[];
    contradictions?: Contradiction[];
    related?: RelatedThread[];
    borrowed?: BorrowedFact[];
    open: InspectorSection[];
    onToggle: (section: InspectorSection) => void;
    focusedSourceUrl: string | null;
}) {
    const sourcesRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (focusedSourceUrl) {
            sourcesRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        }
    }, [focusedSourceUrl]);

    const activeRun =
        runs.find((run) => run.status === 'streaming') ?? runs.at(-1) ?? null;

    const counts: Record<InspectorSection, number | null> = {
        sources: thread.sources_count,
        facts: thread.facts_count,
        disputed: contradictions?.length ?? null,
        related: related?.length ?? null,
        timeline: runs.length,
        next: followUps.length,
    };

    return (
        <aside
            aria-label="Thread inspector"
            className="pane border-l border-rule bg-panel/40"
        >
            {sections.map((section) => {
                const isOpen = open.includes(section.id);

                return (
                    <section
                        key={section.id}
                        ref={section.id === 'sources' ? sourcesRef : undefined}
                        className="border-b border-rule"
                    >
                        <h2 className="sticky-head">
                            <button
                                type="button"
                                onClick={() => onToggle(section.id)}
                                aria-expanded={isOpen}
                                title={`${section.label} (${section.key})`}
                                className="focus-ring group flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-panel"
                            >
                                <Icon
                                    name="chevron"
                                    className={cn(
                                        'text-muted transition-transform duration-[--duration-instant]',
                                        isOpen && 'rotate-90',
                                    )}
                                />
                                <span className="label text-ink">
                                    {section.label}
                                </span>
                                <span className="ml-auto flex items-center gap-2">
                                    <Pill tone={toneFor(section.id, counts)}>
                                        {counts[section.id]}
                                    </Pill>
                                    <span className="label opacity-0 transition-opacity group-hover:opacity-100">
                                        {section.key}
                                    </span>
                                </span>
                            </button>
                        </h2>

                        {isOpen ? (
                            <div className="rise-in px-3 pt-1 pb-4">
                                {section.id === 'sources' ? (
                                    <Deferred
                                        data="sources"
                                        fallback={<Skeleton rows={3} />}
                                    >
                                        <SourceList
                                            sources={sources ?? []}
                                            highlightedUrl={focusedSourceUrl}
                                        />
                                    </Deferred>
                                ) : null}

                                {section.id === 'facts' ? (
                                    <Deferred
                                        data="facts"
                                        fallback={<Skeleton rows={4} />}
                                    >
                                        <FactList
                                            threadId={thread.id}
                                            facts={facts ?? []}
                                            contradictions={contradictions}
                                        />
                                    </Deferred>
                                ) : null}

                                {section.id === 'disputed' ? (
                                    <Deferred
                                        data="contradictions"
                                        fallback={<Skeleton rows={2} />}
                                    >
                                        <DisputeList
                                            threadId={thread.id}
                                            contradictions={
                                                contradictions ?? []
                                            }
                                        />
                                    </Deferred>
                                ) : null}

                                {section.id === 'related' ? (
                                    <Deferred
                                        data={['related', 'borrowed']}
                                        fallback={<Skeleton rows={3} />}
                                    >
                                        <RelatedThreads
                                            related={related ?? []}
                                            borrowed={borrowed ?? []}
                                            hasBrief={thread.brief !== null}
                                        />
                                    </Deferred>
                                ) : null}

                                {section.id === 'timeline' ? (
                                    runs.length === 0 ? (
                                        <EmptyState
                                            title="No runs yet"
                                            body="Each research run records the phases it moved through."
                                        />
                                    ) : (
                                        <RunTimeline
                                            events={activeRun?.events ?? []}
                                            live={
                                                activeRun?.status ===
                                                'streaming'
                                            }
                                        />
                                    )
                                ) : null}

                                {section.id === 'next' ? (
                                    <FollowUpQueue
                                        threadId={thread.id}
                                        followUps={followUps}
                                    />
                                ) : null}
                            </div>
                        ) : null}
                    </section>
                );
            })}

            {thread.knowledge_error ? (
                <div
                    className={cn(
                        'm-4 rounded border border-signal/40 bg-signal/5 p-3',
                    )}
                >
                    <p className="label text-signal">Distillation stopped</p>
                    <p className="mt-1 text-xs">{thread.knowledge_error}</p>
                </div>
            ) : null}
        </aside>
    );
}

/** Sections earn a colour only when they are holding something. */
function toneFor(
    section: InspectorSection,
    counts: Record<InspectorSection, number | null>,
): 'knowledge' | 'danger' | 'neutral' {
    if (section === 'disputed' && (counts.disputed ?? 0) > 0) {
        return 'danger';
    }

    if (
        (section === 'facts' || section === 'related') &&
        (counts[section] ?? 0) > 0
    ) {
        return 'knowledge';
    }

    return 'neutral';
}
