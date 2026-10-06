import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatNumber } from '@/lib/format';

export interface StackGroup {
    key: string;
    label: string;
}

/** Urutan hue kategorikal tetap (tervalidasi CVD) — warna mengikuti kelompok, bukan peringkat. */
const colors = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

export const stackColor = (index: number) => colors[index % colors.length];

/**
 * Kolom bertumpuk per tahun: celah 2px antar segmen, ujung atas membulat, satu sumbu Y, tooltip per kolom.
 */
export function StackedYearChart({ data, groups, height = 260 }: { data: Record<string, number | string>[]; groups: StackGroup[]; height?: number }) {
    return (
        <div>
            <ResponsiveContainer width="100%" height={height}>
                <BarChart data={data} margin={{ top: 8, right: 8, bottom: 0, left: -24 }} barCategoryGap="28%">
                    <CartesianGrid stroke="var(--border)" vertical={false} />
                    <XAxis dataKey="year" tickLine={false} axisLine={false} tick={{ fontSize: 12, fill: 'var(--muted-foreground)' }} dy={6} />
                    <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }} />
                    <Tooltip
                        cursor={{ fill: 'var(--foreground)', fillOpacity: 0.04 }}
                        content={({ active, payload, label }) =>
                            active && payload?.length ? (
                                <div className="min-w-44 rounded-xl border border-white/85 surface-glass-strong px-3 py-2 text-xs">
                                    <div className="mb-1 font-semibold">Tahun {label}</div>
                                    {groups.map((group, index) => (
                                        <div key={group.key} className="flex items-center justify-between gap-4">
                                            <span className="inline-flex items-center gap-1.5">
                                                <span className="size-2 rounded-sm" style={{ background: stackColor(index) }} /> {group.label}
                                            </span>
                                            <b className="tabular">{formatNumber(Number(payload[0].payload[group.key] ?? 0))}</b>
                                        </div>
                                    ))}
                                    <div className="mt-1 flex justify-between border-t pt-1 text-muted-foreground">
                                        <span>Total · {payload[0].payload.lecturers} dosen</span>
                                        <b className="text-foreground tabular">{formatNumber(Number(payload[0].payload.total))}</b>
                                    </div>
                                </div>
                            ) : null
                        }
                    />
                    {groups.map((group, index) => (
                        <Bar
                            key={group.key}
                            dataKey={group.key}
                            name={group.label}
                            stackId="works"
                            fill={stackColor(index)}
                            stroke="var(--dot-ring)"
                            strokeWidth={2}
                            maxBarSize={56}
                            radius={index === groups.length - 1 ? [4, 4, 0, 0] : 0}
                            isAnimationActive={false}
                        />
                    ))}
                </BarChart>
            </ResponsiveContainer>
            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-muted-foreground">
                {groups.map((group, index) => (
                    <span key={group.key} className="inline-flex items-center gap-1.5">
                        <span className="size-2.5 rounded-sm" style={{ background: stackColor(index) }} />
                        {group.label}
                    </span>
                ))}
            </div>
        </div>
    );
}
