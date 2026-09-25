import { Link, router, useForm } from '@inertiajs/react';
import { AlarmClock, CheckCircle2, ClipboardList, Lock, Pencil, Plus, Send, ShieldCheck, Trash2, XCircle } from 'lucide-react';
import { useState } from 'react';
import { type FindingDraft, FindingForm, type FindingFormOptions } from '@/components/ami/finding-form';
import type { FindingRow } from '@/components/ami/types';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { EvidenceFormOptions } from '@/components/evidence/evidence-upload-dialog';
import { LinkedEvidenceList } from '@/components/evidence/linked-evidence';
import type { LinkedEvidence } from '@/components/evidence/types';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Stepper } from '@/components/stepper';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Slider } from '@/components/ui/slider';
import { Textarea } from '@/components/ui/textarea';
import { findingSteps, findingTone, severityTone } from '@/lib/ami';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

interface Finding extends FindingRow {
    description: string;
    criteria: string | null;
    effect: string | null;
    recommendation: string | null;
    question: { code: string; label: string } | null;
    standard_detail: { code: string; name: string; statement: string | null; indicator: string | null } | null;
    audit: { id: number; code: string; auditors: string[] } | null;
    creator: string | null;
    issued_at: string | null;
    verified_at: string | null;
    closed_at: string | null;
    created_at: string;
    requires_corrective_action: boolean;
    finding_severity_id: number;
    quality_standard_id: number | null;
    instrument_question_id: number | null;
    pic_user_id: number | null;
}

interface Action {
    id: number;
    root_cause: string;
    action_plan: string;
    preventive_action: string | null;
    progress: number;
    implementation_notes: string | null;
    root_cause_category_id: number | null;
    root_cause_category: string | null;
    pic_user_id: number;
    pic: string | null;
    status: string;
    status_label: string;
    due_date: string;
    completed_at: string | null;
    overdue: boolean;
    evidence: LinkedEvidence[];
}

interface Props extends Partial<Omit<FindingFormOptions, 'picOptions'>> {
    finding: Finding;
    actions: Action[];
    evidence: LinkedEvidence[];
    verifications: { decision: string; notes: string | null; verifier: string | null; verified_at: string }[];
    rootCauses: Option[];
    evidenceOptions: EvidenceFormOptions;
    picOptions: Option[];
    can: { edit: boolean; issue: boolean; respond: boolean; submit: boolean; verify: boolean; close: boolean; attach: boolean };
}

const actionTone: Record<string, 'neutral' | 'info' | 'warning' | 'success' | 'danger' | 'primary'> = {
    planned: 'neutral',
    in_progress: 'info',
    completed: 'warning',
    verified: 'success',
    rejected: 'danger',
};

