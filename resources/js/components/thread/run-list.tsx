import { Form } from '@inertiajs/react';
import { useState } from 'react';
import ResearchRunController from '@/actions/App/Http/Controllers/ResearchRunController';
import Markdown from '@/components/markdown';
import RunTimeline from '@/components/run-timeline';
import StatusDot from '@/components/status-dot';
import type { Run } from '@/types/research';

export default function RunList({
    threadId,
    runs,
}: {
    threadId: number;
    runs: Run[];
}) {
    const latest = runs.at(-1)?.id ?? null;
    const [openId, setOpenId] = useState<number | null>(latest);
    const open = runs.find((run) => run.id === openId) ?? runs.at(-1) ?? null;

    if (runs.length === 0) {
        return <p className="text-sm text-muted">No runs yet.</p>;
    }

    return (
        <div className="grid gap-6 lg:grid-cols-[260px_1fr]">
            <ol className="space-y-1">
                {runs.map((run) => (
                    <li
                        key={run.id}
                        style={{ paddingLeft: `${run.depth * 12}px` }}
                    >
                        <button
                            type="button"
                            onClick={() => setOpenId(run.id)}
                            className={`w-full rounded border px-3 py-2 text-left transition ${
                                open?.id === run.id
                                    ? 'border-ink bg-ground'
                                    : 'border-transparent hover:border-rule'
                            }`}
                        >
                            <span className="flex items-center justify-between gap-2">
                                <span className="label">
                                    {run.depth === 0
                                        ? 'Seed'
                                        : `Depth ${run.depth}`}
                                </span>
                                <StatusDot
                                    status={run.status}
                                    label={run.phase ?? run.status}
                                />
                            </span>
                            <span className="mt-1 line-clamp-2 block text-sm">
                                {run.query}
                            </span>
                        </button>
                    </li>
                ))}
            </ol>

            {open ? (
                <article className="min-w-0">
                    <header className="flex flex-wrap items-baseline justify-between gap-3 border-b border-rule pb-3">
                        <h3 className="text-base font-medium tracking-tight text-pretty">
                            {open.query}
                        </h3>
                        <div className="flex items-center gap-4">
                            <span className="label">
                                {open.mode} · {open.credits_cost} cr
                                {open.pages_analyzed
                                    ? ` · ${open.pages_analyzed} pages`
                                    : ''}
                            </span>
                            {open.status === 'streaming' ||
                            open.status === 'queued' ? (
                                <Form
                                    action={ResearchRunController.destroy([
                                        threadId,
                                        open.id,
                                    ])}
                                >
                                    <button
                                        type="submit"
                                        className="label hover:text-ink"
                                    >
                                        Stop run
                                    </button>
                                </Form>
                            ) : null}
                        </div>
                    </header>

                    {open.error ? (
                        <p className="mt-4 rounded border border-red-600/40 bg-red-600/5 p-3 text-sm text-red-700 dark:text-red-400">
                            {open.error}
                        </p>
                    ) : null}

                    {open.report ? (
                        <Markdown className="mt-5">{open.report}</Markdown>
                    ) : (
                        <div className="mt-5">
                            <RunTimeline
                                events={open.events}
                                live={open.status === 'streaming'}
                            />
                        </div>
                    )}

                    {open.report && open.events.length > 0 ? (
                        <details className="mt-6">
                            <summary className="label cursor-pointer hover:text-ink">
                                How this run was made
                            </summary>
                            <div className="mt-3">
                                <RunTimeline
                                    events={open.events}
                                    live={false}
                                />
                            </div>
                        </details>
                    ) : null}
                </article>
            ) : null}
        </div>
    );
}
