import { heatTone } from '@/components/charts/heatmap';
import { formatScore } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Perubahan skor terhadap kegiatan sebelumnya (▲ naik, ▼ turun). */
export function ScoreDelta({ score, previous, label, className }: { score: number | null; previous: number | null; label?: string; className?: string }) {
    if (score === null || previous === null) return null;

    const diff = Math.round((score - previous) * 100) / 100;
    const title = `Sebelumnya ${formatScore(previous)}${label ? ` (${label})` : ''}`;

    if (diff === 0) {
        return (
            <span className={cn('text-[11px] font-semibold text-muted-foreground', className)} title={title}>
                = tetap
            </span>
        );
    }

    return (
        <span className={cn('text-[11px] font-semibold whitespace-nowrap tabular', diff > 0 ? 'text-success-foreground' : 'text-danger-foreground', className)} title={title}>
            {diff > 0 ? '▲' : '▼'} {formatScore(Math.abs(diff))}
        </span>
    );
}

/** Sel skor satu aspek dengan warna heatmap. */
export function SectionCell({ value, min, max, title }: { value: number | null | undefined; min: number; max: number; title: string }) {
    return (
        <td className="p-0.5">
            <div title={`${title}: ${formatScore(value)}`} className={cn('flex h-8 min-w-12 items-center justify-center rounded-md text-xs font-bold tabular', heatTone(value, min, max))}>
                {formatScore(value)}
            </div>
        </td>
    );
}

/** Judul kolom aspek: kode beserta kata pertama nama aspek (nama lengkap di tooltip). */
export function SectionHeader({ section }: { section: { code: string; title: string } }) {
    return (
        <th scope="col" className="px-0.5 py-2 text-center align-bottom" title={section.title}>
            <div className="text-xs font-bold">{section.code}</div>
            <div className="text-[10px] leading-tight font-medium whitespace-nowrap">{section.title.split(' ')[0]}</div>
        </th>
    );
}

/** Keterangan kode aspek instrumen, ditampilkan di atas tabel skor per aspek. */
export function SectionLegend({ sections }: { sections: { code: string; title: string }[] }) {
    if (sections.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-1.5 text-xs">
            <span className="font-semibold text-muted-foreground">Kolom aspek:</span>
            {sections.map((section) => (
                <span key={section.code} className="inline-flex items-center gap-1.5 rounded-full bg-muted/70 py-0.5 pr-2.5 pl-1 ring-1 ring-border ring-inset">
                    <span className="flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-extrabold text-white">{section.code}</span>
                    {section.title}
                </span>
            ))}
            <span className="text-muted-foreground">· rata-rata skor butir pada aspek itu, warna makin gelap = makin tinggi.</span>
        </div>
    );
}