export default function FindingShow({ finding, actions, evidence, verifications, rootCauses, evidenceOptions, picOptions, can, ...options }: Props) {
    const [editing, setEditing] = useState(false);
    const [actionForm, setActionForm] = useState<Action | 'new' | null>(null);
    const [verifying, setVerifying] = useState<'accepted' | 'rejected' | null>(null);
    const allDone = actions.length > 0 && actions.every((a) => a.status === 'completed' || a.status === 'verified');
    const unitKey = finding.audit ? undefined : null;

    return (
        <>
            <PageHeader
                title={finding.title}
                breadcrumbs={[{ label: 'Temuan', href: route('ami.findings.index') }, ...(finding.audit ? [{ label: finding.audit.code, href: route('ami.audits.show', finding.audit.id) }] : []), { label: finding.code }]}
                meta={
                    <>
                        <StatusBadge tone={severityTone[finding.severity_color] ?? 'neutral'}>{finding.severity}</StatusBadge>
                        <StatusBadge tone={findingTone[finding.status]}>{finding.status_label}</StatusBadge>
                        <StatusBadge tone={finding.overdue ? 'danger' : 'neutral'} dot={false}>
                            <AlarmClock className="size-3" /> Tenggat {formatDate(finding.due_date)}
                        </StatusBadge>
                        <StatusBadge tone="neutral" dot={false}>
                            {finding.auditee_name}
                        </StatusBadge>
                    </>
                }
                actions={
                    <>
                        {can.edit && (
                            <>
                                <Button variant="outline" onClick={() => setEditing(true)}>
                                    <Pencil /> Ubah
                                </Button>
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="ghost" size="icon" aria-label="Hapus">
                                            <Trash2 className="text-destructive" />
                                        </Button>
                                    }
                                    title="Hapus temuan draf?"
                                    href={route('ami.findings.destroy', finding.id)}
                                    confirmLabel="Hapus"
                                />
                            </>
                        )}
                        {can.issue && (
                            <ConfirmDialog
                                trigger={
                                    <Button>
                                        <Send /> Terbitkan ke auditee
                                    </Button>
                                }
                                title="Terbitkan temuan?"
                                description="PIC auditee akan diberi tahu dan wajib menyusun tindakan koreksi sebelum tenggat."
                                href={route('ami.findings.issue', finding.id)}
                                method="post"
                                destructive={false}
                                confirmLabel="Terbitkan"
                            />
                        )}
                        {can.submit && (
                            <ConfirmDialog
                                trigger={
                                    <Button disabled={!allDone}>
                                        <Send /> Ajukan verifikasi
                                    </Button>
                                }
                                title="Ajukan verifikasi ke auditor?"
                                description="Pastikan seluruh tindakan koreksi selesai (100%) dan buktinya telah dilampirkan."
                                href={route('ami.findings.submit', finding.id)}
                                method="post"
                                destructive={false}
                                confirmLabel="Ajukan"
                            />
                        )}
                        {can.verify && (
                            <>
                                <Button variant="outline" className="text-destructive" onClick={() => setVerifying('rejected')}>
                                    <XCircle /> Kembalikan
                                </Button>
                                <Button onClick={() => setVerifying('accepted')}>
                                    <ShieldCheck /> Verifikasi
                                </Button>
                            </>
                        )}
                        {can.close && (
                            <ConfirmDialog
                                trigger={
                                    <Button>
                                        <Lock /> Tutup temuan
                                    </Button>
                                }
                                title="Tutup temuan?"
                                description={finding.requires_corrective_action ? 'Tindak lanjut telah diverifikasi.' : 'Temuan kategori ini dapat ditutup tanpa tindakan koreksi.'}
                                href={route('ami.findings.close', finding.id)}
                                method="post"
                                destructive={false}
                                confirmLabel="Tutup"
                            />
                        )}
                    </>
                }
            />

            <Card className="mb-6">
                <CardContent>
                    <Stepper steps={findingSteps} current={finding.status} tone={finding.overdue ? 'danger' : 'primary'} />
                </CardContent>
            </Card>

            {verifications[0]?.decision === 'rejected' && finding.status === 'in_progress' && (
                <Alert className="mb-6 border-warning/40 bg-warning-soft">
                    <XCircle className="text-gold-foreground" />
                    <AlertTitle>Tindak lanjut dikembalikan auditor</AlertTitle>
                    <AlertDescription>{verifications[0].notes}</AlertDescription>
                </Alert>
            )}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardContent className="grid grid-cols-1 gap-5 md:grid-cols-2">
                            <Section label="Kondisi" tone="danger" text={finding.description} />
                            <Section label="Kriteria" text={finding.criteria} />
                            <Section label="Akibat / risiko" text={finding.effect} />
                            <Section label="Rekomendasi auditor" tone="primary" text={finding.recommendation} />
                        </CardContent>
                    </Card>

                    <Card className="gap-0 py-0">
                        <div className="flex flex-col gap-3 border-b p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 className="flex items-center gap-2 font-bold">
                                    <ClipboardList className="size-4 text-primary" /> Tindakan koreksi
                                </h3>
                                <p className="text-xs text-muted-foreground">Akar masalah → tindakan → PIC → tenggat → bukti → verifikasi.</p>
                            </div>
                            {can.respond && (
                                <Button onClick={() => setActionForm('new')}>
                                    <Plus /> Tambah tindakan
                                </Button>
                            )}
                        </div>
                        {actions.length === 0 ? (
                            <p className="px-6 py-10 text-center text-sm text-muted-foreground">
                                {finding.status === 'open' ? 'Temuan belum diterbitkan ke auditee.' : finding.requires_corrective_action ? 'Belum ada tindakan koreksi.' : 'Temuan kategori ini tidak mewajibkan tindakan koreksi.'}
                            </p>
                        ) : (
                            <div className="divide-y">
                                {actions.map((action, index) => (
                                    <div key={action.id} className="p-5">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="flex size-6 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground">{index + 1}</span>
                                            <StatusBadge tone={actionTone[action.status]}>{action.status_label}</StatusBadge>
                                            {action.root_cause_category && <StatusBadge tone="neutral" dot={false}>{action.root_cause_category}</StatusBadge>}
                                            <span className={cn('ml-auto text-xs', action.overdue ? 'font-semibold text-destructive' : 'text-muted-foreground')}>
                                                {action.pic} · tenggat {formatDate(action.due_date)}
                                            </span>
                                        </div>
                                        <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <Section label="Akar masalah" text={action.root_cause} compact />
                                            <Section label="Tindakan koreksi" text={action.action_plan} compact />
                                            {action.preventive_action && <Section label="Tindakan pencegahan" text={action.preventive_action} compact />}
                                            {action.implementation_notes && <Section label="Catatan pelaksanaan" text={action.implementation_notes} compact />}
                                        </div>
                                        <div className="mt-3 flex items-center gap-3">
                                            <Meter value={action.progress} severity="good" className="flex-1" />
                                            <span className="w-10 text-right text-xs font-bold tabular">{action.progress}%</span>
                                            {can.respond && (
                                                <Button size="sm" variant="outline" onClick={() => setActionForm(action)}>
                                                    <Pencil /> Perbarui
                                                </Button>
                                            )}
                                        </div>
                                        <div className="mt-4">
                                            <div className="mb-2 text-[11px] font-semibold text-muted-foreground uppercase">Bukti pelaksanaan</div>
                                            <LinkedEvidenceList
                                                items={action.evidence}
                                                mapTo={{ type: 'corrective_action', id: action.id, label: `Tindakan ${index + 1} — ${finding.code}` }}
                                                options={evidenceOptions}
                                                canAttach={can.respond}
                                                defaultUnit={unitKey}
                                                emptyText="Belum ada bukti. Unggah dokumen/foto pelaksanaan."
                                                compact
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>

                <aside className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm">
                            <Info label="Kode" value={finding.code} />
                            <Info label="Auditee" value={finding.auditee_name} />
                            <Info label="PIC auditee" value={finding.pic} />
                            {finding.standard_detail && (
                                <div className="rounded-xl bg-secondary/60 p-3">
                                    <div className="text-[11px] font-semibold text-secondary-foreground uppercase">Standar</div>
                                    <div className="font-semibold">
                                        {finding.standard_detail.code} — {finding.standard_detail.name}
                                    </div>
                                    {finding.standard_detail.statement && <p className="mt-1 text-xs text-muted-foreground">{finding.standard_detail.statement}</p>}
                                </div>
                            )}
                            {finding.question && <Info label="Butir instrumen" value={`${finding.question.code} — ${finding.question.label}`} />}
                            {finding.audit && (
                                <Info
                                    label="Audit"
                                    value={
                                        <Link href={route('ami.audits.show', finding.audit.id)} className="text-primary hover:underline">
                                            {finding.audit.code} · {finding.audit.auditors.join(', ')}
                                        </Link>
                                    }
                                />
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Linimasa</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ol className="relative flex flex-col gap-4 border-l pl-5 text-sm">
                                <TimelineItem label="Dicatat auditor" date={finding.created_at} by={finding.creator} />
                                {finding.issued_at && <TimelineItem label="Diterbitkan ke auditee" date={finding.issued_at} />}
                                {verifications
                                    .slice()
                                    .reverse()
                                    .map((v, i) => (
                                        <TimelineItem key={i} label={v.decision === 'accepted' ? 'Tindak lanjut diverifikasi' : 'Dikembalikan untuk perbaikan'} date={v.verified_at} by={v.verifier} note={v.notes} tone={v.decision === 'accepted' ? 'primary' : 'danger'} />
                                    ))}
                                {finding.closed_at && <TimelineItem label="Temuan ditutup" date={finding.closed_at} tone="primary" />}
                            </ol>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Bukti temuan</CardTitle>
                            <CardDescription>Dokumen yang mendasari temuan auditor.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <LinkedEvidenceList items={evidence} mapTo={{ type: 'finding', id: finding.id, label: finding.code }} options={evidenceOptions} canAttach={can.attach} compact />
                        </CardContent>
                    </Card>
                </aside>
            </div>

            {editing && finding.audit && options.severityOptions && (
                <FindingForm auditId={finding.audit.id} draft={finding as unknown as FindingDraft} options={{ ...(options as FindingFormOptions), picOptions }} onClose={() => setEditing(false)} />
            )}
            {actionForm && <ActionDialog findingId={finding.id} action={actionForm === 'new' ? null : actionForm} rootCauses={rootCauses} picOptions={picOptions} defaultPic={finding.pic_user_id} onClose={() => setActionForm(null)} />}
            {verifying && <VerifyDialog findingId={finding.id} decision={verifying} onClose={() => setVerifying(null)} />}
        </>
    );
}

function Section({ label, text, tone, compact }: { label: string; text: string | null; tone?: 'danger' | 'primary'; compact?: boolean }) {
    return (
        <div className={cn(!compact && 'rounded-xl border p-4', tone === 'danger' && !compact && 'border-destructive/20 bg-danger-soft/30', tone === 'primary' && !compact && 'border-primary/20 bg-secondary/40')}>
            <div className="text-[11px] font-bold tracking-wide text-muted-foreground uppercase">{label}</div>
            <p className={cn('mt-1 whitespace-pre-line', compact ? 'text-[13px]' : 'text-sm')}>{text || '—'}</p>
        </div>
    );
}

function Info({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <div className="text-[11px] font-semibold text-muted-foreground uppercase">{label}</div>
            <div className="mt-0.5 font-medium">{value ?? '—'}</div>
        </div>
    );
}

function TimelineItem({ label, date, by, note, tone }: { label: string; date: string; by?: string | null; note?: string | null; tone?: 'primary' | 'danger' }) {
    return (
        <li className="relative">
            <span className={cn('absolute top-1 -left-[26px] size-3 rounded-full ring-4 ring-white', tone === 'danger' ? 'bg-destructive' : tone === 'primary' ? 'bg-primary' : 'bg-gold')} />
            <div className="font-semibold">{label}</div>
            <div className="text-xs text-muted-foreground">
                {formatDateTime(date)}
                {by ? ` · ${by}` : ''}
            </div>
            {note && <p className="mt-1 text-xs">“{note}”</p>}
        </li>
    );
}

function ActionDialog({ findingId, action, rootCauses, picOptions, defaultPic, onClose }: { findingId: number; action: Action | null; rootCauses: Option[]; picOptions: Option[]; defaultPic: number | null; onClose: () => void }) {
    const form = useForm({
        root_cause: action?.root_cause ?? '',
        root_cause_category_id: action?.root_cause_category_id ? String(action.root_cause_category_id) : '',
        action_plan: action?.action_plan ?? '',
        preventive_action: action?.preventive_action ?? '',
        pic_user_id: action ? String(action.pic_user_id) : defaultPic ? String(defaultPic) : '',
        due_date: action?.due_date ?? '',
        progress: action?.progress ?? 0,
        implementation_notes: action?.implementation_notes ?? '',
    });

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (action) form.put(route('ami.findings.actions.update', [findingId, action.id]), opts);
        else form.post(route('ami.findings.actions.store', findingId), opts);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={action ? 'Perbarui tindakan koreksi' : 'Tindakan koreksi baru'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Akar masalah" error={form.errors.root_cause} required className="sm:col-span-2" hint="Mengapa ketidaksesuaian terjadi? (gunakan 5-Why bila perlu)">
                    <Textarea rows={3} value={form.data.root_cause} onChange={(e) => form.setData('root_cause', e.target.value)} />
                </FormField>
                <FormField label="Kategori akar masalah" error={form.errors.root_cause_category_id}>
                    <SelectField value={form.data.root_cause_category_id} onChange={(v) => form.setData('root_cause_category_id', v)} options={rootCauses} placeholder="Pilih kategori" />
                </FormField>
                <FormField label="PIC tindakan" error={form.errors.pic_user_id} required>
                    <Combobox value={form.data.pic_user_id} onChange={(v) => form.setData('pic_user_id', v)} options={picOptions} placeholder="Pilih PIC" />
                </FormField>
                <FormField label="Tindakan koreksi" error={form.errors.action_plan} required className="sm:col-span-2">
                    <Textarea rows={3} value={form.data.action_plan} onChange={(e) => form.setData('action_plan', e.target.value)} />
                </FormField>
                <FormField label="Tindakan pencegahan" error={form.errors.preventive_action} className="sm:col-span-2">
                    <Textarea rows={2} value={form.data.preventive_action} onChange={(e) => form.setData('preventive_action', e.target.value)} />
                </FormField>
                <FormField label="Tenggat" error={form.errors.due_date} required>
                    <Input type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                </FormField>
                {action && (
                    <>
                        <FormField label={`Progres: ${form.data.progress}%`} error={form.errors.progress}>
                            <Slider value={[form.data.progress]} max={100} step={5} onValueChange={([v]) => form.setData('progress', v)} className="py-3" />
                        </FormField>
                        <FormField label="Catatan pelaksanaan" error={form.errors.implementation_notes} className="sm:col-span-2">
                            <Textarea rows={3} value={form.data.implementation_notes} onChange={(e) => form.setData('implementation_notes', e.target.value)} />
                        </FormField>
                    </>
                )}
            </div>
            {action && action.status === 'planned' && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="mt-3 text-destructive"
                    onClick={() => router.delete(route('ami.findings.actions.destroy', [findingId, action.id]), { preserveScroll: true, onSuccess: onClose })}
                >
                    <Trash2 /> Hapus tindakan ini
                </Button>
            )}
        </FormDialog>
    );
}

function VerifyDialog({ findingId, decision, onClose }: { findingId: number; decision: 'accepted' | 'rejected'; onClose: () => void }) {
    const form = useForm({ decision, notes: '' });

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title={decision === 'accepted' ? 'Verifikasi tindak lanjut' : 'Kembalikan untuk perbaikan'}
            description={decision === 'accepted' ? 'Tindakan koreksi dinyatakan efektif berdasarkan bukti.' : 'Auditee akan diminta memperbaiki tindakan koreksi.'}
            onSubmit={() => form.post(route('ami.findings.verify', findingId), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
            submitLabel={decision === 'accepted' ? 'Verifikasi' : 'Kembalikan'}
        >
            <FormField label={decision === 'accepted' ? 'Catatan verifikasi (opsional)' : 'Catatan perbaikan'} error={form.errors.notes} required={decision === 'rejected'}>
                <Textarea rows={4} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </FormField>
            {decision === 'accepted' && (
                <p className="mt-3 flex items-center gap-2 text-xs text-muted-foreground">
                    <CheckCircle2 className="size-3.5 text-primary" /> Temuan akan berstatus terverifikasi dan siap ditutup.
                </p>
            )}
        </FormDialog>
    );
}
