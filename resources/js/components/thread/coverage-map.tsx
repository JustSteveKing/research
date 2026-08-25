import { router } from '@inertiajs/react';
import ResearchRunController from '@/actions/App/Http/Controllers/ResearchRunController';
import Icon from '@/components/ui/icon';
import { cn } from '@/lib/utils';
import type { CoverageTopic } from '@/types/research';

const tones: Record<CoverageTopic['status'], string> = {
    covered: 'bg-knowledge',
    thin: 'bg-knowledge/40',
    open: 'bg-rule',
};

/**
 * What a complete answer needs, against what the thread actually holds.
 *
 * The sub-topics are the model's; the counts are measured from the facts, so
 * an empty bar means nobody has researched that yet rather than that the
 * model forgot to mention it.
 */
export default function CoverageMap({
    threadId,
    coverage,
    canSpend,
    runCost,
}: {
    threadId: number;
    coverage: CoverageTopic[];
    canSpend: boolean;
    runCost: number;
}) {
    if (coverage.length === 0) {
        return null;
    }

    const open = coverage.filter((topic) => topic.status !== 'covered').length;

    const research = (topic: CoverageTopic) =>
        router.post(
            ResearchRunController.store(threadId).url,
            { query: topic.topic },
            { preserveScroll: true },
        );

    return (
        <section className="mb-6 rounded border border-rule bg-panel/40 p-3">
            <h2 className="label mb-2.5 flex items-center gap-1.5">
                Coverage
                <span className="text-muted">
                    · {coverage.length - open} of {coverage.length} covered
                </span>
            </h2>

            <ul className="space-y-2">
                {coverage.map((topic) => (
                    <li key={topic.topic} className="group/topic flex gap-2.5">
                        <span
                            aria-hidden
                            className={cn(
                                'mt-1.5 h-3 w-0.5 shrink-0 rounded-full',
                                tones[topic.status],
                            )}
                        />

                        <div className="min-w-0 flex-1">
                            <p className="flex items-baseline gap-2 text-[13px] leading-5">
                                <span className="text-pretty">
                                    {topic.topic}
                                </span>
                                <span className="label tnum ml-auto shrink-0">
                                    {topic.status === 'open'
                                        ? 'nothing yet'
                                        : `${topic.facts_count} facts`}
                                </span>
                            </p>

                            {topic.why ? (
                                <p className="label mt-0.5 text-pretty">
                                    {topic.why}
                                </p>
                            ) : null}
                        </div>

                        {topic.status !== 'covered' ? (
                            <button
                                type="button"
                                onClick={() => research(topic)}
                                disabled={!canSpend}
                                title={
                                    canSpend
                                        ? `Research this now · ${runCost} cr`
                                        : 'Not enough credits left in this budget'
                                }
                                aria-label={`Research ${topic.topic}`}
                                className="focus-ring mt-0.5 shrink-0 self-start rounded p-0.5 text-muted opacity-0 transition-opacity group-hover/topic:opacity-100 hover:text-ink focus-visible:opacity-100 disabled:cursor-not-allowed disabled:opacity-30"
                            >
                                <Icon name="spark" className="h-3 w-3" />
                            </button>
                        ) : null}
                    </li>
                ))}
            </ul>
        </section>
    );
}
