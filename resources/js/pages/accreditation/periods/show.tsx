import { Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Ban, CalendarClock, FileDown, Gauge, Gavel, ListChecks, Pencil, Target } from 'lucide-react';
import { useMemo, useState } from 'react';
import { IndicatorItem } from '@/components/accreditation/indicator-item';
import { ReadinessBar } from '@/components/accreditation/readiness-bar';
import type { CriterionStat, GapItem, IndicatorState, PeriodRow, ReadinessSummary } from '@/components/accreditation/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { EvidenceFormOptions } from '@/components/evidence/evidence-upload-dialog';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { StatusBadge } from '@/components/status-badge';
import { Stepper } from '@/components/stepper';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { deadlineLabel, deadlineTone, indicatorStatus, periodSteps, periodTone } from '@/lib/accreditation';
import { formatDate, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import { PeriodForm, type PeriodFormOptions } from './index';

interface Criterion {
    id: number;
    parent_id: number | null;
    code: string;
    title: string;
    description: string | null;
    weight: number;
    indicators: IndicatorState[];
}

interface Period extends PeriodRow {
    notes: string | null;
    sk_number: string | null;
    certificate_number: string | null;
    result_grade: string | null;
    result_score: number | null;
    target_score: number | null;
    pic_user_id: number | null;
    instrument_version_id: number;
    study_program_id: number;
    starts_on: string | null;
    submitted_on: string | null;
    visit_on: string | null;
    decided_on: string | null;
    valid_from: string | null;
    valid_until: string | null;
    current_status: string | null;
    current_valid_until: string | null;
    scale_max: number;
    thresholds: { label: string; min: number }[];
}

interface Props extends PeriodFormOptions {
    period: Period;
    summary: ReadinessSummary;
    byCriterion: CriterionStat[];
    gaps: GapItem[];
    criteria: Criterion[];
    evidenceOptions: EvidenceFormOptions;
    can: { manage: boolean; contribute: boolean };
}

type Filter = 'all' | 'gap' | 'partial' | 'ready' | 'essential' | 'unassessed';

const filters: { value: Filter; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'gap', label: 'Belum ada bukti' },
    { value: 'partial', label: 'Menunggu verifikasi' },
    { value: 'ready', label: 'Siap' },
    { value: 'essential', label: 'Syarat perlu' },
    { value: 'unassessed', label: 'Belum dinilai' },
];

