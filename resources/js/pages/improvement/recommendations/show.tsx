import { Link, router, useForm } from '@inertiajs/react';
import { Ban, CheckCircle2, CheckSquare, Lightbulb, Lock, Pencil, Plus, ShieldCheck, Square, Target, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { EvidenceFormOptions } from '@/components/evidence/evidence-upload-dialog';
import { LinkedEvidenceList } from '@/components/evidence/linked-evidence';
import type { LinkedEvidence } from '@/components/evidence/types';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Stepper } from '@/components/stepper';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Slider } from '@/components/ui/slider';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime, formatNumber } from '@/lib/format';
import { originTone, planTone, priorityTone, recommendationTone } from '@/lib/improvement';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';
import { type RecommendationRow, RecommendationForm } from './index';

interface Plan {
    id: number;
    title: string;
    description: string | null;
    target_output: string | null;
    pic_user_id: number;
    pic: string | null;
    budget: number | null;
    progress: number;
    implementation_notes: string | null;
    status: string;
    status_label: string;
    starts_on: string | null;
    due_date: string;
    overdue: boolean;
    tasks: { id: number; title: string; is_done: boolean; due_date: string | null }[];
    evidence: LinkedEvidence[];
    verifications: { decision: string; notes: string | null; verifier: string | null; verified_at: string }[];
    can_update: boolean;
}

interface Props {
    recommendation: RecommendationRow & { description: string; rationale: string | null; source: { label: string; url: string | null } | null; target: string | null; closed_at: string | null };
    plans: Plan[];
    targets: Option[];
    picOptions: Option[];
    priorityOptions: Option[];
    evidenceOptions: EvidenceFormOptions;
    can: { manage: boolean; verify: boolean; plan: boolean };
}

const flow = [
    { value: 'open', label: 'Rekomendasi' },
    { value: 'in_progress', label: 'Rencana & pelaksanaan' },
    { value: 'completed', label: 'Bukti' },
    { value: 'verified', label: 'Verifikasi' },
    { value: 'closed', label: 'Ditutup' },
];

