import { cn } from '@/lib/utils';

/** Renders a shortcut the way the OS shows it. */
export default function Keycap({
    keys,
    className,
}: {
    keys: string;
    className?: string;
}) {
    return (
        <kbd
            className={cn(
                'rounded border border-rule bg-ground px-1 py-px font-mono text-[10px] leading-4 text-muted',
                className,
            )}
        >
            {keys}
        </kbd>
    );
}
