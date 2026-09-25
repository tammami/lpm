import { cn } from '@/lib/utils';

interface Segment {
    label: string;
    count: number;
    percent: number;
}

/** Kelas warna diverging: dua hue berlawanan (terakota ↔ hijau), makin pekat di ujung skala. */
function divergingClass(index: number, total: number) {
    const low = ['bg-div-neg-2', 'bg-div-neg-1'];
    const high = ['bg-div-pos-1', 'bg-div-pos-2'];
    if (total === 2) return index === 0 ? low[1] : high[1];
    const half = total / 2;
    if (total % 2 === 1 && index === Math.floor(half)) return 'bg-div-mid';
    if (index < half) return index === 0 ? low[0] : low[1];
    return index === total - 1 ? high[1] : high[0];
}

/**
 * Bar bertumpuk 100% untuk sebaran jawaban Likert, celah 2px antar segmen, label hanya bila muat.
 */
export function DistributionBar({ segments, className }: { segments: Segment[]; className?: string }) {
    const total = segments.reduce((sum, s) => sum + s.count, 0);
    if (total === 0) return <div className={cn('h-3 rounded-full bg-muted', className)} />;

    return (
        <div className={cn('group/dist relative flex h-3 w-full gap-[2px] overflow-visible', className)}>
            {segments.map((segment, index) =>
                segment.percent > 0 ? (
                    <div
                        key={segment.label}
                        className={cn('h-full first:rounded-l-full last:rounded-r-full', divergingClass(index, segments.length))}
                        style={{ width: `${segment.percent}%` }}
                        title={`${segment.label}: ${segment.count} (${segment.percent}%)`}
                    />
                ) : null,
            )}
        </div>
    );
}

export function DistributionLegend({ labels }: { labels: string[] }) {
    return (
        <div className="flex flex-wrap items-center gap-3 text-[11px] text-muted-foreground">
            {labels.map((label, index) => (
                <span key={label} className="inline-flex items-center gap-1.5">
                    <span className={cn('size-2.5 rounded-sm', divergingClass(index, labels.length))} />
                    {label}
                </span>
            ))}
        </div>
    );
}