export default function RecommendationShow({ recommendation, plans, targets, picOptions, priorityOptions, evidenceOptions, can }: Props) {
    const [editing, setEditing] = useState(false);
    const [planForm, setPlanForm] = useState<Plan | 'new' | null>(null);
    const [verifying, setVerifying] = useState<{ plan: Plan; decision: 'accepted' | 'rejected' } | null>(null);
    const [cancelling, setCancelling] = useState(false);

    return (
        <>
            <PageHeader
                title={recommendation.title}
                breadcrumbs={[{ label: 'Rekomendasi', href: route('improvement.recommendations.index') }, { label: recommendation.code }]}
                meta={
                    <>
                        <StatusBadge tone={recommendationTone[recommendation.status]}>{recommendation.status_label}</StatusBadge>
                        <StatusBadge tone={priorityTone[recommendation.priority]} dot={false}>
                            Prioritas {recommendation.priority_label}
                        </StatusBadge>
                        <StatusBadge tone={originTone[recommendation.origin]} dot={false}>
                            {recommendation.origin_label}
                        </StatusBadge>
                        <StatusBadge tone={recommendation.overdue ? 'danger' : 'neutral'} dot={false}>
                            Tenggat {formatDate(recommendation.due_date)}
                        </StatusBadge>
                    </>
                }
                actions={
                    can.manage &&
                    !['closed', 'cancelled'].includes(recommendation.status) && (
                        <>
                            <Button variant="outline" onClick={() => setEditing(true)}>
                                <Pencil /> Ubah
                            </Button>
                            <Button variant="ghost" className="text-muted-foreground" onClick={() => setCancelling(true)}>
                                <Ban /> Batalkan
                            </Button>
                            {recommendation.status === 'verified' && (
                                <ConfirmDialog
                                    trigger={
                                        <Button>
                                            <Lock /> Tutup rekomendasi
                                        </Button>
                                    }
                                    title="Tutup rekomendasi?"
                                    description="Seluruh rencana aksi telah terverifikasi. Siklus perbaikan selesai."
                                    href={route('improvement.recommendations.close', recommendation.id)}
                                    method="post"
                                    destructive={false}
                                    confirmLabel="Tutup"
                                />
                            )}
                        </>
                    )
                }
            />

            {recommendation.status !== 'cancelled' && (
                <Card className="mb-6">
                    <CardContent>
                        <Stepper steps={flow} current={recommendation.status} />
                    </CardContent>
                </Card>
            )}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div className="flex flex-col gap-6">
                    <Card className="border-gold/40 bg-gradient-to-br from-gold-soft/50 to-card">
                        <CardContent className="flex gap-4">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gold text-gold-foreground">
                                <Lightbulb className="size-5" />
                            </span>
                            <div>
                                <p className="text-[16px] leading-relaxed whitespace-pre-line">{recommendation.description}</p>
                                {recommendation.rationale && <p className="mt-2 text-sm font-medium text-gold-foreground">Dasar: {recommendation.rationale}</p>}
                                {recommendation.source?.url && (
                                    <Link href={recommendation.source.url} className="mt-2 inline-block text-sm font-semibold text-primary hover:underline">
                                        Lihat sumber: {recommendation.source.label} →
                                    </Link>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex items-center justify-between">
                        <h2 className="flex items-center gap-2 text-lg font-bold">
                            <Target className="size-5 text-primary" /> Rencana aksi
                        </h2>
                        {can.plan && !['closed', 'cancelled'].includes(recommendation.status) && (
                            <Button onClick={() => setPlanForm('new')}>
                                <Plus /> Tambah rencana aksi
                            </Button>
                        )}
                    </div>

                    {plans.length === 0 && (
                        <Card>
                            <CardContent className="py-10 text-center text-sm text-muted-foreground">Belum ada rencana aksi. Uraikan langkah konkret, PIC, dan tenggatnya.</CardContent>
                        </Card>
                    )}

                    {plans.map((plan) => (
                        <Card key={plan.id} className={cn(plan.overdue && 'border-destructive/40')}>
                            <CardContent className="flex flex-col gap-4">
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge tone={planTone[plan.status]}>{plan.status_label}</StatusBadge>
                                            {plan.overdue && <StatusBadge tone="danger">Lewat tenggat</StatusBadge>}
                                        </div>
                                        <h3 className="mt-2 text-[16px] font-bold">{plan.title}</h3>
                                        {plan.description && <p className="mt-0.5 text-sm text-muted-foreground">{plan.description}</p>}
                                        <div className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                            <span>PIC: <b className="text-foreground">{plan.pic}</b></span>
                                            <span>
                                                {plan.starts_on ? `${formatDate(plan.starts_on)} – ` : 'Tenggat '}
                                                {formatDate(plan.due_date)}
                                            </span>
                                            {plan.target_output && <span>Luaran: {plan.target_output}</span>}
                                            {plan.budget ? <span>Anggaran: Rp{formatNumber(plan.budget)}</span> : null}
                                        </div>
                                    </div>
                                    <div className="flex gap-2">
                                        {plan.can_update && plan.status !== 'verified' && (
                                            <Button variant="outline" size="sm" onClick={() => setPlanForm(plan)}>
                                                <Pencil /> Perbarui
                                            </Button>
                                        )}
                                        {can.verify && plan.status === 'completed' && (
                                            <>
                                                <Button size="sm" variant="outline" className="text-destructive" onClick={() => setVerifying({ plan, decision: 'rejected' })}>
                                                    <XCircle /> Kembalikan
                                                </Button>
                                                <Button size="sm" onClick={() => setVerifying({ plan, decision: 'accepted' })}>
                                                    <ShieldCheck /> Verifikasi
                                                </Button>
                                            </>
                                        )}
                                    </div>
                                </div>

                                <div className="flex items-center gap-3">
                                    <Meter value={plan.progress} severity="good" className="flex-1" />
                                    <span className="text-sm font-bold tabular">{plan.progress}%</span>
                                </div>

                                {plan.tasks.length > 0 && (
                                    <ul className="flex flex-col gap-1">
                                        {plan.tasks.map((task) => (
                                            <li key={task.id}>
                                                <button
                                                    type="button"
                                                    disabled={!plan.can_update || plan.status === 'verified'}
                                                    onClick={() => router.post(route('improvement.action-plans.tasks.toggle', [plan.id, task.id]), {}, { preserveScroll: true })}
                                                    className="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm hover:bg-muted/60 disabled:hover:bg-transparent"
                                                >
                                                    {task.is_done ? <CheckSquare className="size-4 text-primary" /> : <Square className="size-4 text-muted-foreground" />}
                                                    <span className={cn(task.is_done && 'text-muted-foreground line-through')}>{task.title}</span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {plan.can_update && plan.status !== 'verified' && <AddTask planId={plan.id} />}

                                {plan.implementation_notes && <p className="rounded-xl bg-muted/50 p-3 text-sm whitespace-pre-line">{plan.implementation_notes}</p>}

                                <div>
                                    <div className="mb-2 text-[11px] font-semibold text-muted-foreground uppercase">Bukti pelaksanaan</div>
                                    <LinkedEvidenceList
                                        items={plan.evidence}
                                        mapTo={{ type: 'action_plan', id: plan.id, label: plan.title }}
                                        options={evidenceOptions}
                                        canAttach={plan.can_update && plan.status !== 'verified'}
                                        defaultUnit={recommendation.target}
                                        compact
                                    />
                                </div>

                                {plan.verifications.length > 0 && (
                                    <div className="flex flex-col gap-1.5 border-t pt-3">
                                        {plan.verifications.map((v, i) => (
                                            <div key={i} className="flex items-start gap-2 text-xs">
                                                {v.decision === 'accepted' ? <CheckCircle2 className="size-4 text-primary" /> : <XCircle className="size-4 text-destructive" />}
                                                <span>
                                                    <b>{v.verifier}</b> {v.decision === 'accepted' ? 'memverifikasi' : 'mengembalikan'} · {formatDateTime(v.verified_at)}
                                                    {v.notes && <span className="block text-muted-foreground">“{v.notes}”</span>}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <aside className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ringkasan</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm">
                            <Info label="Kode" value={recommendation.code} />
                            <Info label="Sasaran" value={recommendation.target_name} />
                            <Info label="Indikator" value={recommendation.indicator} />
                            <Info label="PIC rekomendasi" value={recommendation.pic} />
                            <Info label="Dibuat" value={formatDateTime(recommendation.created_at)} />
                            {recommendation.closed_at && <Info label="Ditutup" value={formatDateTime(recommendation.closed_at)} />}
                        </CardContent>
                    </Card>
                </aside>
            </div>

            {editing && <RecommendationForm recommendation={recommendation} targets={targets} picOptions={picOptions} priorityOptions={priorityOptions} onClose={() => setEditing(false)} />}
            {planForm && <PlanDialog recommendationId={recommendation.id} plan={planForm === 'new' ? null : planForm} picOptions={picOptions} defaultPic={recommendation.pic_user_id} onClose={() => setPlanForm(null)} />}
            {verifying && <VerifyPlanDialog plan={verifying.plan} decision={verifying.decision} onClose={() => setVerifying(null)} />}
            {cancelling && <CancelDialog id={recommendation.id} onClose={() => setCancelling(false)} />}
        </>
    );
}

function Info({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <div className="text-[11px] font-semibold text-muted-foreground uppercase">{label}</div>
            <div className="mt-0.5 font-medium">{value ?? '—'}</div>
        </div>
    );
}

function AddTask({ planId }: { planId: number }) {
    const form = useForm({ title: '' });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('improvement.action-plans.tasks.store', planId), { preserveScroll: true, onSuccess: () => form.reset() });
            }}
            className="flex gap-2"
        >
            <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Tambah langkah/tugas…" className="h-8" />
            <Button size="sm" variant="ghost" disabled={!form.data.title || form.processing}>
                <Plus /> Tugas
            </Button>
        </form>
    );
}

function PlanDialog({ recommendationId, plan, picOptions, defaultPic, onClose }: { recommendationId: number; plan: Plan | null; picOptions: Option[]; defaultPic: number | null; onClose: () => void }) {
    const form = useForm({
        title: plan?.title ?? '',
        description: plan?.description ?? '',
        target_output: plan?.target_output ?? '',
        pic_user_id: plan ? String(plan.pic_user_id) : defaultPic ? String(defaultPic) : '',
        starts_on: plan?.starts_on ?? '',
        due_date: plan?.due_date ?? '',
        budget: plan?.budget ?? '',
        progress: plan?.progress ?? 0,
        implementation_notes: plan?.implementation_notes ?? '',
        tasks: ['', '', ''],
    });
    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (plan) form.put(route('improvement.action-plans.update', plan.id), opts);
        else form.post(route('improvement.action-plans.store', recommendationId), opts);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={plan ? 'Perbarui rencana aksi' : 'Rencana aksi baru'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Judul rencana aksi" error={form.errors.title} required className="sm:col-span-2">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Workshop umpan balik penilaian untuk dosen" />
                </FormField>
                <FormField label="Uraian" error={form.errors.description} className="sm:col-span-2">
                    <Textarea rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
                <FormField label="PIC" error={form.errors.pic_user_id} required>
                    <Combobox value={form.data.pic_user_id} onChange={(v) => form.setData('pic_user_id', v)} options={picOptions} placeholder="Pilih PIC" />
                </FormField>
                <FormField label="Luaran / target" error={form.errors.target_output}>
                    <Input value={form.data.target_output} onChange={(e) => form.setData('target_output', e.target.value)} placeholder="100% dosen memberi umpan balik" />
                </FormField>
                <FormField label="Mulai" error={form.errors.starts_on}>
                    <Input type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
                </FormField>
                <FormField label="Tenggat" error={form.errors.due_date} required>
                    <Input type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                </FormField>
                <FormField label="Anggaran (Rp, opsional)" error={form.errors.budget}>
                    <Input type="number" value={form.data.budget ?? ''} onChange={(e) => form.setData('budget', e.target.value)} />
                </FormField>
                {plan ? (
                    <>
                        <FormField label={`Progres: ${form.data.progress}%`} error={form.errors.progress}>
                            <Slider value={[form.data.progress]} max={100} step={5} onValueChange={([v]) => form.setData('progress', v)} className="py-3" />
                        </FormField>
                        <FormField label="Catatan pelaksanaan" error={form.errors.implementation_notes} className="sm:col-span-2">
                            <Textarea rows={3} value={form.data.implementation_notes} onChange={(e) => form.setData('implementation_notes', e.target.value)} />
                        </FormField>
                    </>
                ) : (
                    <FormField label="Langkah/tugas (opsional)" className="sm:col-span-2" hint="Progres dihitung otomatis dari tugas yang dicentang.">
                        <div className="flex flex-col gap-2">
                            {form.data.tasks.map((task, index) => (
                                <Input key={index} value={task} onChange={(e) => form.setData('tasks', form.data.tasks.map((t, i) => (i === index ? e.target.value : t)))} placeholder={`Langkah ${index + 1}`} />
                            ))}
                        </div>
                    </FormField>
                )}
            </div>
        </FormDialog>
    );
}

function VerifyPlanDialog({ plan, decision, onClose }: { plan: Plan; decision: 'accepted' | 'rejected'; onClose: () => void }) {
    const form = useForm({ decision, notes: '' });

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title={decision === 'accepted' ? 'Verifikasi rencana aksi' : 'Kembalikan rencana aksi'}
            description={plan.title}
            onSubmit={() => form.post(route('improvement.action-plans.verify', plan.id), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
            submitLabel={decision === 'accepted' ? 'Verifikasi' : 'Kembalikan'}
        >
            <FormField label={decision === 'accepted' ? 'Catatan (opsional)' : 'Catatan perbaikan'} error={form.errors.notes} required={decision === 'rejected'}>
                <Textarea rows={4} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}

function CancelDialog({ id, onClose }: { id: number; onClose: () => void }) {
    const form = useForm({ reason: '' });

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title="Batalkan rekomendasi?" onSubmit={() => form.post(route('improvement.recommendations.cancel', id), { onSuccess: onClose })} processing={form.processing} submitLabel="Batalkan rekomendasi">
            <FormField label="Alasan" error={form.errors.reason} required>
                <Textarea rows={3} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}
