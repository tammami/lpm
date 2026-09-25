import { cn } from '@/lib/utils';

export interface Classification {
    label: string;
    color: string;
}

const tones: Record<string, string> = {
    danger: 'bg-danger-soft bg-linear-to-r from-danger-soft to-white/70 text-danger-foreground ring-destructive/20',
    warning: 'bg-warning-soft bg-linear-to-r from-warning-soft to-white/70 text-gold-foreground ring-warning/30',
    info: 'bg-info-soft bg-linear-to-r from-info-soft to-white/70 text-info-foreground ring-info/20',
    success: 'bg-success-soft bg-linear-to-r from-success-soft to-white/70 text-success-foreground ring-success/25',
    neutral: 'bg-muted bg-linear-to-r from-neutral-soft to-white/60 text-muted-foreground ring-border',
};

/** Label klasifikasi skor sesuai skema yang diatur admin (ikon titik + label, bukan warna saja). */
export function ClassBadge({ classification, className }: { classification: Classification | null | undefined; className?: string }) {
    if (!classification) return null;
    return (
        <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-bold whitespace-nowrap ring-1 ring-inset', tones[classification.color] ?? tones.neutral, className)}>
            <span className="size-1.5 rounded-full bg-current" />
            {classification.label}
        </span>
    );
}

export function InsufficientBadge({ responses, minimum }: { responses: number; minimum: number }) {
    return (
        <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap text-muted-foreground ring-1 ring-border ring-inset" title={`n=${responses} < minimum ${minimum}`}>
            Respons tidak mencukupi
        </span>
    );
}
