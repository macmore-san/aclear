import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * The masthead every page in this system opens on — a bordered header block
 * naming the record, the way a permit or certificate states what it is and
 * its validity period before any content. Shared so every page reads as the
 * same register, not a one-off hero.
 */
export default function RegisterHeader({
    title,
    meta,
    actions,
    className,
}: {
    title: string;
    meta?: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <header
            className={cn(
                'border-border bg-card flex flex-col gap-4 rounded-sm border px-5 py-4 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="space-y-1">
                <h1 className="font-serif text-xl font-semibold tracking-tight sm:text-2xl">
                    {title}
                </h1>
                {meta && (
                    <div className="text-muted-foreground font-mono text-xs">
                        {meta}
                    </div>
                )}
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
