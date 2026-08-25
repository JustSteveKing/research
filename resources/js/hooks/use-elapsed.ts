import { useEffect, useState } from 'react';

function format(seconds: number): string {
    const minutes = Math.floor(seconds / 60);

    return `${String(minutes).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

/**
 * Ticking mm:ss since a timestamp. Returns null when nothing is running, so a
 * finished run leaves no stale clock behind.
 */
export function useElapsed(
    startedAt: string | null | undefined,
): string | null {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!startedAt) {
            return;
        }

        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, [startedAt]);

    if (!startedAt) {
        return null;
    }

    const seconds = Math.max(
        0,
        Math.floor((now - new Date(startedAt).getTime()) / 1000),
    );

    return format(seconds);
}
