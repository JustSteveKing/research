import { Head, router, setLayoutProps, usePoll } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ResearchRunController from '@/actions/App/Http/Controllers/ResearchRunController';
import BudgetGauge from '@/components/budget-gauge';
import StatusDot from '@/components/status-dot';
import ChatPanel from '@/components/thread/chat-panel';
import CostButton from '@/components/thread/cost-button';
import DeleteThreadButton from '@/components/thread/delete-thread-button';
import ExportMenu from '@/components/thread/export-menu';
import Inspector from '@/components/thread/inspector';
import type { InspectorSection } from '@/components/thread/inspector';
import ReadingPane from '@/components/thread/reading-pane';
import SinceStrip from '@/components/thread/since-strip';
import WatchMenu from '@/components/thread/watch-menu';
import Keycap from '@/components/ui/keycap';
import { useHotkeys } from '@/hooks/use-hotkeys';
import type {
    BorrowedFact,
    ChatMessage,
    Contradiction,
    Fact,
    FollowUp,
    RelatedThread,
    Run,
    Source,
    Thread,
} from '@/types/research';
import type { SinceLastOpened } from '@/types/workspace';

export default function ThreadWorkspace({
    thread,
    runs,
    followUps,
    chat,
    sources,
    facts,
    contradictions,
    related,
    borrowed,
    sinceLastOpened,
}: {
    thread: Thread;
    runs: Run[];
    followUps: FollowUp[];
    chat: ChatMessage[];
    sources?: Source[];
    facts?: Fact[];
    contradictions?: Contradiction[];
    related?: RelatedThread[];
    borrowed?: BorrowedFact[];
    sinceLastOpened: SinceLastOpened | null;
}) {
    // A live run takes the reader over until the reader picks something else.
    const streamingRun = runs.find((run) => run.status === 'streaming') ?? null;
    const [chosenMode, setChosenMode] = useState<'brief' | 'run' | null>(null);
    const [chosenRunId, setChosenRunId] = useState<number | null>(null);

    const mode = chosenMode ?? (streamingRun ? 'run' : 'brief');
    const selectedRunId = chosenRunId ?? streamingRun?.id ?? null;
    const [openSections, setOpenSections] = useState<InspectorSection[]>([
        'sources',
        'next',
    ]);
    const [chatOpen, setChatOpen] = useState(false);
    const [inspectorOpen, setInspectorOpen] = useState(
        () => typeof window === 'undefined' || window.innerWidth >= 1024,
    );
    const [focusedSource, setFocusedSource] = useState<string | null>(null);
    const [dismissedSince, setDismissedSince] = useState(false);

    const runCost = thread.mode === 'balanced' ? 350 : 250;
    const working = thread.is_running || thread.knowledge_state !== 'idle';

    setLayoutProps({
        threadId: thread.id,
        title: thread.title,
        commandContext: {
            isRunning: thread.is_running,
            autoFollowUp: thread.auto_follow_up,
            availableCredits: thread.available_credits,
            runCost,
        },
    });

    usePoll(
        2000,
        { only: ['thread', 'runs', 'followUps', 'rail', 'activity'] },
        { autoStart: working, mode: 'rest' },
    );

    useHotkeys(
        useMemo(
            () => ({
                'mod+j': () => setChatOpen((open) => !open),
                'mod+\\': () => setInspectorOpen((open) => !open),
                'mod+1': () => toggleSection('sources', true),
                'mod+2': () => toggleSection('facts', true),
                'mod+3': () => toggleSection('disputed', true),
                'mod+4': () => toggleSection('related', true),
                'mod+5': () => toggleSection('timeline', true),
                'mod+6': () => toggleSection('next', true),
                'mod+r': () =>
                    router.post(
                        `/threads/${thread.id}/rebuild`,
                        {},
                        { preserveScroll: true },
                    ),
            }),
            [thread.id],
        ),
    );

    // The highlight clears itself; the timer lives with the click, not an effect.
    const peekSource = (url: string) => {
        setFocusedSource(url);
        setChosenRunId(chosenRunId);
        window.setTimeout(() => setFocusedSource(null), 2400);
    };

    const toggleSection = (section: InspectorSection, forceOpen = false) => {
        setOpenSections((current) =>
            current.includes(section) && !forceOpen
                ? current.filter((item) => item !== section)
                : current.includes(section)
                  ? current
                  : [...current, section],
        );
    };

    return (
        <div className="grid min-h-0 grid-rows-[auto_minmax(0,1fr)_auto]">
            <Head title={thread.title} />

            {sinceLastOpened && !dismissedSince ? (
                <SinceStrip
                    since={sinceLastOpened}
                    onDismiss={() => setDismissedSince(true)}
                />
            ) : (
                <div />
            )}

            <div
                className="grid min-h-0"
                style={{
                    gridTemplateColumns: 'minmax(0, 1fr) var(--inspector-w)',
                }}
            >
                <div className="grid min-h-0 grid-rows-[minmax(0,1fr)_auto]">
                    <ReadingPane
                        thread={thread}
                        runs={runs}
                        mode={mode}
                        onModeChange={setChosenMode}
                        selectedRunId={selectedRunId}
                        onSourceClick={peekSource}
                    />

                    <div className={chatOpen ? 'h-[38vh]' : ''}>
                        <ChatPanel
                            threadId={thread.id}
                            messages={chat}
                            expanded={chatOpen}
                            onToggle={setChatOpen}
                        />
                    </div>
                </div>

                {inspectorOpen ? (
                    <Inspector
                        thread={thread}
                        runs={runs}
                        followUps={followUps}
                        sources={sources}
                        facts={facts}
                        contradictions={contradictions}
                        related={related}
                        borrowed={borrowed}
                        open={openSections}
                        onToggle={(section) => toggleSection(section)}
                        focusedSourceUrl={focusedSource}
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setInspectorOpen(true)}
                        title="Show inspector (⌘\\)"
                        className="focus-ring label absolute top-2 right-2 flex items-center gap-1.5 rounded border border-rule bg-panel px-2 py-1 transition-colors hover:border-ink hover:text-ink"
                    >
                        Inspector
                        <Keycap keys="⌘\\" />
                    </button>
                )}
            </div>

            <footer className="flex h-12 items-center gap-4 border-t border-rule bg-panel/60 px-4">
                <StatusDot
                    status={thread.status}
                    label={
                        thread.knowledge_state === 'idle'
                            ? thread.status
                            : thread.knowledge_state_label
                    }
                />

                <div className="w-44 shrink-0">
                    <BudgetGauge
                        budget={thread.credit_budget}
                        spent={thread.credits_spent}
                        reserved={thread.credits_reserved}
                        costPerRun={runCost}
                        compact
                    />
                </div>

                <form
                    className="ml-auto flex items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();

                        const input = event.currentTarget.elements.namedItem(
                            'query',
                        ) as HTMLInputElement;

                        if (input.value.trim().length < 5) {
                            return;
                        }

                        router.post(
                            ResearchRunController.store(thread.id).url,
                            { query: input.value },
                            {
                                preserveScroll: true,
                                onSuccess: () => (input.value = ''),
                            },
                        );
                    }}
                >
                    <input
                        id="thread-query-input"
                        name="query"
                        placeholder="Research a specific angle…"
                        className="focus-ring h-7 w-72 rounded border border-rule bg-ground px-3 text-[13px] outline-none"
                    />
                    <CostButton
                        type="submit"
                        cost={runCost}
                        affordable={thread.available_credits >= runCost}
                        available={thread.available_credits}
                    >
                        Run
                    </CostButton>
                </form>

                <span aria-hidden className="h-4 w-px bg-rule" />

                <WatchMenu
                    threadId={thread.id}
                    cadence={thread.watch_cadence}
                    runCost={runCost}
                />

                <ExportMenu threadId={thread.id} />

                <DeleteThreadButton
                    threadId={thread.id}
                    summary={`${runs.length} runs · ${thread.sources_count} sources · ${thread.facts_count} facts`}
                />
            </footer>
        </div>
    );
}
