import { Link, useForm } from '@inertiajs/react';
import { CalendarRange, ClipboardCheck, Plus, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { PaginationBar } from '@/components/pagination-bar';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { programTone } from '@/lib/ami';
import { formatDate } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Program {
    id: number;
    code: string;
    name: string;
    year: number;
    scope: string | null;
    objective: string | null;
    status: string;
    status_label: string;
    period: string | null;
    starts_on: string | null;
    ends_on: string | null;
    audits_total: number;
    audits_completed: number;
    findings_total: number;
    findings_open: number;
}

export default function ProgramsIndex({ programs, periods, canManage }: { programs: Paginated<Program>; periods: Option[]; canManage: boolean }) {
    const [creating, setCreating] = useState(false);

    return (
        <>
            <PageHeader
                title="Program Audit Mutu Internal"
                description="Siklus AMI tahunan: rencana audit, pelaksanaan per auditee, temuan, tindakan koreksi, hingga verifikasi."
                breadcrumbs={[{ label: 'Audit Mutu Internal' }, { label: 'Program Audit' }]}
                actions={
                    canManage && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus /> Program baru
                        </Button>
                    )
                }
            />
            {programs.data.length === 0 ? (
                <div className="rounded-2xl border bg-card">
                    <EmptyState icon={ClipboardCheck} title="Belum ada program AMI" description="Buat program audit tahunan, lalu jadwalkan audit untuk setiap prodi/unit." />
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {programs.data.map((program) => {
                        const progress = program.audits_total ? Math.round((program.audits_completed / program.audits_total) * 100) : 0;
                        return (
                            <Link key={program.id} href={route('ami.programs.show', program.id)} className="group flex flex-col gap-4 rounded-2xl border bg-card p-5 transition hover:border-primary/30 hover:shadow-md">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <div className="font-mono text-xs text-muted-foreground">{program.code}</div>
                                        <h3 className="mt-1 text-lg font-bold group-hover:text-primary">{program.name}</h3>
                                        <div className="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <CalendarRange className="size-3.5" /> {formatDate(program.starts_on)} – {formatDate(program.ends_on)}
                                        </div>
                                    </div>
                                    <StatusBadge tone={programTone[program.status]}>{program.status_label}</StatusBadge>
                                </div>
                                {program.objective && <p className="line-clamp-2 text-sm text-muted-foreground">{program.objective}</p>}
                                <div>
                                    <div className="mb-1.5 flex justify-between text-xs">
                                        <span className="font-semibold">
                                            {program.audits_completed}/{program.audits_total} audit selesai
                                        </span>
                                        <span className="tabular">{progress}%</span>
                                    </div>
                                    <Meter value={progress} severity="good" />
                                </div>
                                <div className="flex gap-4 border-t pt-3 text-xs">
                                    <span className="inline-flex items-center gap-1.5">
                                        <ShieldAlert className="size-3.5 text-destructive" /> <b>{program.findings_open}</b> temuan terbuka
                                    </span>
                                    <span className="text-muted-foreground">{program.findings_total} total temuan</span>
                                </div>
                            </Link>
                        );
                    })}
                </div>
            )}
            {programs.last_page > 1 && (
                <div className="mt-4 rounded-2xl border bg-card">
                    <PaginationBar meta={programs} />
                </div>
            )}
            {creating && <ProgramForm periods={periods} onClose={() => setCreating(false)} />}
        </>
    );
}

export function ProgramForm({ program, periods, onClose }: { program?: { id: number; name: string; year: number; academic_period_id: number | null; scope: string | null; objective: string | null; criteria: string | null; starts_on: string | null; ends_on: string | null }; periods: Option[]; onClose: () => void }) {
    const form = useForm({
        name: program?.name ?? `AMI Tahun ${new Date().getFullYear()}`,
        year: program?.year ?? new Date().getFullYear(),
        academic_period_id: program?.academic_period_id ? String(program.academic_period_id) : '',
        scope: program?.scope ?? '',
        objective: program?.objective ?? '',
        criteria: program?.criteria ?? '',
        starts_on: program?.starts_on ?? '',
        ends_on: program?.ends_on ?? '',
    });
    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (program) form.put(route('ami.programs.update', program.id), options);
        else form.post(route('ami.programs.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={program ? 'Ubah program audit' : 'Program audit baru'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <FormField label="Nama program" error={form.errors.name} required className="sm:col-span-3">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Tahun" error={form.errors.year} required>
                    <Input type="number" value={form.data.year} onChange={(e) => form.setData('year', Number(e.target.value))} />
                </FormField>
                <FormField label="Periode akademik" className="sm:col-span-2">
                    <SelectField value={form.data.academic_period_id} onChange={(v) => form.setData('academic_period_id', v)} options={periods} placeholder="Opsional" />
                </FormField>
                <FormField label="Mulai" error={form.errors.starts_on}>
                    <Input type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
                </FormField>
                <FormField label="Selesai" error={form.errors.ends_on}>
                    <Input type="date" value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} />
                </FormField>
                <FormField label="Tujuan audit" className="sm:col-span-4">
                    <Textarea rows={2} value={form.data.objective} onChange={(e) => form.setData('objective', e.target.value)} placeholder="Memastikan standar SPMI dilaksanakan dan mengidentifikasi ketidaksesuaian serta peluang peningkatan." />
                </FormField>
                <FormField label="Lingkup" className="sm:col-span-2">
                    <Textarea rows={2} value={form.data.scope} onChange={(e) => form.setData('scope', e.target.value)} placeholder="Seluruh prodi FTK dan unit pendukung" />
                </FormField>
                <FormField label="Kriteria / standar acuan" className="sm:col-span-2">
                    <Textarea rows={2} value={form.data.criteria} onChange={(e) => form.setData('criteria', e.target.value)} placeholder="Standar SPMI 2026, SN-Dikti" />
                </FormField>
            </div>
        </FormDialog>
    );
}
