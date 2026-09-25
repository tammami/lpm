import { Link } from '@inertiajs/react';
import { CalendarDays, MapPin, Pencil, Plus, ShieldAlert, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { AuditForm } from '@/components/ami/audit-form';
import type { AuditFormOptions, AuditSummary } from '@/components/ami/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { auditTone, programTone } from '@/lib/ami';
import { formatDate } from '@/lib/format';
import { router } from '@inertiajs/react';
import type { Option } from '@/types';
import { ProgramForm } from './index';

interface Props extends AuditFormOptions {
    program: { id: number; code: string; name: string; year: number; scope: string | null; objective: string | null; criteria: string | null; academic_period_id: number | null; status: string; status_label: string; period: string | null; starts_on: string | null; ends_on: string | null };
    audits: AuditSummary[];
    statuses: Option[];
    periods: Option[];
    canManage: boolean;
}

export default function ProgramShow({ program, audits, statuses, periods, canManage, ...options }: Props) {
    const [scheduling, setScheduling] = useState(false);
    const [editing, setEditing] = useState(false);
    const completed = audits.filter((a) => a.status === 'completed').length;
    const open = audits.reduce((sum, a) => sum + a.open_findings_count, 0);

    return (
        <>
            <PageHeader
                title={program.name}
                breadcrumbs={[{ label: 'Program Audit', href: route('ami.programs.index') }, { label: program.code }]}
                meta={
                    <>
                        <StatusBadge tone={programTone[program.status]}>{program.status_label}</StatusBadge>
                        <StatusBadge tone="neutral" dot={false}>
                            {formatDate(program.starts_on)} – {formatDate(program.ends_on)}
                        </StatusBadge>
                    </>
                }
                actions={
                    canManage && (
                        <>
                            <SelectField value={program.status} onChange={(v) => router.post(route('ami.programs.transition', program.id), { status: v }, { preserveScroll: true })} options={statuses} className="w-40" />
                            <Button variant="outline" onClick={() => setEditing(true)}>
                                <Pencil /> Ubah
                            </Button>
                            {audits.length === 0 && (
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="ghost" size="icon" aria-label="Hapus">
                                            <Trash2 className="text-destructive" />
                                        </Button>
                                    }
                                    title="Hapus program?"
                                    href={route('ami.programs.destroy', program.id)}
                                    confirmLabel="Hapus"
                                />
                            )}
                            <Button onClick={() => setScheduling(true)}>
                                <Plus /> Jadwalkan audit
                            </Button>
                        </>
                    )
                }
            />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatTile label="Audit dijadwalkan" value={audits.length} icon={CalendarDays} tone="info" />
                <StatTile label="Audit selesai" value={`${completed}/${audits.length}`} icon={Users} />
                <StatTile label="Temuan belum tuntas" value={open} icon={ShieldAlert} tone={open ? 'danger' : 'neutral'} />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div className="flex flex-col gap-3">
                    {audits.length === 0 && (
                        <Card>
                            <EmptyState icon={CalendarDays} title="Belum ada jadwal audit" description="Jadwalkan audit untuk setiap prodi/fakultas/unit yang menjadi auditee." />
                        </Card>
                    )}
                    {audits.map((audit) => (
                        <Link key={audit.id} href={route('ami.audits.show', audit.id)} className="group grid grid-cols-1 gap-3 rounded-2xl border bg-card p-4 transition hover:border-primary/30 hover:shadow-sm sm:grid-cols-[88px_minmax(0,1fr)_auto] sm:items-center">
                            <div className="flex flex-col items-center justify-center rounded-xl bg-secondary py-2 text-secondary-foreground">
                                <span className="text-[11px] font-semibold uppercase">{audit.scheduled_on ? formatDate(audit.scheduled_on, 'MMM') : '—'}</span>
                                <span className="text-2xl leading-none font-extrabold">{audit.scheduled_on ? formatDate(audit.scheduled_on, 'd') : '?'}</span>
                            </div>
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="font-bold group-hover:text-primary">{audit.auditee_name}</span>
                                    <StatusBadge tone={auditTone[audit.status]}>{audit.status_label}</StatusBadge>
                                </div>
                                <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                    <span className="font-mono">{audit.code}</span>
                                    <span className="inline-flex items-center gap-1">
                                        <Users className="size-3" /> {audit.lead_auditor ?? '—'}
                                        {audit.auditors.length > 1 && ` +${audit.auditors.length - 1}`}
                                    </span>
                                    {audit.location && (
                                        <span className="inline-flex items-center gap-1">
                                            <MapPin className="size-3" /> {audit.location}
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="text-right text-xs">
                                <div className="font-bold">{audit.findings_count} temuan</div>
                                {audit.open_findings_count > 0 && <div className="text-destructive">{audit.open_findings_count} belum tuntas</div>}
                            </div>
                        </Link>
                    ))}
                </div>
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Rencana audit</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4 text-sm">
                        <Block label="Tujuan" value={program.objective} />
                        <Block label="Lingkup" value={program.scope} />
                        <Block label="Kriteria" value={program.criteria} />
                        <Block label="Periode akademik" value={program.period} />
                    </CardContent>
                </Card>
            </div>

            {scheduling && <AuditForm programId={program.id} audit={null} options={options} onClose={() => setScheduling(false)} />}
            {editing && <ProgramForm program={program} periods={periods} onClose={() => setEditing(false)} />}
        </>
    );
}

function Block({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <div className="text-[11px] font-semibold text-muted-foreground uppercase">{label}</div>
            <div className="mt-0.5 whitespace-pre-line">{value ?? '—'}</div>
        </div>
    );
}
