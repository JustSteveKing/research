import type { PropsWithChildren } from 'react';
import { useEffect, useRef } from 'react';

/**
 * Centred modal surface with focus trapping and Escape to dismiss. Used by the
 * composer and the command palette so both behave identically.
 */
export default function Overlay({
    open,
    onClose,
    label,
    align = 'center',
    children,
}: PropsWithChildren<{
    open: boolean;
    onClose: () => void;
    label: string;
    align?: 'center' | 'top';
}>) {
    const panel = useRef<HTMLDivElement>(null);
    const restoreTo = useRef<HTMLElement | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        restoreTo.current = document.activeElement as HTMLElement | null;

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();

                return;
            }

            if (event.key !== 'Tab' || !panel.current) {
                return;
            }

            const focusable = panel.current.querySelectorAll<HTMLElement>(
                'a[href], button:not([disabled]), input, textarea, select, [tabindex]:not([tabindex="-1"])',
            );

            if (focusable.length === 0) {
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };

        window.addEventListener('keydown', onKeyDown, true);

        return () => {
            window.removeEventListener('keydown', onKeyDown, true);
            restoreTo.current?.focus();
        };
    }, [open, onClose]);

    if (!open) {
        return null;
    }

    return (
        <div
            className={`fixed inset-0 z-50 flex justify-center bg-ink/25 px-6 backdrop-blur-[2px] ${
                align === 'top' ? 'items-start pt-[12vh]' : 'items-center'
            }`}
            onMouseDown={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <div
                ref={panel}
                role="dialog"
                aria-modal="true"
                aria-label={label}
                className="rise-in w-full max-w-2xl rounded-md border border-rule bg-panel p-5 shadow-[0_12px_32px_-12px_rgb(0_0_0/0.35)]"
            >
                {children}
            </div>
        </div>
    );
}
