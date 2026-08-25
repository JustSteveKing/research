import { cn } from '@/lib/utils';
import type { RunStatus, ThreadStatus } from '@/types/research';

const tone: Record<RunStatus | ThreadStatus, string> = {
    queued: 'bg-muted',
    streaming: 'bg-signal',
    running: 'bg-signal',
    completed: 'bg-knowledge',
    complete: 'bg-knowledge',
    idle: 'bg-muted',
    paused: 'bg-muted',
    failed: 'bg-red-600',
    cancelled: 'bg-muted',
};

export default function StatusDot({
    status,
    label,
}: {
    status: RunStatus | ThreadStatus;
    label?: string;
}) {
    const live = status === 'streaming' || status === 'running';

    return (
        <span className="inline-flex items-center gap-2">
            <span className="relative flex h-1.5 w-1.5">
                {live ? (
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-signal opacity-60" />
                ) : null}
                <span
                    className={cn(
                        'relative h-1.5 w-1.5 rounded-full',
                        tone[status],
                    )}
                />
            </span>
            <span className="label">{label ?? status}</span>
        </span>
    );
}
