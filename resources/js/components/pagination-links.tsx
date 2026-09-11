import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function PaginationLinks<T>({
    paginated,
}: {
    paginated: Pick<Paginated<T>, 'links' | 'current_page' | 'last_page'>;
}) {
    if (paginated.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex items-center justify-center gap-1"
            aria-label="Pagination"
        >
            {paginated.links.map((link, index) =>
                link.url === null ? (
                    <span
                        key={index}
                        // eslint-disable-next-line react/no-danger
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        className="text-muted-foreground px-3 py-1.5 text-sm"
                    />
                ) : (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        // eslint-disable-next-line react/no-danger
                        dangerouslySetInnerHTML={{ __html: link.label }}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm transition-colors',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-accent hover:text-accent-foreground',
                        )}
                    />
                ),
            )}
        </nav>
    );
}
