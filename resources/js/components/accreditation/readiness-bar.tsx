import { cn } from '@/lib/utils';

/**
 * Bar bertumpuk status indikator: siap (hijau) · menunggu verifikasi (kuning) · belum ada bukti (merah muda).
 * Warna status disertai legenda berlabel agar tidak bergantung pada warna saja.
 */
export function ReadinessBar({ ready, partial, gap, className, legend = false, size = 'md' }: { ready: number; partial: number; gap: number; className?: string; legend?: boolean; size?: 'sm' | 'md' }) {
    const total = ready + partial + gap;
    const segments = [
        { key: 'ready', value: ready, label: 'Siap', className: 'bg-grad-good' },
        { key: 'partial', value: partial, label: 'Menunggu verifikasi', className: 'bg-grad-warn' },
        { key: 'gap', value: gap, label: 'Belum ada bukti', className: 'bg-grad-gap' },
    ];

    return (
        <div className={className}>
            <div className={cn('flex w-full gap-[2px] overflow-hidden rounded-full bg-muted', size === 'sm' ? 'h-1.5' : 'h-2.5')} role="img" aria-label={`Siap ${ready}, menunggu ${partial}, belum ada bukti ${gap} dari ${total} indikator`}>
                {total > 0 &&
                    segments
                        .filter((segment) => segment.value > 0)
                        .map((segment) => <span key={segment.key} className={cn('h-full first:rounded-l-full last:rounded-r-full', segment.className)} style={{ width: `${(segment.value / total) * 100}%` }} title={`${segment.label}: ${segment.value}`} />)}
            </div>
            {legend && (
                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-muted-foreground">
                    {segments.map((segment) => (
                        <span key={segment.key} className="inline-flex items-center gap-1.5">
                            <span className={cn('size-2 rounded-full', segment.className)} />
                            {segment.label} <b className="text-foreground tabular">{segment.value}</b>
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}
