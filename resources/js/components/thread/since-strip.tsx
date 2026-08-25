import Icon from '@/components/ui/icon';
import type { SinceLastOpened } from '@/types/workspace';

/**
 * What landed while the window was closed. Shown once per visit.
 */
export default function SinceStrip({
    since,
    onDismiss,
}: {
    since: SinceLastOpened;
    onDismiss: () => void;
}) {
    const parts = [
        since.runs > 0 ? `${since.runs} runs` : null,
        since.facts > 0 ? `${since.facts} facts` : null,
        since.questions > 0 ? `${since.questions} questions` : null,
        since.credits > 0 ? `${since.credits} cr spent` : null,
    ].filter(Boolean);

    return (
        <div className="rise-in flex h-8 items-center gap-2 border-b border-rule bg-knowledge/[0.06] px-4">
            <Icon name="spark" className="text-knowledge" />
            <span className="label text-knowledge">Since you left</span>
            <span className="tnum text-xs">{parts.join(' · ')}</span>
            <button
                type="button"
                onClick={onDismiss}
                aria-label="Dismiss"
                className="focus-ring ml-auto rounded p-1 text-muted transition-colors hover:text-ink"
            >
                <Icon name="close" className="h-3 w-3" />
            </button>
        </div>
    );
}
