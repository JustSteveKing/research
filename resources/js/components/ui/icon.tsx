import { cn } from '@/lib/utils';

/**
 * Hand-drawn 16px icon set — small enough to keep inline and avoid a
 * dependency, consistent 1.5px strokes so they sit with the mono type.
 */
const paths = {
    plus: <path d="M8 3.5v9M3.5 8h9" />,
    search: (
        <>
            <circle cx="7.2" cy="7.2" r="3.7" />
            <path d="M10 10l3 3" />
        </>
    ),
    pin: <path d="M6 2.5h4l-.6 3.2 2.1 2.1-3 .6-2.2 4-.9-4.2-3-.9 2.4-1.6z" />,
    chevron: <path d="M6 4l4 4-4 4" />,
    close: <path d="M4 4l8 8M12 4l-8 8" />,
    external: (
        <>
            <path d="M9 3.5h3.5V7" />
            <path d="M12.5 3.5L7.5 8.5" />
            <path d="M11 9.5v3h-8v-8h3" />
        </>
    ),
    warning: (
        <>
            <path d="M8 3l5 9H3z" />
            <path d="M8 6.6v2.4M8 10.6v.6" />
        </>
    ),
    trash: (
        <>
            <path d="M3.5 4.5h9M6.5 4.5V3h3v1.5" />
            <path d="M5 4.5l.6 8h4.8l.6-8" />
        </>
    ),
    refresh: (
        <>
            <path d="M12.5 7a4.5 4.5 0 10-1.2 3.4" />
            <path d="M12.5 4v3h-3" />
        </>
    ),
    stop: <rect x="4.5" y="4.5" width="7" height="7" rx="1" />,
    send: <path d="M3 8l10-4-4 10-1.8-4.2z" />,
    spark: <path d="M8 2.5l1.4 3.6 3.6 1.4-3.6 1.4L8 12.5 6.6 8.9 3 7.5l3.6-1.4z" />,
    check: <path d="M3.5 8.5l3 3 6-7" />,
    pencil: (
        <>
            <path d="M11.2 2.8l2 2-7.2 7.2-2.6.6.6-2.6z" />
            <path d="M9.8 4.2l2 2" />
        </>
    ),
    download: (
        <>
            <path d="M8 2.5v7" />
            <path d="M5 7l3 3 3-3" />
            <path d="M3 12.5h10" />
        </>
    ),
    split: (
        <>
            <path d="M3 3.5h2.5L8 8l2.5 4.5H13" />
            <path d="M3 12.5h2.5L8 8" />
            <path d="M11 1.8l2 1.7-2 1.7" />
            <path d="M11 10.8l2 1.7-2 1.7" />
        </>
    ),
    clock: (
        <>
            <circle cx="8" cy="8" r="5.5" />
            <path d="M8 4.8V8l2.2 1.6" />
        </>
    ),
    link: (
        <>
            <path d="M6.8 9.2a2.6 2.6 0 003.7 0l2-2a2.6 2.6 0 00-3.7-3.7l-1 1" />
            <path d="M9.2 6.8a2.6 2.6 0 00-3.7 0l-2 2a2.6 2.6 0 003.7 3.7l1-1" />
        </>
    ),
} as const;

export type IconName = keyof typeof paths;

export default function Icon({
    name,
    className,
}: {
    name: IconName;
    className?: string;
}) {
    return (
        <svg
            viewBox="0 0 16 16"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            className={cn('h-3.5 w-3.5 shrink-0', className)}
        >
            {paths[name]}
        </svg>
    );
}
