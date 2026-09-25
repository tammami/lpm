import { cn } from '@/lib/utils';

type Severity = 'good' | 'warning' | 'critical' | 'neutral';

const fills: Record<Severity, string> = {
    good: 'bg-grad-good',
    warning: 'bg-grad-warn',
    critical: 'bg-grad-critical',
    neutral: 'bg-grad-neutral',
};

const tracks: Record<Severity, string> = {
    good: 'bg-primary/12',
    warning: 'bg-warning/18',
    critical: 'bg-destructive/12',
    neutral: 'bg-muted',
};

export function severityForRate(rate: number | null | undefined, good = 75, warning = 50): Severity {
    if (rate === null || rate === undefined) return 'neutral';
    if (rate >= good) return 'good';
    if (rate >= warning) return 'warning';
    return 'critical';
}

/**
 * Meter persentase: isian membawa tingkat keparahan, track adalah langkah terang dari warna yang sama.
 */
export function Meter({ value, severity, className, size = 'md' }: { value: number | null | undefined; severity?: Severity; className?: string; size?: 'sm' | 'md' }) {
    const tone = severity ?? severityForRate(value);
    const width = Math.max(0, Math.min(100, value ?? 0));

    return (
        <div
            role="meter"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={value ?? undefined}
            className={cn('w-full overflow-hidden rounded-full', size === 'sm' ? 'h-1.5' : 'h-2', tracks[tone], className)}
        >
            <div className={cn('h-full rounded-full transition-[width] duration-500', fills[tone])} style={{ width: `${width}%` }} />
        </div>
    );
}
