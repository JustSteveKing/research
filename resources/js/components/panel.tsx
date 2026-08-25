import type { PropsWithChildren, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export default function Panel({
    title,
    aside,
    className,
    children,
}: PropsWithChildren<{
    title?: string;
    aside?: ReactNode;
    className?: string;
}>) {
    return (
        <section
            className={cn(
                'rounded-md border border-rule bg-panel p-5',
                className,
            )}
        >
            {title ? (
                <header className="mb-4 flex items-center justify-between gap-3">
                    <h2 className="label">{title}</h2>
                    {aside}
                </header>
            ) : null}
            {children}
        </section>
    );
}
