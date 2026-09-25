import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function EmptyState({
    icon: Icon = Inbox,
    title,
    description,
    action,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: ReactNode;
    action?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('flex flex-col items-center justify-center px-6 py-14 text-center', className)}>
            <div className="relative mb-4">
                <div className="absolute inset-0 rotate-45 rounded-2xl bg-linear-to-br from-info-soft via-secondary to-gold-soft" />
                <div className="relative flex size-14 items-center justify-center rounded-2xl border border-white/85 bg-white/85 text-primary shadow-card">
                    <Icon className="size-6" />
                </div>
            </div>
            <h3 className="text-[16px] font-bold">{title}</h3>
            {description && <p className="mt-1 max-w-sm text-sm text-muted-foreground">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
