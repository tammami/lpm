import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types';

interface Props {
    title: string;
    description?: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    actions?: ReactNode;
    meta?: ReactNode;
}

export function PageHeader({ title, description, breadcrumbs, actions, meta }: Props) {
    return (
        <>
            <Head title={title} />
            <div className="mb-6 flex flex-col gap-4 md:mb-8 md:flex-row md:items-end md:justify-between">
                <div className="min-w-0">
                    {breadcrumbs && breadcrumbs.length > 0 && (
                        <nav aria-label="Breadcrumb" className="mb-2 flex flex-wrap items-center gap-1 text-xs font-medium text-muted-foreground">
                            {breadcrumbs.map((crumb, index) => (
                                <span key={index} className="flex items-center gap-1">
                                    {index > 0 && <ChevronRight className="size-3 opacity-50" />}
                                    {crumb.href ? (
                                        <Link href={crumb.href} className="transition hover:text-primary">
                                            {crumb.label}
                                        </Link>
                                    ) : (
                                        <span className="text-foreground/70">{crumb.label}</span>
                                    )}
                                </span>
                            ))}
                        </nav>
                    )}
                    <h1 className="text-2xl font-extrabold tracking-tight text-foreground md:text-[28px]">{title}</h1>
                    {description && <div className="mt-1.5 max-w-3xl text-sm text-muted-foreground">{description}</div>}
                    {meta && <div className="mt-3 flex flex-wrap items-center gap-2">{meta}</div>}
                </div>
                {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
            </div>
        </>
    );
}
