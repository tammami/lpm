import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ChevronDown, CircleDashed, Clock3, Save } from 'lucide-react';
import { useState } from 'react';
import type { EvidenceFormOptions } from '@/components/evidence/evidence-upload-dialog';
import { LinkedEvidenceList } from '@/components/evidence/linked-evidence';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Textarea } from '@/components/ui/textarea';
import { indicatorStatus } from '@/lib/accreditation';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { IndicatorState } from './types';

const icons = {
    ready: <CheckCircle2 className="size-5 text-primary" />,
    partial: <Clock3 className="size-5 text-warning" />,
    gap: <CircleDashed className="size-5 text-destructive/70" />,
};

interface Props {
    indicator: IndicatorState;
    periodId: number;
    scaleMax: number;
    canContribute: boolean;
    evidenceOptions: EvidenceFormOptions;
    defaultUnit: string;
    defaultOpen?: boolean;
}

/**
 * Satu indikator akreditasi: status bukti, bukti tertaut (dalam konteks periode), dan penilaian diri.
 */
export function IndicatorItem({ indicator, periodId, scaleMax, canContribute, evidenceOptions, defaultUnit, defaultOpen = false }: Props) {
    const [open, setOpen] = useState(defaultOpen);
    const [score, setScore] = useState<number | null>(indicator.self_score);
    const [notes, setNotes] = useState(indicator.notes ?? '');
    const [saving, setSaving] = useState(false);
    const dirty = score !== indicator.self_score || notes !== (indicator.notes ?? '');
    const scores = Array.from({ length: Math.floor(scaleMax) + 1 }, (_, i) => i);

    const save = (nextScore = score, nextNotes = notes) => {
        router.post(
            route('accreditation.periods.assess', periodId),
            { indicator_id: indicator.id, self_score: nextScore, notes: nextNotes || null },
            { preserveScroll: true, preserveState: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) },
        );
    };

    return (
        <Collapsible open={open} onOpenChange={setOpen} className={cn('rounded-xl border bg-card transition', open && 'shadow-sm ring-1 ring-primary/10')}>
            <CollapsibleTrigger className="flex w-full items-start gap-3 px-4 py-3 text-left">
                <span className="mt-0.5 shrink-0" aria-label={indicatorStatus[indicator.status].label}>
                    {icons[indicator.status]}
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-mono text-xs font-bold text-primary">{indicator.code}</span>
                        {indicator.is_essential && (
                            <StatusBadge tone={indicator.essential_met ? 'gold' : 'danger'} dot={false} className="h-5 text-[11px]">
                                {!indicator.essential_met && <AlertTriangle className="size-3" />} Syarat perlu
                            </StatusBadge>
                        )}
                        <span className="text-[11px] text-muted-foreground">
                            {indicator.evidence_count} bukti{indicator.verified_count > 0 && ` · ${indicator.verified_count} terverifikasi`}
                        </span>
                    </div>
                    <p className={cn('mt-0.5 text-[14px] leading-snug', !open && 'line-clamp-2')}>{indicator.statement}</p>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <span
                        className={cn(
                            'flex h-7 min-w-10 items-center justify-center rounded-lg px-2 text-xs font-extrabold tabular',
                            indicator.self_score === null ? 'bg-muted text-muted-foreground' : indicator.self_score >= scaleMax * 0.75 ? 'bg-secondary text-primary' : indicator.self_score >= scaleMax * 0.5 ? 'bg-warning-soft text-gold-foreground' : 'bg-danger-soft text-danger-foreground',
                        )}
                        title="Skor penilaian diri"
                    >
                        {indicator.self_score === null ? '—' : formatNumber(indicator.self_score, 1)}
                    </span>
                    <ChevronDown className={cn('size-4 text-muted-foreground transition', open && 'rotate-180')} />
                </div>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="grid grid-cols-1 gap-5 border-t px-4 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                    <div className="flex flex-col gap-3">
                        {(indicator.target || indicator.evidence_hint) && (
                            <dl className="grid gap-2 rounded-xl bg-muted/40 p-3 text-[13px]">
                                {indicator.target && (
                                    <div>
                                        <dt className="text-[11px] font-semibold text-muted-foreground uppercase">Target / kondisi yang diharapkan</dt>
                                        <dd>{indicator.target}</dd>
                                    </div>
                                )}
                                {indicator.evidence_hint && (
                                    <div>
                                        <dt className="text-[11px] font-semibold text-muted-foreground uppercase">Bukti yang dibutuhkan</dt>
                                        <dd>{indicator.evidence_hint}</dd>
                                    </div>
                                )}
                            </dl>
                        )}
                        <LinkedEvidenceList
                            items={indicator.evidence}
                            mapTo={{ type: 'accreditation_indicator', id: indicator.id, context: periodId, label: `${indicator.code} — ${indicator.statement.slice(0, 80)}` }}
                            options={evidenceOptions}
                            canAttach={canContribute}
                            defaultUnit={defaultUnit}
                            emptyText="Belum ada bukti untuk indikator ini pada periode ini."
                            compact
                        />
                    </div>
                    <div className="flex flex-col gap-3">
                        <div>
                            <div className="mb-2 text-[11px] font-semibold text-muted-foreground uppercase">Penilaian diri (0–{scaleMax})</div>
                            <div className="flex flex-wrap gap-1.5">
                                {scores.map((value) => (
                                    <button
                                        key={value}
                                        type="button"
                                        disabled={!canContribute || saving}
                                        onClick={() => setScore(score === value ? null : value)}
                                        className={cn(
                                            'size-10 rounded-xl border text-sm font-bold transition',
                                            score === value ? 'border-primary bg-primary text-primary-foreground shadow-sm' : 'bg-card hover:border-primary/40 hover:bg-secondary/40',
                                            !canContribute && 'cursor-not-allowed opacity-70',
                                        )}
                                    >
                                        {value}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <Textarea rows={3} value={notes} disabled={!canContribute} onChange={(e) => setNotes(e.target.value)} placeholder="Catatan analisis: kondisi saat ini, kekurangan, rencana pemenuhan…" />
                        {canContribute && (
                            <div className="flex justify-end">
                                <Button size="sm" onClick={() => save()} disabled={!dirty || saving}>
                                    <Save /> {saving ? 'Menyimpan…' : 'Simpan penilaian'}
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
