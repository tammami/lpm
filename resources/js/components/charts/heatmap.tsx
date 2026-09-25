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

/**
 * Heatmap prodi × bagian dengan ramp sekuensial satu hue (terang → gelap = rendah → tinggi).
 */
export function Heatmap({ sections, rows, min, max, onSelectRow, format = formatScore }: Props) {
    const step = (value: number) => Math.min(ramp.length - 1, Math.max(0, Math.floor(((value - min) / (max - min)) * ramp.length)));

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-separate border-spacing-[3px] text-xs">
                <thead>
                    <tr>
                        <th className="w-48 px-2 text-left font-semibold text-muted-foreground" />
                        {sections.map((section) => (
                            <th key={section.code} className="px-1 pb-1 text-center font-bold text-muted-foreground" title={section.title}>
                                {section.code}
                                <div className="truncate text-[11px] font-medium">{section.title.split(' ')[0]}</div>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.id}>
                            <th className="max-w-48 truncate px-2 text-left font-semibold">
                                {onSelectRow ? (
                                    <button type="button" onClick={() => onSelectRow(row.id)} className="truncate hover:text-primary">
                                        {row.name}
                                    </button>
                                ) : (
                                    row.name
                                )}
                            </th>
                            {sections.map((section) => {
                                const value = row.cells[section.code];
                                const level = value === null || value === undefined ? -1 : step(value);
                                return (
                                    <td
                                        key={section.code}
                                        title={`${row.name} · ${section.title}: ${format(value)}`}
                                        className={cn(
                                            'h-10 min-w-14 rounded-md text-center font-bold tabular transition hover:ring-2 hover:ring-foreground/20',
                                            level < 0 ? 'bg-muted text-muted-foreground' : ramp[level],
                                            level >= 3 ? 'text-white' : 'text-foreground',
                                        )}
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
