import { Link, useForm } from '@inertiajs/react';
import { CalendarClock, Plus } from 'lucide-react';
import { useState } from 'react';
import { ReadinessBar } from '@/components/accreditation/readiness-bar';
import type { PeriodRow, ReadinessSummary } from '@/components/accreditation/types';
import { Combobox } from '@/components/combobox';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { deadlineLabel, deadlineTone, periodTone } from '@/lib/accreditation';
import { formatNumber, formatPercent } from '@/lib/format';
import type { Option } from '@/types';

export interface PeriodFormOptions {
    studyPrograms: Option[];
    versions: (Option & { body_id: number })[];
    picOptions: Option[];
}

interface Props extends PeriodFormOptions {
    periods: (PeriodRow & { summary: ReadinessSummary })[];
    filters: { status?: string | null; study_program_id?: string | null };
    statuses: Option[];
    can: { manage: boolean };
}

export default function PeriodIndex({ periods, filters, statuses, studyPrograms, versions, picOptions, can }: Props) {
    const params = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '');
    const [creating, setCreating] = useState(can.manage && params.get('create') === '1');
    const { filters: query, setFilter } = useQueryFilters({ status: filters.status ?? 'all', study_program_id: filters.study_program_id ?? 'all' });

    return (
        <>
            <PageHeader
                title="Periode Akreditasi"
                description="Setiap siklus (re)akreditasi prodi: instrumen LAM, tim penyusun, tenggat, bukti per indikator, hingga keputusan."
                breadcrumbs={[{ label: 'Akreditasi' }, { label: 'Periode' }]}
                actions={
                    can.manage && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus /> Periode baru
                        </Button>
                    )
                }
            />

            <div className="mb-4 flex flex-wrap gap-3">
                <SelectField value={query.status} onChange={(v) => setFilter('status', v)} options={statuses} allLabel="Semua tahap" className="w-56" />
                <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" className="w-72" />
            </div>

            {periods.length === 0 ? (
                <Card>
                    <EmptyState title="Belum ada periode akreditasi" description="Buat periode untuk mulai memetakan bukti per indikator instrumen LAM." />
                </Card>
            ) : (
                <div className="flex flex-col gap-3">
                    {periods.map((period) => (
                        <Link key={period.id} href={route('accreditation.periods.show', period.id)} className="grid grid-cols-1 gap-4 rounded-2xl border bg-card p-4 transition hover:border-primary/30 hover:shadow-sm md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_150px] md:items-center">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <StatusBadge tone={periodTone[period.status]}>{period.status_label}</StatusBadge>
                                    <span className="font-mono text-xs text-muted-foreground">{period.code}</span>
                                </div>
                                <div className="mt-1.5 truncate font-bold">{period.name}</div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {period.instrument} · PIC {period.pic ?? '—'}
                                </div>
                            </div>
                            <div>
                                <div className="mb-1.5 flex items-baseline justify-between text-xs">
                                    <span className="text-muted-foreground">
                                        {period.summary.ready}/{period.summary.total} indikator siap
                                    </span>
                                    <span className="text-base font-extrabold text-primary tabular">{formatPercent(period.summary.readiness, 0)}</span>
                                </div>
                                <ReadinessBar ready={period.summary.ready} partial={period.summary.partial} gap={period.summary.gap} />
                                <div className="mt-1.5 text-[11px] text-muted-foreground">
                                    {period.summary.estimated_grade ? `Estimasi ${period.summary.estimated_grade} (${formatNumber(period.summary.estimated_score)})` : 'Estimasi menunggu ≥ 50% indikator dinilai'} · {formatPercent(period.summary.coverage, 0)} dinilai
                                </div>
                            </div>
                            <div className="md:text-right">
                                {period.days_to_deadline !== null ? (
                                    <StatusBadge tone={deadlineTone(period.days_to_deadline)} dot={false}>
                                        <CalendarClock className="size-3" /> {deadlineLabel(period.days_to_deadline)}
                                    </StatusBadge>
                                ) : (
                                    <span className="text-xs text-muted-foreground">{period.status === 'decided' ? 'Selesai' : '—'}</span>
                                )}
                            </div>
                        </Link>
                    ))}
                </div>
            )}

            {creating && (
                <PeriodForm
                    studyPrograms={studyPrograms}
                    versions={versions}
                    picOptions={picOptions}
                    defaultProgram={params.get('study_program_id')}
                    onClose={() => setCreating(false)}
                />
            )}
        </>
    );
}

