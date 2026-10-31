import { cn } from '@/lib/utils';

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'primary' | 'gold';

const tones: Record<Tone, string> = {
    success: 'bg-success-soft text-success-foreground ring-current/40',
    warning: 'bg-warning-soft text-gold-foreground ring-current/40',
    danger: 'bg-danger-soft text-danger-foreground ring-current/40',
    info: 'bg-info-soft text-info-foreground ring-current/40',
    neutral: 'bg-neutral-soft text-muted-foreground ring-current/35',
    primary: 'bg-secondary text-secondary-foreground ring-current/40',
    gold: 'bg-gold-soft text-gold-foreground ring-current/40',
};

export function StatusBadge({ tone = 'neutral', children, dot = true, className }: { tone?: Tone; children: React.ReactNode; dot?: boolean; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset', tones[tone], className)}>
            {dot && <span className="size-1.5 rounded-full bg-current opacity-80" />}
            {children}
        </span>
    );
}

/** Pemetaan status umum ke warna. */
export const statusTone: Record<string, Tone> = {
    draft: 'neutral',
    review: 'info',
    approved: 'primary',
    published: 'success',
    active: 'success',
    aktif: 'success',
    closed: 'neutral',
    archived: 'neutral',
    submitted: 'success',
    reopened: 'warning',
    open: 'danger',
    action_required: 'warning',
    in_progress: 'info',
    verification: 'primary',
    verified: 'success',
    rejected: 'danger',
    revision: 'danger',
    planned: 'info',
    completed: 'success',
    cancelled: 'neutral',
    overdue: 'danger',
    pending: 'warning',
    expired: 'danger',
    cuti: 'warning',
    lulus: 'primary',
    keluar: 'danger',
    non_aktif: 'neutral',
};

export function EnumBadge({ value, label }: { value: string; label: string }) {
    return <StatusBadge tone={statusTone[value] ?? 'neutral'}>{label}</StatusBadge>;
}
