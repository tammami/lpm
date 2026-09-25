import { cn } from '@/lib/utils';

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'primary' | 'gold';

const tones: Record<Tone, string> = {
    success: 'bg-success-soft bg-linear-to-r from-success-soft to-white/70 text-success-foreground ring-success/25',
    warning: 'bg-warning-soft bg-linear-to-r from-warning-soft to-white/70 text-gold-foreground ring-warning/30',
    danger: 'bg-danger-soft bg-linear-to-r from-danger-soft to-white/70 text-danger-foreground ring-destructive/20',
    info: 'bg-info-soft bg-linear-to-r from-info-soft to-white/70 text-info-foreground ring-info/20',
    neutral: 'bg-muted bg-linear-to-r from-neutral-soft to-white/60 text-muted-foreground ring-border',
    primary: 'bg-secondary bg-linear-to-r from-secondary to-white/70 text-secondary-foreground ring-primary/15',
    gold: 'bg-gold-soft bg-linear-to-r from-gold-soft to-white/70 text-gold-foreground ring-gold/30',
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
