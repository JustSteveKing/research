import { cn } from '@/lib/utils';

/**
 * Any control that spends research credits states its price on the label.
 */
export default function CostButton({
    cost,
    affordable,
    available,
    processing,
    children,
    className,
    ...props
}: React.ButtonHTMLAttributes<HTMLButtonElement> & {
    cost: number;
    affordable: boolean;
    available: number;
    processing?: boolean;
}) {
    return (
        <button
            {...props}
            disabled={!affordable || processing || props.disabled}
            title={
                affordable
                    ? undefined
                    : `Needs ${cost} credits, ${available} left in this thread's budget`
            }
            className={cn(
                'focus-ring inline-flex h-7 items-center gap-1.5 rounded border px-2.5 text-xs transition-colors duration-[--duration-instant] disabled:cursor-not-allowed disabled:opacity-45',
                affordable
                    ? 'border-ink bg-ink text-ground hover:opacity-90'
                    : 'border-rule text-muted',
                className,
            )}
        >
            {processing ? 'Starting…' : children}
            <span className="tnum font-mono text-[10px] opacity-80">
                {cost} cr
            </span>
        </button>
    );
}
