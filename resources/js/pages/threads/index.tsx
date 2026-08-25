import { Head, Link, setLayoutProps } from '@inertiajs/react';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import BudgetGauge from '@/components/budget-gauge';
import StatusDot from '@/components/status-dot';
import DeleteThreadButton from '@/components/thread/delete-thread-button';
import Keycap from '@/components/ui/keycap';
import type { ThreadSummary } from '@/types/research';
import type { ThreadComposerDefaults } from '@/types/workspace';

/**
 * Overview pane. Thread switching lives in the rail, so this screen exists for
 * comparing threads and re-entering old work.
 */
export default function ThreadsOverview({
    threads,
    defaults,
}: {
    threads: ThreadSummary[];
    defaults: ThreadComposerDefaults;
}) {
    setLayoutProps({ threadId: null, title: 'Research' });

    return (
        <div className="pane">
            <Head title="Threads" />

            <div className="mx-auto max-w-4xl px-6 py-8">
                {threads.length === 0 ? (
                    <div className="rounded-md border border-dashed border-rule px-6 py-8">
                        <h1 className="text-[15px] font-medium tracking-tight">
                            Nothing researched yet
                        </h1>
                        <p className="mt-2 max-w-prose text-sm leading-relaxed text-muted">
                            Open a thread with one question. Each run pulls live
                            sources, distils them locally, and proposes what to
                            ask next until the credit budget runs out.
                        </p>
                        <p className="label mt-5 flex items-center gap-1.5">
                            Press <Keycap keys="⌘N" /> to start
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-2">
                        {threads.map((thread) => (
                            <li key={thread.id} className="group relative">
                                <Link
                                    href={ThreadController.show(thread.id).url}
                                    prefetch="hover"
                                    cacheFor="30s"
                                    className="focus-ring block rounded-md border border-rule bg-panel px-4 py-3 transition-colors duration-[--duration-instant] hover:border-ink"
                                >
                                    <div className="flex items-baseline gap-3">
                                        <h2 className="truncate text-[13px] font-medium">
                                            {thread.title}
                                        </h2>
                                        <StatusDot status={thread.status} />
                                        <span className="label tnum ml-auto shrink-0">
                                            {thread.facts_count} facts ·{' '}
                                            {thread.sources_count} sources ·{' '}
                                            {thread.runs_count} runs
                                        </span>
                                    </div>

                                    <p className="mt-1 truncate text-xs text-muted">
                                        {thread.seed_question}
                                    </p>

                                    <div className="mt-3 max-w-xs">
                                        <BudgetGauge
                                            budget={thread.credit_budget}
                                            spent={thread.credits_spent}
                                            reserved={thread.credits_reserved}
                                            costPerRun={
                                                defaults.costs[thread.mode]
                                            }
                                        />
                                    </div>
                                </Link>

                                <div className="absolute top-3 right-3 opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100">
                                    <DeleteThreadButton threadId={thread.id} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
