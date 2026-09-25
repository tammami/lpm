import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export function PaginationBar({ meta }: { meta: Omit<Paginated<unknown>, 'data'> }) {
    if (meta.total === 0) return null;
    const links = meta.links.slice(1, -1);
    const prev = meta.links[0];
    const next = meta.links[meta.links.length - 1];

    const pageClass = 'flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-[13px] font-semibold transition tabular';

    return (
        <div className="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 sm:flex-row">
            <p className="text-xs text-muted-foreground">
                Menampilkan <span className="font-semibold text-foreground tabular">{formatNumber(meta.from)}</span>–
                <span className="font-semibold text-foreground tabular">{formatNumber(meta.to)}</span> dari{' '}
                <span className="font-semibold text-foreground tabular">{formatNumber(meta.total)}</span> data
            </p>
            {meta.last_page > 1 && (
                <nav className="flex items-center gap-1" aria-label="Paginasi">
                    <PageLink url={prev.url} className={pageClass} label="Sebelumnya">
                        <ChevronLeft className="size-4" />
                    </PageLink>
                    {links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                preserveState
                                className={cn(pageClass, link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted')}
                            >
                                {link.label}
                            </Link>
                        ) : (
                            <span key={index} className={cn(pageClass, 'text-muted-foreground')}>
                                …
                            </span>
                        ),
                    )}
                    <PageLink url={next.url} className={pageClass} label="Berikutnya">
                        <ChevronRight className="size-4" />
                    </PageLink>
                </nav>
            )}
        </div>
    );
}

function PageLink({ url, className, label, children }: { url: string | null; className: string; label: string; children: React.ReactNode }) {
    if (!url) {
        return (
            <span className={cn(className, 'text-muted-foreground/40')} aria-disabled>
                {children}
            </span>
        );
    }
    return (
        <Link href={url} preserveScroll preserveState className={cn(className, 'text-muted-foreground hover:bg-muted')} aria-label={label}>
            {children}
        </Link>
    );
}
