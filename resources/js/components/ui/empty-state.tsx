import type { ReactNode } from 'react';

/**
 * Empty is a place to act, not a shrug. Every one names what will fill it.
 */
export default function EmptyState({
    title,
    body,
    action,
}: {
    title: string;
    body?: string;
    action?: ReactNode;
}) {
    return (
        <div className="rounded border border-dashed border-rule px-4 py-5">
            <p className="text-sm">{title}</p>
            {body ? <p className="mt-1 max-w-prose text-xs text-muted">{body}</p> : null}
            {action ? <div className="mt-3">{action}</div> : null}
        </div>
    );
}
