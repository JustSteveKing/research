import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'knowledge' | 'signal' | 'danger';

const tones: Record<Tone, string> = {
    neutral: 'border-rule text-muted',
    knowledge: 'border-knowledge/40 text-knowledge',
    signal: 'border-signal/40 text-signal',
    danger: 'border-red-600/40 text-red-700 dark:text-red-400',
};

export default function Pill({
    tone = 'neutral',
    className,
    title,
    children,
}: {
    tone?: Tone;
    className?: string;
    title?: string;
    children: React.ReactNode;
}) {
    return (
        <span
            title={title}
            className={cn(
                'inline-flex h-4 items-center rounded-full border px-1.5 font-mono text-[10px] tabular-nums',
                tones[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}
