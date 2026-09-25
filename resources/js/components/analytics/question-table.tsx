import { ArrowDownWideNarrow, ListOrdered } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DistributionBar, DistributionLegend } from '@/components/charts/distribution-bar';
import { ClassBadge } from '@/components/charts/score-badge';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatScore } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { QuestionStat } from './types';

/**
 * Analisis per butir/indikator: skor, klasifikasi, dan sebaran jawaban. Butir di bawah ambang ditandai.
 */
export function QuestionTable({ questions, threshold }: { questions: QuestionStat[]; threshold: number }) {
    const [order, setOrder] = useState<'instrument' | 'lowest'>('instrument');
    const rows = useMemo(
        () => (order === 'lowest' ? [...questions].sort((a, b) => (a.score ?? 99) - (b.score ?? 99)) : questions),
        [questions, order],
    );
    const legend = questions.find((q) => q.distribution.length > 0)?.distribution.map((d) => d.label) ?? [];

    return (
        <div>
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <DistributionLegend labels={legend} />
                <ToggleGroup type="single" value={order} onValueChange={(v) => v && setOrder(v as typeof order)} variant="outline" size="sm">
                    <ToggleGroupItem value="instrument" className="gap-1.5 px-3">
                        <ListOrdered className="size-3.5" /> Urutan instrumen
                    </ToggleGroupItem>
                    <ToggleGroupItem value="lowest" className="gap-1.5 px-3">
                        <ArrowDownWideNarrow className="size-3.5" /> Terendah dulu
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>
            <div className="divide-y rounded-xl border">
                {rows.map((question) => {
                    const below = question.score !== null && question.score < threshold;
                    return (
                        <div key={question.id} className={cn('grid gap-3 p-3.5 md:grid-cols-[minmax(0,1fr)_220px_110px] md:items-center', below && 'bg-danger-soft/40')}>
                            <div className="flex min-w-0 gap-3">
                                <span className="mt-0.5 w-8 shrink-0 font-mono text-xs font-bold text-primary">{question.code}</span>
                                <div className="min-w-0">
                                    <p className="text-[13px] leading-snug font-medium">{question.label}</p>
                                    {question.indicator && <p className="mt-0.5 text-[11px] text-muted-foreground">Indikator: {question.indicator}</p>}
                                </div>
                            </div>
                            <DistributionBar segments={question.distribution} />
                            <div className="flex items-center justify-end gap-2">
                                <ClassBadge classification={question.classification} className="hidden xl:inline-flex" />
                                <span className={cn('text-base font-extrabold tabular', below && 'text-destructive')}>{formatScore(question.score)}</span>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
