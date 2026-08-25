import { router } from '@inertiajs/react';
import { useState } from 'react';
import ThreadController from '@/actions/App/Http/Controllers/ThreadController';
import Button from '@/components/ui/button';
import Pill from '@/components/ui/pill';
import type { WatchCadence } from '@/types/research';

const cadences: { id: WatchCadence | null; label: string; hint: string }[] = [
    { id: null, label: 'Off', hint: 'no scheduled runs' },
    { id: 'daily', label: 'Daily', hint: 'fast-moving topics' },
    { id: 'weekly', label: 'Weekly', hint: 'most research' },
    { id: 'monthly', label: 'Monthly', hint: 'slow-moving fields' },
];

/**
 * Re-research the seed question on a cadence. Watch runs come out of the same
 * budget as everything else, so the cost per check is stated up front.
 */
export default function WatchMenu({
    threadId,
    cadence,
    runCost,
}: {
    threadId: number;
    cadence: WatchCadence | null;
    runCost: number;
}) {
    const [open, setOpen] = useState(false);

    const choose = (next: WatchCadence | null) => {
        setOpen(false);

        if (next !== cadence) {
            router.patch(
                ThreadController.update(threadId).url,
                { watch_cadence: next },
                { preserveScroll: true },
            );
        }
    };

    return (
        <div className="relative">
            <Button
                type="button"
                variant={cadence ? 'outline' : 'ghost'}
                icon="clock"
                aria-expanded={open}
                aria-haspopup="menu"
                onClick={() => setOpen((current) => !current)}
            >
                Watch
                {cadence ? (
                    <Pill tone="knowledge" className="ml-1">
                        {cadence}
                    </Pill>
                ) : null}
            </Button>

            {open ? (
                <>
                    <button
                        type="button"
                        aria-hidden
                        tabIndex={-1}
                        onClick={() => setOpen(false)}
                        className="fixed inset-0 z-10 cursor-default"
                    />

                    <ul
                        role="menu"
                        className="rise-in absolute bottom-full left-0 z-20 mb-1.5 w-60 rounded border border-rule bg-panel p-1 shadow-lg"
                    >
                        {cadences.map((option) => (
                            <li key={option.label} role="none">
                                <button
                                    type="button"
                                    role="menuitemradio"
                                    aria-checked={option.id === cadence}
                                    onClick={() => choose(option.id)}
                                    className="focus-ring flex w-full items-baseline gap-2 rounded px-2 py-1.5 text-left transition-colors hover:bg-ground"
                                >
                                    <span className="text-[13px]">
                                        {option.label}
                                    </span>
                                    <span className="label ml-auto">
                                        {option.hint}
                                    </span>
                                </button>
                            </li>
                        ))}

                        <li
                            role="none"
                            className="label mt-1 border-t border-rule px-2 pt-1.5"
                        >
                            Each check spends {runCost} credits from this
                            thread’s budget.
                        </li>
                    </ul>
                </>
            ) : null}
        </div>
    );
}
