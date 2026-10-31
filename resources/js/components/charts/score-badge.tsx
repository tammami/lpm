import { cn } from '@/lib/utils';

export interface Classification {
    label: string;
    color: string;
}

const tones: Record<string, string> = {
    danger: 'bg-danger-soft text-danger-foreground ring-current/40',
    warning: 'bg-warning-soft text-gold-foreground ring-current/40',
    info: 'bg-info-soft text-info-foreground ring-current/40',
    success: 'bg-success-soft text-success-foreground ring-current/40',
    neutral: 'bg-neutral-soft text-muted-foreground ring-current/35',
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
        <span className="inline-flex items-center rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap text-muted-foreground ring-1 ring-border ring-inset" title={`Baru ${responses} respons, minimum ${minimum}`}>
            Respons tidak mencukupi
        </span>
    );
}
