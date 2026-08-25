import type { RunEvent } from '@/types/research';

/**
 * The phase tape of a run: one tick per event the research engine reported.
 */
export default function RunTimeline({
    events,
    live,
}: {
    events: RunEvent[];
    live: boolean;
}) {
    if (events.length === 0) {
        return (
            <p className="label flex items-center gap-1.5">
                {live ? (
                    <>
                        <span className="h-1.5 w-1.5 animate-pulse rounded-full bg-signal" />
                        Waiting for the first event
                    </>
                ) : (
                    'No events recorded'
                )}
            </p>
        );
    }

    return (
        <ol className="relative space-y-1.5 border-l border-rule pl-3.5">
            {events.map((event, index) => {
                const last = index === events.length - 1;

                return (
                    <li key={event.id} className="relative">
                        <span
                            aria-hidden
                            className={
                                last && live
                                    ? 'absolute top-[7px] -left-[18.5px] h-1.5 w-1.5 animate-pulse rounded-full bg-signal ring-2 ring-panel'
                                    : 'absolute top-[7px] -left-[18.5px] h-1.5 w-1.5 rounded-full bg-rule ring-2 ring-panel'
                            }
                        />
                        <div className="flex items-baseline gap-2">
                            <span
                                className={
                                    last && live
                                        ? 'font-mono text-[11px] text-signal'
                                        : 'font-mono text-[11px] text-ink'
                                }
                            >
                                {event.event}
                            </span>
                            <time className="label tnum ml-auto shrink-0">
                                {new Date(event.created_at).toLocaleTimeString(
                                    [],
                                    {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit',
                                    },
                                )}
                            </time>
                        </div>
                        {event.message ? (
                            <p className="truncate text-xs text-muted">
                                {event.message}
                            </p>
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}
