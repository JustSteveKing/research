import { useState } from 'react';
import Icon from '@/components/ui/icon';
import { cn } from '@/lib/utils';

export interface ToastPayload {
    id: string;
    message: string;
    tone: 'neutral' | 'signal' | 'knowledge';
}

const tones: Record<ToastPayload['tone'], string> = {
    neutral: 'border-rule',
    signal: 'border-signal/50 text-signal',
    knowledge: 'border-knowledge/50 text-knowledge',
};

/**
 * Confirms actions whose effect happens off-screen — a queued run, a rebuild,
 * a deletion. Keyed by the flash id so a repeat action replays the animation,
 * and self-dismissing through CSS rather than a timer in state.
 */
export default function Toaster({ toast }: { toast: ToastPayload | null }) {
    const [dismissed, setDismissed] = useState<string | null>(null);

    if (!toast || dismissed === toast.id) {
        return null;
    }

    return (
        <div
            key={toast.id}
            role="status"
            aria-live="polite"
            className={cn(
                'toast pointer-events-auto fixed right-3 bottom-9 z-40 flex items-center gap-2 rounded border bg-panel py-1.5 pr-1.5 pl-3 shadow-[0_8px_24px_-10px_rgb(0_0_0/0.35)]',
                tones[toast.tone],
            )}
        >
            <span className="font-mono text-[11px] tracking-[0.04em]">
                {toast.message}
            </span>

            <button
                type="button"
                onClick={() => setDismissed(toast.id)}
                aria-label="Dismiss"
                className="focus-ring rounded p-0.5 text-muted transition-colors hover:text-ink"
            >
                <Icon name="close" className="h-3 w-3" />
            </button>
        </div>
    );
}
