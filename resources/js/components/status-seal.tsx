import { cn } from '@/lib/utils';

/**
 * The seal glyph this system uses instead of a colored pill: a filled ring
 * for an on-file / complete state, a dashed open ring for a pending /
 * incomplete one. An unfiled state is drawn as deliberately as a filed one.
 */
export default function StatusSeal({
    on,
    label,
    className,
}: {
    on: boolean;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 font-mono text-xs',
                on ? 'text-status-on' : 'text-status-off',
                className,
            )}
        >
            <svg
                viewBox="0 0 12 12"
                className="size-2.5 shrink-0"
                aria-hidden="true"
            >
                <circle
                    cx="6"
                    cy="6"
                    r="5"
                    fill={on ? 'currentColor' : 'none'}
                    stroke="currentColor"
                    strokeWidth="1.4"
                    strokeDasharray={on ? undefined : '2 1.6'}
                />
            </svg>
            {label}
        </span>
    );
}