export default function PeriodShow({ period, summary, byCriterion, gaps, criteria, evidenceOptions, studyPrograms, versions, picOptions, can }: Props) {
    const canViewReadiness = useCan()('accreditation.view');
    const [editing, setEditing] = useState(false);
    const [deciding, setDeciding] = useState(false);
    const [cancelling, setCancelling] = useState(false);
    const [filter, setFilter] = useState<Filter>('all');
    const [search, setSearch] = useState('');
    const [tab, setTab] = useState('indicators');
    const open = ['preparing', 'submitted', 'visitation'].includes(period.status);
    const nextStep = periodSteps[periodSteps.findIndex((s) => s.value === period.status) + 1];
    const nextAction: Record<string, string> = { preparing: 'Tandai diajukan', submitted: 'Mulai asesmen lapangan' };

    const roots = criteria.filter((c) => c.parent_id === null);
    const stats = useMemo(() => Object.fromEntries(byCriterion.map((c) => [c.id, c])), [byCriterion]);
    const matches = (indicator: IndicatorState) => {
        const term = search.trim().toLowerCase();
        if (term && !`${indicator.code} ${indicator.statement} ${indicator.evidence_hint ?? ''}`.toLowerCase().includes(term)) return false;
        if (filter === 'essential') return indicator.is_essential;
        if (filter === 'unassessed') return indicator.self_score === null;
        return filter === 'all' || indicator.status === filter;
    };

    return (
        <>
            <PageHeader
                title={period.name}
                breadcrumbs={[{ label: 'Periode Akreditasi', href: route('accreditation.periods.index') }, { label: period.code }]}
                meta={
                    <>
                        <StatusBadge tone={periodTone[period.status]}>{period.status_label}</StatusBadge>
                        <StatusBadge tone="gold" dot={false}>
                            {period.instrument}
                        </StatusBadge>
                        {open && period.submission_deadline && (
                            <StatusBadge tone={deadlineTone(period.days_to_deadline)} dot={false}>
                                <CalendarClock className="size-3" /> Batas pengajuan {formatDate(period.submission_deadline)} · {deadlineLabel(period.days_to_deadline)}
                            </StatusBadge>
                        )}
                        <span className="text-xs text-muted-foreground">PIC {period.pic ?? '—'}</span>
                    </>
                }
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <a href={route('accreditation.periods.report', period.id)}>
                                <FileDown /> Laporan PDF
                            </a>
                        </Button>
                        {can.manage && open && (
                            <>
                                <Button variant="outline" onClick={() => setEditing(true)}>
                                    <Pencil /> Ubah
                                </Button>
                                {period.status === 'visitation' ? (
                                    <Button onClick={() => setDeciding(true)}>
                                        <Gavel /> Catat keputusan
                                    </Button>
                                ) : (
                                    nextStep && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button>
                                                    {nextAction[period.status] ?? nextStep.label} <ArrowRight />
                                                </Button>
                                            }
                                            title={`Lanjut ke tahap "${nextStep.label}"?`}
                                            description={period.status === 'preparing' ? `Kesiapan dokumen saat ini ${formatPercent(summary.readiness, 0)} dengan ${summary.gap} indikator belum memiliki bukti.` : 'Tanggal tahap akan dicatat hari ini.'}
                                            href={route('accreditation.periods.advance', period.id)}
                                            method="post"
                                            destructive={false}
                                            confirmLabel="Lanjutkan"
                                        />
                                    )
                                )}
                                <Button variant="ghost" size="icon" onClick={() => setCancelling(true)} aria-label="Batalkan periode" title="Batalkan periode">
                                    <Ban className="text-muted-foreground" />
                                </Button>
                            </>
                        )}
                    </>
                }
            />

            {period.status !== 'cancelled' && (
                <Card className="mb-6">
                    <CardContent>
                        <Stepper steps={periodSteps} current={period.status} />
                    </CardContent>
                </Card>
            )}

            {period.status === 'decided' && (
                <Card className="mb-6 border-primary/30 bg-gradient-to-br from-secondary/70 to-card">
                    <CardContent className="flex flex-wrap items-center gap-x-10 gap-y-3">
                        <div>
                            <div className="text-[11px] font-semibold text-muted-foreground uppercase">Hasil akreditasi</div>
                            <div className="text-2xl font-extrabold text-primary">{period.result_grade}</div>
                        </div>
                        <Info label="Nilai" value={period.result_score !== null ? formatNumber(period.result_score) : null} />
                        <Info label="Nomor SK" value={period.sk_number} />
                        <Info label="Sertifikat" value={period.certificate_number} />
                        <Info label="Berlaku" value={`${formatDate(period.valid_from)} – ${formatDate(period.valid_until)}`} />
                    </CardContent>
                </Card>
            )}

            <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Card className="gap-3 py-5 xl:col-span-2">
                    <CardContent className="flex flex-col gap-3">
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <div className="flex items-center gap-1.5 text-[13px] font-semibold text-muted-foreground">
                                    <Gauge className="size-4 text-primary" /> Kesiapan dokumen
                                </div>
                                <div className="mt-1 text-5xl leading-none font-extrabold tracking-tight text-primary tabular">{formatPercent(summary.readiness, 0)}</div>
                            </div>
                            <div className="text-right text-xs text-muted-foreground">
                                <b className="text-foreground">{summary.ready}</b> dari {summary.total} indikator
                                <br />
                                memiliki bukti terverifikasi
                            </div>
                        </div>
                        <ReadinessBar ready={summary.ready} partial={summary.partial} gap={summary.gap} legend />
                    </CardContent>
                </Card>
                <Card className="gap-3 py-5">
                    <CardContent>
                        <div className="flex items-center gap-1.5 text-[13px] font-semibold text-muted-foreground">
                            <Target className="size-4 text-primary" /> Estimasi skor
                        </div>
                        <div className="mt-1 flex items-baseline gap-1.5">
                            <span className="text-3xl font-extrabold tabular">{summary.estimated_score !== null ? formatNumber(summary.estimated_score) : '—'}</span>
                            <span className="text-xs text-muted-foreground">/ 400</span>
                        </div>
                        <div className="mt-1 text-sm font-bold">{summary.estimated_grade ?? <span className="font-medium text-muted-foreground">Nilai ≥ 50% indikator untuk estimasi peringkat</span>}</div>
                        <Meter value={summary.coverage} severity="neutral" size="sm" className="mt-3" />
                        <div className="mt-1 text-[11px] text-muted-foreground">
                            {summary.assessed}/{summary.total} indikator dinilai{period.target_score ? ` · target ${formatNumber(period.target_score)}` : ''}
                        </div>
                    </CardContent>
                </Card>
                <Card className={cn('gap-3 py-5', summary.essential_unmet > 0 && 'border-destructive/40')}>
                    <CardContent>
                        <div className="flex items-center gap-1.5 text-[13px] font-semibold text-muted-foreground">
                            <AlertTriangle className={cn('size-4', summary.essential_unmet ? 'text-destructive' : 'text-primary')} /> Syarat perlu belum terpenuhi
                        </div>
                        <div className={cn('mt-1 text-3xl font-extrabold tabular', summary.essential_unmet && 'text-destructive')}>{summary.essential_unmet}</div>
                        <p className="mt-1 text-[11px] text-muted-foreground">Indikator esensial wajib memiliki bukti terverifikasi dan skor diri ≥ 2.</p>
                        {summary.essential_unmet > 0 && (
                            <Button
                                variant="link"
                                size="sm"
                                className="mt-1 h-auto p-0"
                                onClick={() => {
                                    setTab('indicators');
                                    setFilter('essential');
                                }}
                            >
                                Lihat indikator esensial →
                            </Button>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Tabs value={tab} onValueChange={setTab}>
                <TabsList className="mb-4">
                    <TabsTrigger value="indicators">Indikator & bukti</TabsTrigger>
                    <TabsTrigger value="criteria">Per kriteria</TabsTrigger>
                    <TabsTrigger value="gaps">
                        Gap analysis <span className="ml-1 rounded-full bg-danger-soft px-1.5 text-[11px] font-bold text-danger-foreground">{gaps.length}</span>
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="indicators">
                    <div className="grid grid-cols-1 gap-6 xl:grid-cols-[260px_minmax(0,1fr)]">
                        <nav className="hidden xl:block">
                            <div className="sticky top-20 flex flex-col gap-1 rounded-2xl border bg-card p-2">
                                {roots.map((root) => {
                                    const stat = stats[root.id];
                                    return (
                                        <a key={root.id} href={`#kriteria-${root.id}`} className="rounded-lg px-3 py-2 text-[13px] transition hover:bg-muted">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="truncate">
                                                    <b className="text-primary">{root.code}</b> {root.title}
                                                </span>
                                                <span className="shrink-0 text-xs font-bold tabular">{formatPercent(stat?.readiness, 0)}</span>
                                            </div>
                                            {stat && <ReadinessBar ready={stat.ready} partial={stat.partial} gap={stat.gap} size="sm" className="mt-1.5" />}
                                        </a>
                                    );
                                })}
                            </div>
                        </nav>
                        <div className="flex min-w-0 flex-col gap-6">
                            <div className="flex flex-wrap items-center gap-2">
                                <SearchInput value={search} onChange={setSearch} placeholder="Cari indikator atau bukti…" />
                                <div className="flex flex-wrap gap-1">
                                    {filters.map((item) => (
                                        <button
                                            key={item.value}
                                            type="button"
                                            onClick={() => setFilter(item.value)}
                                            className={cn('rounded-full border px-3 py-1 text-xs font-semibold transition', filter === item.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:border-primary/40')}
                                        >
                                            {item.label}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            {roots.map((root) => {
                                const groups = [root, ...criteria.filter((c) => c.parent_id === root.id)];
                                const visible = groups.flatMap((g) => g.indicators).filter(matches);
                                const stat = stats[root.id];
                                if (visible.length === 0 && (filter !== 'all' || search)) return null;

                                return (
                                    <section key={root.id} id={`kriteria-${root.id}`} className="scroll-mt-20">
                                        <div className="mb-3 flex flex-wrap items-end justify-between gap-2">
                                            <div>
                                                <h2 className="text-lg font-bold">
                                                    <span className="text-primary">{root.code}</span> {root.title}
                                                </h2>
                                                <div className="text-xs text-muted-foreground">
                                                    Bobot {formatNumber(root.weight)}% · {stat?.ready ?? 0}/{stat?.total ?? 0} siap
                                                    {stat?.self_average !== null && stat?.self_average !== undefined && ` · skor diri rata-rata ${formatNumber(stat.self_average, 2)}`}
                                                </div>
                                            </div>
                                            <span className="text-sm font-extrabold text-primary tabular">{formatPercent(stat?.readiness, 0)}</span>
                                        </div>
                                        <div className="flex flex-col gap-4">
                                            {groups.map((group) => {
                                                const items = group.indicators.filter(matches);
                                                if (items.length === 0) return null;
                                                return (
                                                    <div key={group.id} className="flex flex-col gap-2">
                                                        {group.parent_id !== null && (
                                                            <div className="mt-1 text-[12px] font-bold text-muted-foreground uppercase">
                                                                {group.code} · {group.title}
                                                            </div>
                                                        )}
                                                        {items.map((indicator) => (
                                                            <IndicatorItem
                                                                key={indicator.id}
                                                                indicator={indicator}
                                                                periodId={period.id}
                                                                scaleMax={period.scale_max}
                                                                canContribute={can.contribute}
                                                                evidenceOptions={evidenceOptions}
                                                                defaultUnit={`study_program:${period.study_program_id}`}
                                                            />
                                                        ))}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </section>
                                );
                            })}
                        </div>
                    </div>
                </TabsContent>

                <TabsContent value="criteria">
                    <Card>
                        <CardHeader>
                            <CardTitle>Rekap per kriteria</CardTitle>
                            <CardDescription>Kesiapan dokumen dan rata-rata skor penilaian diri (tertimbang bobot indikator).</CardDescription>
                        </CardHeader>
                        <CardContent className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-[11px] text-muted-foreground uppercase">
                                        <th className="py-2 pr-3 font-semibold">Kriteria</th>
                                        <th className="px-3 py-2 text-right font-semibold">Bobot</th>
                                        <th className="w-[34%] px-3 py-2 font-semibold">Kesiapan dokumen</th>
                                        <th className="px-3 py-2 text-right font-semibold">Siap</th>
                                        <th className="w-[20%] px-3 py-2 font-semibold">Skor diri</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {byCriterion.map((row) => (
                                        <tr key={row.id} className="border-b last:border-0">
                                            <td className="py-3 pr-3">
                                                <b className="text-primary">{row.code}</b> {row.title}
                                            </td>
                                            <td className="px-3 text-right tabular">{formatNumber(row.weight)}%</td>
                                            <td className="px-3">
                                                <div className="flex items-center gap-3">
                                                    <ReadinessBar ready={row.ready} partial={row.partial} gap={row.gap} className="flex-1" />
                                                    <span className="w-10 text-right font-bold tabular">{formatPercent(row.readiness, 0)}</span>
                                                </div>
                                            </td>
                                            <td className="px-3 text-right tabular">
                                                {row.ready}/{row.total}
                                            </td>
                                            <td className="px-3">
                                                <div className="flex items-center gap-2">
                                                    <Meter value={row.self_percent} severity="neutral" size="sm" className="flex-1" />
                                                    <span className="w-9 text-right font-bold tabular">{row.self_average !== null ? formatNumber(row.self_average, 2) : '—'}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <p className="mt-4 text-[11px] text-muted-foreground">
                                Ambang estimasi peringkat: {period.thresholds.map((t) => `${t.label} ≥ ${formatNumber(t.min)}`).join(' · ')}. Estimasi bersifat indikatif, bukan hasil resmi asesmen LAM.
                            </p>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="gaps">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <ListChecks className="size-4 text-primary" /> Checklist dokumen yang belum siap
                            </CardTitle>
                            <CardDescription>Urut per kriteria. Bagikan ke tim penyusun sebagai daftar kerja — tersedia juga dalam laporan PDF.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {gaps.length === 0 && <p className="rounded-xl bg-secondary/60 p-4 text-sm text-primary">Seluruh indikator telah memiliki bukti terverifikasi.</p>}
                            {gaps.map((gap) => (
                                <div key={gap.id} className="flex items-start gap-3 rounded-xl border px-4 py-3">
                                    <span className="mt-0.5 w-10 shrink-0 font-mono text-xs font-bold text-primary">{gap.code}</span>
                                    <div className="min-w-0 flex-1">
                                        <p className="text-[14px]">{gap.statement}</p>
                                        {gap.evidence_hint && <p className="mt-0.5 text-xs text-muted-foreground">Bukti: {gap.evidence_hint}</p>}
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-1">
                                        <StatusBadge tone={indicatorStatus[gap.status].tone}>{indicatorStatus[gap.status].label}</StatusBadge>
                                        {gap.is_essential && !gap.essential_met && (
                                            <StatusBadge tone="danger" dot={false}>
                                                Syarat perlu
                                            </StatusBadge>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>

            {editing && (
                <PeriodForm
                    period={period}
                    studyPrograms={studyPrograms}
                    versions={versions}
                    picOptions={picOptions}
                    onClose={() => setEditing(false)}
                />
            )}
            {deciding && <DecisionDialog period={period} onClose={() => setDeciding(false)} />}
            {cancelling && <CancelDialog id={period.id} onClose={() => setCancelling(false)} />}
            {!open && period.status === 'cancelled' && period.notes && <p className="mt-6 text-sm whitespace-pre-line text-muted-foreground">{period.notes}</p>}
            <div className="mt-8 text-xs text-muted-foreground">
                Akreditasi prodi saat ini: <b>{period.current_status ?? '—'}</b> · berlaku s.d. {formatDate(period.current_valid_until)}
                {canViewReadiness && (
                    <>
                        {' · '}
                        <Link href={route('accreditation.readiness')} className="font-semibold text-primary hover:underline">
                            Lihat kesiapan seluruh prodi
                        </Link>
                    </>
                )}
            </div>
        </>
    );
}

function Info({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <div className="text-[11px] font-semibold text-muted-foreground uppercase">{label}</div>
            <div className="font-semibold">{value ?? '—'}</div>
        </div>
    );
}

function DecisionDialog({ period, onClose }: { period: Period; onClose: () => void }) {
    const form = useForm({
        result_grade: '',
        result_score: '' as string | number,
        sk_number: '',
        certificate_number: '',
        decided_on: new Date().toISOString().slice(0, 10),
        valid_from: new Date().toISOString().slice(0, 10),
        valid_until: '',
    });

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title="Catat keputusan akreditasi"
            description="Status & masa berlaku akreditasi prodi akan diperbarui otomatis, dan periode ditutup."
            onSubmit={() => form.post(route('accreditation.periods.decide', period.id), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
            submitLabel="Simpan keputusan"
            size="lg"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Peringkat" error={form.errors.result_grade} required>
                    <Input list="grades" value={form.data.result_grade} onChange={(e) => form.setData('result_grade', e.target.value)} placeholder="Unggul / Baik Sekali / Baik" />
                    <datalist id="grades">
                        {period.thresholds.map((t) => (
                            <option key={t.label} value={t.label} />
                        ))}
                    </datalist>
                </FormField>
                <FormField label="Nilai akreditasi" error={form.errors.result_score}>
                    <Input type="number" value={form.data.result_score} onChange={(e) => form.setData('result_score', e.target.value)} />
                </FormField>
                <FormField label="Nomor SK" error={form.errors.sk_number}>
                    <Input value={form.data.sk_number} onChange={(e) => form.setData('sk_number', e.target.value)} />
                </FormField>
                <FormField label="Nomor sertifikat" error={form.errors.certificate_number}>
                    <Input value={form.data.certificate_number} onChange={(e) => form.setData('certificate_number', e.target.value)} />
                </FormField>
                <FormField label="Tanggal keputusan" error={form.errors.decided_on} required>
                    <Input type="date" value={form.data.decided_on} onChange={(e) => form.setData('decided_on', e.target.value)} />
                </FormField>
                <FormField label="Berlaku mulai" error={form.errors.valid_from}>
                    <Input type="date" value={form.data.valid_from} onChange={(e) => form.setData('valid_from', e.target.value)} />
                </FormField>
                <FormField label="Berlaku sampai" error={form.errors.valid_until} required>
                    <Input type="date" value={form.data.valid_until} onChange={(e) => form.setData('valid_until', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}

function CancelDialog({ id, onClose }: { id: number; onClose: () => void }) {
    const form = useForm({ reason: '' });

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title="Batalkan periode akreditasi?" onSubmit={() => form.post(route('accreditation.periods.cancel', id), { onSuccess: onClose })} processing={form.processing} submitLabel="Batalkan periode">
            <FormField label="Alasan" error={form.errors.reason} required>
                <Textarea rows={3} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}
