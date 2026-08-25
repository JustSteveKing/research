import { cn } from '@/lib/utils';

/**
 * Each cell is one research run at the thread's current mode: filled cells are
 * already paid for, the pulsing cell is the run in flight, empty cells are what
 * the budget still allows.
 */
export default function BudgetGauge({
    budget,
    spent,
    reserved,
    costPerRun,
    compact = false,
}: {
    budget: number;
    spent: number;
    reserved: number;
    costPerRun: number;
    compact?: boolean;
}) {
    const total = Math.max(1, Math.floor(budget / costPerRun));
    const shown = Math.min(total, 40);
    const spentCells = Math.floor(spent / costPerRun);
    const reservedCells = Math.ceil(reserved / costPerRun);
    const left = Math.max(0, total - spentCells - reservedCells);

    return (
        <div>
            {compact ? null : (
                <div className="flex items-baseline justify-between gap-2">
                    <span className="label">Budget</span>
                    <span className="tnum font-mono text-[11px]">
                        <span className="text-ink">
                            {spent.toLocaleString()}
                        </span>
                        <span className="text-rule"> / </span>
                        <span className="text-muted">
                            {budget.toLocaleString()}
                        </span>
                    </span>
                </div>
            )}

            <div
                className={cn('flex gap-[2px]', compact ? '' : 'mt-1.5')}
                role="img"
                aria-label={`${spent} of ${budget} credits spent, ${left} runs left`}
            >
                {Array.from({ length: shown }, (_, index) => {
                    const isSpent = index < spentCells;
                    const isReserved =
                        !isSpent && index < spentCells + reservedCells;

                    return (
                        <span
                            key={index}
                            className={cn(
                                'h-1.5 flex-1 rounded-[1px] transition-colors duration-[--duration-fast]',
                                isSpent && 'bg-signal',
                                isReserved && 'animate-pulse bg-signal/40',
                                !isSpent && !isReserved && 'bg-rule/60',
                            )}
                        />
                    );
                })}
            </div>

            <p className="label tnum mt-1">
                {left} {left === 1 ? 'run' : 'runs'} left · {costPerRun} cr each
                {total > shown ? ` · showing ${shown}` : ''}
            </p>
        </div>
    );
}
