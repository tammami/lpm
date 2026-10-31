import { formatScore } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Props {
    sections: { code: string; title: string }[];
    rows: { id: number; name: string; cells: Record<string, number | null> }[];
    min: number;
    max: number;
    onSelectRow?: (id: number) => void;
    format?: (value: number | null | undefined) => string;
}

const ramp = ['bg-seq-1', 'bg-seq-2', 'bg-seq-3', 'bg-seq-4', 'bg-seq-5', 'bg-seq-6'];

/** Kelas warna sel heatmap untuk satu nilai (abu-abu bila kosong). */
export function heatTone(value: number | null | undefined, min: number, max: number) {
    if (value === null || value === undefined) return 'bg-muted text-muted-foreground';
    const level = Math.min(ramp.length - 1, Math.max(0, Math.floor(((value - min) / (max - min)) * ramp.length)));
    return cn(ramp[level], level >= 4 ? 'text-white' : level >= 2 ? 'text-seq-ink' : 'text-foreground');
}

/**
 * Heatmap prodi × bagian dengan ramp sekuensial satu hue (terang → gelap = rendah → tinggi).
 */
export function Heatmap({ sections, rows, min, max, onSelectRow, format = formatScore }: Props) {
    return (
        <div className="relative overflow-x-auto rounded-lg focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none" tabIndex={0} role="region" aria-label="Peta skor (geser ke samping bila perlu)">
            <table className="w-full border-separate border-spacing-[3px] text-xs">
                <thead>
                    <tr>
                        <th scope="col" className="w-48 px-2 text-left font-semibold text-muted-foreground">
                            <span className="sr-only">Nama</span>
                        </th>
                        {sections.map((section) => (
                            <th key={section.code} scope="col" className="px-1 pb-1 text-center font-bold text-muted-foreground" title={section.title}>
                                {section.code}
                                <div className="truncate text-[11px] font-medium">{section.title.split(' ')[0]}</div>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id}>
                            <th scope="row" className="max-w-48 truncate px-2 text-left font-semibold">
                                {onSelectRow ? (
                                    <button type="button" onClick={() => onSelectRow(row.id)} className="min-h-6 truncate rounded hover:text-primary focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none">
                                        {row.name}
                                    </button>
                                ) : (
                                    row.name
                                )}
                            </th>
                            {sections.map((section) => {
                                const value = row.cells[section.code];
                                return (
                                    <td
                                        key={section.code}
                                        title={`${row.name} · ${section.title}: ${format(value)}`}
                                        className={cn('h-10 min-w-14 rounded-md text-center font-bold tabular transition hover:ring-2 hover:ring-foreground/20', heatTone(value, min, max))}
                                    >
                                        {format(value)}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
            <div className="mt-3 flex items-center gap-2 text-[11px] text-muted-foreground">
                <span>{format(min)}</span>
                <div className="flex overflow-hidden rounded">
                    {ramp.map((cls) => (
                        <span key={cls} className={cn('h-2.5 w-7', cls)} />
                    ))}
                </div>
                <span>{format(max)}</span>
            </div>
        </div>
    );
}
