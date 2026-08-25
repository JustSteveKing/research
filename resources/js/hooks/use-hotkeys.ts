import { useEffect } from 'react';

type Handler = (event: KeyboardEvent) => void;

/**
 * Global keyboard bindings. Keys read as "mod+k" or "1", where mod is Cmd on
 * macOS and Ctrl elsewhere. Bindings are ignored while the user is typing,
 * unless the binding opts in with a leading "!".
 */
export function useHotkeys(bindings: Record<string, Handler>, enabled = true) {
    useEffect(() => {
        if (!enabled) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;
            const typing =
                target instanceof HTMLElement &&
                (target.tagName === 'INPUT' ||
                    target.tagName === 'TEXTAREA' ||
                    target.isContentEditable);

            for (const [combo, handler] of Object.entries(bindings)) {
                const force = combo.startsWith('!');
                const parts = (force ? combo.slice(1) : combo).split('+');
                const key = parts[parts.length - 1];

                if (typing && !force) {
                    continue;
                }

                if (event.key.toLowerCase() !== key.toLowerCase()) {
                    continue;
                }

                if (
                    parts.includes('mod') !== (event.metaKey || event.ctrlKey)
                ) {
                    continue;
                }

                if (parts.includes('shift') !== event.shiftKey) {
                    continue;
                }

                event.preventDefault();
                handler(event);

                return;
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [bindings, enabled]);
}
