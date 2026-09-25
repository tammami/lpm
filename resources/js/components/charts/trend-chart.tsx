import { useEffect, useId, useState } from 'react';
import { Area, CartesianGrid, ComposedChart, Line, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatScore } from '@/lib/format';

interface Point {
    label: string;
    score: number | null;
    responses: number;
}

/**
 * Tren skor lintas periode: garis 2px bergradien di atas area teal yang memudar, titik 8px bercincin, satu sumbu Y, crosshair + tooltip.
 */
export function TrendChart({ data, min, max, threshold, height = 240 }: { data: Point[]; min: number; max: number; threshold?: number; height?: number }) {
    // Rapatkan domain ke sebaran data (kelipatan 0,5) agar perubahan terbaca, tetap dalam skala instrumen.
    const values = data.map((d) => d.score).filter((v): v is number => v !== null);
    const lower = values.length ? Math.max(min, Math.floor((Math.min(...values, threshold ?? Infinity) - 0.5) * 2) / 2) : min;
    const upper = values.length ? Math.min(max, Math.ceil((Math.max(...values) + 0.5) * 2) / 2) : max;
    const id = useId().replace(/:/g, '');
    const narrow = useNarrow();
    // Di layar sempit, "Ganjil 2025/2026" diringkas menjadi "Ganjil 25/26" agar label sumbu tidak bertabrakan.
    const tickLabel = (label: string) => (narrow ? label.replace(/\b\d{2}(\d{2})\/\d{2}(\d{2})\b/, '$1/$2') : label);

    return (
        <ResponsiveContainer width="100%" height={height}>
            <ComposedChart data={data} margin={{ top: 16, right: 36, bottom: 0, left: -18 }}>
                <defs>
                    <linearGradient id={`area-${id}`} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="var(--chart-1)" stopOpacity={0.28} />
                        <stop offset="100%" stopColor="var(--chart-1)" stopOpacity={0} />
                    </linearGradient>
                    <linearGradient id={`line-${id}`} x1="0" y1="0" x2="1" y2="0">
                        <stop offset="0%" stopColor="var(--teal-deep)" />
                        <stop offset="100%" stopColor="var(--chart-1)" />
                    </linearGradient>
                </defs>
                <CartesianGrid stroke="var(--border)" vertical={false} />
                <XAxis dataKey="label" interval={0} tickFormatter={tickLabel} padding={{ left: narrow ? 16 : 28, right: narrow ? 16 : 28 }} tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }} dy={6} />
                <YAxis domain={[lower, upper]} tickLine={false} axisLine={false} tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }} tickFormatter={(v) => formatScore(v)} />
                {threshold !== undefined && <ReferenceLine y={threshold} stroke="var(--muted-foreground)" strokeOpacity={0.5} />}
                <Tooltip
                    cursor={{ stroke: 'var(--foreground)', strokeOpacity: 0.15, strokeWidth: 1 }}
                    content={({ active, payload }) =>
                        active && payload?.length ? (
                            <div className="rounded-xl border border-white/85 surface-glass-strong px-3 py-2 text-xs">
                                <div className="font-semibold">{payload[0].payload.label}</div>
                                <div className="mt-0.5 flex items-center gap-1.5">
                                    <span className="size-2 rounded-full bg-chart-1" /> Skor <b className="tabular">{formatScore(payload[0].payload.score)}</b>
                                </div>
                                <div className="text-muted-foreground">n = {payload[0].payload.responses} respons</div>
                            </div>
                        ) : null
                    }
                />
                <Area type="monotone" dataKey="score" stroke="none" fill={`url(#area-${id})`} connectNulls isAnimationActive={false} tooltipType="none" />
                <Line
                    type="monotone"
                    dataKey="score"
                    stroke={`url(#line-${id})`}
                    strokeWidth={2}
                    connectNulls
                    dot={{ r: 4.5, fill: 'var(--chart-1)', stroke: 'var(--dot-ring)', strokeWidth: 2 }}
                    activeDot={{ r: 6, fill: 'var(--chart-1)', stroke: 'var(--dot-ring)', strokeWidth: 2 }}
                    label={({ x, y, value, index }: { x?: number | string; y?: number | string; value?: unknown; index?: number }) =>
                        index === data.length - 1 && value !== null && value !== undefined ? (
                            <text x={Number(x)} y={Number(y) - 12} textAnchor="middle" fontSize={12} fontWeight={700} fill="var(--foreground)">
                                {formatScore(Number(value))}
                            </text>
                        ) : (
                            <g />
                        )
                    }
                />
            </ComposedChart>
        </ResponsiveContainer>
    );
}

function useNarrow(query = '(max-width: 640px)') {
    const [narrow, setNarrow] = useState(() => typeof window !== 'undefined' && window.matchMedia(query).matches);

    useEffect(() => {
        const media = window.matchMedia(query);
        const update = () => setNarrow(media.matches);
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, [query]);

    return narrow;
}