export interface EditablePeriod {
    id: number;
    study_program_id: number;
    instrument_version_id: number;
    name: string;
    pic_user_id: number | null;
    starts_on: string | null;
    submission_deadline: string | null;
    visit_on: string | null;
    target_score: number | null;
    notes: string | null;
}

export function PeriodForm({ period, studyPrograms, versions, picOptions, defaultProgram, onClose }: PeriodFormOptions & { period?: EditablePeriod; defaultProgram?: string | null; onClose: () => void }) {
    const form = useForm({
        study_program_id: period ? String(period.study_program_id) : (defaultProgram ?? ''),
        instrument_version_id: period ? String(period.instrument_version_id) : '',
        name: period?.name ?? '',
        pic_user_id: period?.pic_user_id ? String(period.pic_user_id) : '',
        starts_on: period?.starts_on ?? '',
        submission_deadline: period?.submission_deadline ?? '',
        visit_on: period?.visit_on ?? '',
        target_score: period?.target_score ?? '',
        notes: period?.notes ?? '',
    });

    const programLabel = studyPrograms.find((p) => String(p.value) === form.data.study_program_id)?.label;

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (period) form.put(route('accreditation.periods.update', period.id), options);
        else form.post(route('accreditation.periods.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={period ? 'Ubah periode akreditasi' : 'Periode akreditasi baru'} description="Satu prodi hanya dapat memiliki satu periode yang sedang berjalan." onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Program studi" error={form.errors.study_program_id} required>
                    <Combobox
                        value={form.data.study_program_id}
                        onChange={(value) => {
                            form.setData((data) => ({ ...data, study_program_id: value, name: data.name || `Reakreditasi ${studyPrograms.find((p) => String(p.value) === value)?.label ?? ''}` }));
                        }}
                        options={studyPrograms}
                        placeholder="Pilih prodi"
                    />
                </FormField>
                <FormField label="Instrumen LAM (versi berlaku)" error={form.errors.instrument_version_id} required>
                    <SelectField value={form.data.instrument_version_id} onChange={(value) => form.setData('instrument_version_id', value)} options={versions} placeholder="Pilih instrumen" />
                </FormField>
                <FormField label="Nama periode" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder={`Reakreditasi ${programLabel ?? 'prodi'} 2027`} />
                </FormField>
                <FormField label="PIC / ketua tim penyusun" error={form.errors.pic_user_id}>
                    <Combobox value={form.data.pic_user_id} onChange={(value) => form.setData('pic_user_id', value)} options={picOptions} placeholder="Pilih PIC" />
                </FormField>
                <FormField label="Target skor (0–400, opsional)" error={form.errors.target_score}>
                    <Input type="number" value={form.data.target_score ?? ''} onChange={(e) => form.setData('target_score', e.target.value)} placeholder="301" />
                </FormField>
                <FormField label="Mulai persiapan" error={form.errors.starts_on}>
                    <Input type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
                </FormField>
                <FormField label="Batas pengajuan dokumen" error={form.errors.submission_deadline}>
                    <Input type="date" value={form.data.submission_deadline} onChange={(e) => form.setData('submission_deadline', e.target.value)} />
                </FormField>
                <FormField label="Rencana asesmen lapangan" error={form.errors.visit_on}>
                    <Input type="date" value={form.data.visit_on} onChange={(e) => form.setData('visit_on', e.target.value)} />
                </FormField>
                <FormField label="Catatan" error={form.errors.notes} className="sm:col-span-2">
                    <Textarea rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
