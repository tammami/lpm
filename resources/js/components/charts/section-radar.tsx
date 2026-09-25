import { Legend, PolarAngleAxis, PolarGrid, PolarRadiusAxis, Radar, RadarChart, ResponsiveContainer, Tooltip } from 'recharts';
import { formatScore } from '@/lib/format';

interface Props {
    data: { code: string; title: string; score: number | null; benchmark?: number | null }[];
    max: number;
    min?: number;
    seriesLabel: string;
    benchmarkLabel?: string;
}

/**
 * Radar skor per bagian (maks. 2 seri: subjek vs pembanding) dengan legenda.
 */
export function SectionRadar({ data, max, min = 0, seriesLabel, benchmarkLabel }: Props) {
    const values = data.flatMap((d) => [d.score, d.benchmark]).filter((v): v is number => v !== null && v !== undefined);
    const lower = values.length ? Math.max(min, Math.floor(Math.min(...values)) - 1) : min;

    return (
        <ResponsiveContainer width="100%" height={300}>
            <RadarChart data={data} outerRadius="72%">
                <PolarGrid stroke="var(--border)" />
                <PolarAngleAxis dataKey="code" tick={{ fontSize: 12, fontWeight: 700, fill: 'var(--foreground)' }} />
                <PolarRadiusAxis domain={[lower, max]} tick={false} axisLine={false} />
                {benchmarkLabel && (
                    <Radar name={benchmarkLabel} dataKey="benchmark" stroke="var(--chart-2)" strokeWidth={2} fill="var(--chart-2)" fillOpacity={0.08} dot={{ r: 3, fill: 'var(--chart-2)' }} />
                )}
                <Radar name={seriesLabel} dataKey="score" stroke="var(--chart-1)" strokeWidth={2} fill="var(--chart-1)" fillOpacity={0.12} dot={{ r: 3.5, fill: 'var(--chart-1)' }} />
                <Tooltip
                    content={({ active, payload }) =>
                        active && payload?.length ? (
                            <div className="rounded-xl border border-white/85 surface-glass-strong px-3 py-2 text-xs">
                                <div className="font-semibold">
                                    {payload[0].payload.code}. {payload[0].payload.title}
                                </div>
                                {payload.map((item) => (
                                    <div key={String(item.name)} className="mt-0.5 flex items-center gap-1.5">
                                        <span className="size-2 rounded-full" style={{ background: item.color }} /> {item.name}: <b className="tabular">{formatScore(item.value as number)}</b>
                                    </div>
                                ))}
                            </div>
                        ) : null
                    }
                />
                {benchmarkLabel && <Legend iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 12 }} />}
            </RadarChart>
        </ResponsiveContainer>
    );
}
