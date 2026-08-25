import { router } from '@inertiajs/react';
import ContradictionController from '@/actions/App/Http/Controllers/ContradictionController';
import Button from '@/components/ui/button';
import EmptyState from '@/components/ui/empty-state';
import Pill from '@/components/ui/pill';
import type { Contradiction } from '@/types/research';

/**
 * Every open disagreement in one place. Settling a pair keeps the record —
 * the same two facts are never re-flagged — it only stops it being shown as
 * open, so the brief can move on.
 */
export default function DisputeList({
    threadId,
    contradictions,
}: {
    threadId: number;
    contradictions: Contradiction[];
}) {
    if (contradictions.length === 0) {
        return (
            <EmptyState
                title="Nothing disputed"
                body="After each run the local model compares new facts against everything known and flags the pairs that cannot both be true."
            />
        );
    }

    const settle = (clash: Contradiction) =>
        router.patch(
            ContradictionController.update([threadId, clash.id]).url,
            { resolved: true },
            {
                preserveScroll: true,
                only: ['facts', 'contradictions', 'thread'],
            },
        );

    return (
        <ul className="space-y-4">
            {contradictions.map((clash) => (
                <li
                    key={clash.id}
                    className="border-b border-rule pb-4 last:border-0"
                >
                    <div className="space-y-1.5">
                        {clash.sides.map((side, index) => (
                            <div key={side.id} className="flex gap-2">
                                <span className="label mt-0.5 shrink-0 text-signal">
                                    {index === 0 ? 'A' : 'B'}
                                </span>
                                <div className="min-w-0">
                                    <p className="text-[13px] leading-5 text-pretty">
                                        {side.statement}
                                    </p>
                                    {side.thread ? (
                                        <p className="label mt-0.5">
                                            from “{side.thread.title}”
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>

                    {clash.note ? (
                        <p className="mt-2 text-xs leading-relaxed text-pretty text-muted">
                            {clash.note}
                        </p>
                    ) : null}

                    <div className="mt-2 flex items-center gap-2">
                        <Pill title="How closely the two statements match">
                            {Math.round(clash.similarity * 100)}% overlap
                        </Pill>
                        <Button
                            type="button"
                            variant="ghost"
                            size="xs"
                            icon="check"
                            onClick={() => settle(clash)}
                            className="ml-auto"
                        >
                            Settled
                        </Button>
                    </div>
                </li>
            ))}
        </ul>
    );
}
