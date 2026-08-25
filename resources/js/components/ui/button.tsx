import type { ButtonHTMLAttributes } from 'react';
import Icon, { type IconName } from '@/components/ui/icon';
import { cn } from '@/lib/utils';

type Variant = 'ghost' | 'outline' | 'solid' | 'danger';
type Size = 'xs' | 'sm';

const variants: Record<Variant, string> = {
    ghost: 'border-transparent text-muted hover:border-rule hover:text-ink',
    outline: 'border-rule text-ink hover:border-ink',
    solid: 'border-ink bg-ink text-ground hover:opacity-90',
    danger:
        'border-red-600/50 text-red-700 hover:bg-red-600/10 dark:text-red-400',
};

const sizes: Record<Size, string> = {
    xs: 'h-6 gap-1.5 px-2 text-[11px]',
    sm: 'h-7 gap-1.5 px-2.5 text-xs',
};

export default function Button({
    variant = 'outline',
    size = 'sm',
    icon,
    mono = false,
    className,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: Variant;
    size?: Size;
    icon?: IconName;
    /** Machine-ish labels use the mono face, like the rest of the data. */
    mono?: boolean;
}) {
    return (
        <button
            {...props}
            className={cn(
                'focus-ring inline-flex items-center justify-center rounded border transition-colors duration-[--duration-instant] disabled:cursor-not-allowed disabled:opacity-45',
                mono && 'font-mono tracking-[0.06em] uppercase',
                variants[variant],
                sizes[size],
                className,
            )}
        >
            {icon ? <Icon name={icon} /> : null}
            {children}
        </button>
    );
}
