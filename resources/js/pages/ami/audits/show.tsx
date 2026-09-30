import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Ban, CalendarDays, FileText, FolderOpen, MapPin, MessageSquare, Pencil, Plus, ShieldAlert, UserRound, Users } from 'lucide-react';
import { useState } from 'react';
import { AuditForm } from '@/components/ami/audit-form';
import { type FindingDraft, FindingForm, type FindingFormOptions } from '@/components/ami/finding-form';
import type { AuditFormOptions, AuditSummary, FindingRow } from '@/components/ami/types';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { EvidenceFormOptions } from '@/components/evidence/evidence-upload-dialog';
import { LinkedEvidenceList } from '@/components/evidence/linked-evidence';
import type { LinkedEvidence } from '@/components/evidence/types';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import type { Question } from '@/components/questionnaire/question-input';
import { StatusBadge } from '@/components/status-badge';
import { Stepper } from '@/components/stepper';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { auditStages, auditTone, findingTone, severityTone } from '@/lib/ami';
import { formatDate, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

interface ChecklistQuestion extends Question {
    requires_evidence: boolean;
    standard: { id: number; code: string; name: string } | null;
    answer: { option_id: number | null; value_text: string | null; auditor_note: string | null; score: number | null } | null;
}

interface Props extends AuditFormOptions, Omit<FindingFormOptions, 'picOptions'> {
    audit: AuditSummary & { program: { id: number; code: string; name: string; year: number }; summary_text: string | null; strengths: string | null; conclusion: string | null; pic_email: string | null };
    checklist: { id: number; code: string; title: string; description: string | null; questions: ChecklistQuestion[] }[];
    compliance: { answered: number; total: number; score_percent: number | null; breakdown: { label: string; value: string; count: number }[]; by_standard: { code: string; name: string; score_percent: number | null; answered: number; total: number }[] };
    findings: FindingRow[];
    evidence: LinkedEvidence[];
    can: { work: boolean; manage: boolean; lead: boolean; attach: boolean };
    categories?: EvidenceFormOptions['categories'];
}

const optionStyle: Record<string, string> = {
    compliant: 'data-[on=true]:border-primary data-[on=true]:bg-primary data-[on=true]:text-primary-foreground',
    partial: 'data-[on=true]:border-warning data-[on=true]:bg-warning data-[on=true]:text-foreground',
    non_compliant: 'data-[on=true]:border-destructive data-[on=true]:bg-destructive data-[on=true]:text-white',
    na: 'data-[on=true]:border-muted-foreground data-[on=true]:bg-muted-foreground data-[on=true]:text-white',
};

export default function AuditShow(props: Props) {
    const canViewPrograms = useCan()('ami.view');
    const { audit, checklist, compliance, findings, evidence, can } = props;
    const [findingDraft, setFindingDraft] = useState<FindingDraft | null>(null);
    const [editing, setEditing] = useState(false);
    const editable = can.work && ['desk_review', 'field_audit', 'reporting'].includes(audit.status);
    const nextStage = auditStages[auditStages.findIndex((s) => s.value === audit.status) + 1];
    const percent = compliance.total ? Math.round((compliance.answered / compliance.total) * 100) : 0;
    const findingOptions: FindingFormOptions = { ...props, picOptions: props.picOptions };

    return (
        <>
            <PageHeader
                title={audit.auditee_name}
                breadcrumbs={[
                    { label: 'Program Audit', href: canViewPrograms ? route('ami.programs.index') : undefined },
                    { label: audit.program.name, href: canViewPrograms ? route('ami.programs.show', audit.program.id) : undefined },
                    { label: audit.code },
                ]}
                meta={
                    <>
                        <StatusBadge tone={auditTone[audit.status]}>{audit.status_label}</StatusBadge>
                        <StatusBadge tone="neutral" dot={false}>
                            <CalendarDays className="size-3" /> {formatDate(audit.scheduled_on)}
                        </StatusBadge>
                        {audit.location && (
                            <StatusBadge tone="neutral" dot={false}>
                                <MapPin className="size-3" /> {audit.location}
                            </StatusBadge>
                        )}
                    </>
                }
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <a href={route('ami.audits.report', audit.id)}>
                                <FileText /> Laporan PDF
                            </a>
                        </Button>
                        {can.manage && audit.status !== 'completed' && (
                            <Button variant="outline" onClick={() => setEditing(true)}>
                                <Pencil /> Jadwal
                            </Button>
                        )}
                        {can.manage && !['completed', 'cancelled'].includes(audit.status) && (
                            <ConfirmDialog
                                trigger={
                                    <Button variant="outline" className="text-destructive">
                                        <Ban /> Batalkan
                                    </Button>
                                }
                                title="Batalkan audit ini?"
                                description="Audit yang dibatalkan tidak dapat dilanjutkan lagi. Jawaban dan temuan yang sudah dicatat tetap tersimpan."
                                href={route('ami.audits.cancel', audit.id)}
                                method="post"
                                confirmLabel="Batalkan audit"
                            />
                        )}
                        {can.lead && nextStage && audit.status !== 'cancelled' && (
                            <ConfirmDialog
                                trigger={
                                    <Button>
                                        {nextStage.label} <ArrowRight />
                                    </Button>
                                }
                                title={`Lanjut ke tahap "${nextStage.label}"?`}
                                description={nextStage.value === 'completed' ? 'Pastikan kesimpulan telah diisi dan seluruh temuan sudah diterbitkan.' : 'Tim auditor dan auditee akan melihat tahap terbaru.'}
                                href={route('ami.audits.advance', audit.id)}
                                method="post"
                                destructive={false}
                                confirmLabel="Lanjutkan"
                            />
                        )}
                    </>
                }
            />

            <Card className="mb-6">
                <CardContent>
                    <Stepper steps={auditStages} current={audit.status} />
                </CardContent>
            </Card>

            {audit.status === 'planned' && can.work && (
                <Alert className="mb-6 border-info/30 bg-info-soft">
                    <AlertDescription className="text-info">Audit masih terjadwal. Ketua auditor dapat memulai tahap desk evaluation agar daftar tilik bisa diisi.</AlertDescription>
                </Alert>
            )}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <Tabs defaultValue="checklist" className="min-w-0">
                    <TabsList className="mb-4">
                        <TabsTrigger value="checklist">Daftar tilik</TabsTrigger>
                        <TabsTrigger value="findings">Temuan ({findings.length})</TabsTrigger>
                        <TabsTrigger value="documents">Dokumen auditee ({evidence.length})</TabsTrigger>
                        <TabsTrigger value="report">Laporan</TabsTrigger>
                    </TabsList>

                    <TabsContent value="checklist" className="flex flex-col gap-5">
                        {checklist.map((section) => (
                            <section key={section.id} className="overflow-hidden rounded-2xl border bg-card">
                                <header className="flex items-center gap-3 border-b bg-secondary/50 px-5 py-3">
                                    <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-xs font-extrabold text-white">{section.code}</span>
                                    <h3 className="font-bold">{section.title}</h3>
                                </header>
                                <div className="divide-y">
                                    {section.questions.map((question) => (
                                        <ChecklistItem
                                            key={question.id}
                                            auditId={audit.id}
                                            question={question}
                                            editable={editable}
                                            onFinding={() =>
                                                setFindingDraft({
                                                    title: '',
                                                    description: question.answer?.auditor_note ?? '',
                                                    criteria: question.label,
                                                    quality_standard_id: question.standard?.id,
                                                    instrument_question_id: question.id,
                                                    pic_user_id: audit.auditee_pic_user_id,
                                                })
                                            }
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}
                    </TabsContent>

                    <TabsContent value="findings">
                        <Card className="gap-0 py-0">
                            <div className="flex items-center justify-between border-b p-4">
                                <div>
                                    <h3 className="font-bold">Temuan audit</h3>
                                    <p className="text-xs text-muted-foreground">Temuan draf hanya terlihat oleh auditor sampai diterbitkan.</p>
                                </div>
                                {editable && (
                                    <Button onClick={() => setFindingDraft({ pic_user_id: audit.auditee_pic_user_id })}>
                                        <Plus /> Catat temuan
                                    </Button>
                                )}
                            </div>
                            {findings.length === 0 ? (
                                <p className="px-6 py-12 text-center text-sm text-muted-foreground">Belum ada temuan.</p>
                            ) : (
                                <ul className="divide-y">
                                    {findings.map((finding) => (
                                        <li key={finding.id}>
                                            <Link href={route('ami.findings.show', finding.id)} className="flex items-center gap-3 px-4 py-3.5 hover:bg-muted/40">
                                                <StatusBadge tone={severityTone[finding.severity_color] ?? 'neutral'} dot={false}>
                                                    {finding.severity_code}
                                                </StatusBadge>
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate text-sm font-semibold">{finding.title}</div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {finding.code} · {finding.standard ?? 'Tanpa standar'} · PIC {finding.pic ?? '—'}
                                                    </div>
                                                </div>
                                                <StatusBadge tone={findingTone[finding.status]}>{finding.status_label}</StatusBadge>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    </TabsContent>

                    <TabsContent value="documents">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FolderOpen className="size-4 text-primary" /> Dokumen desk evaluation
                                </CardTitle>
                                <CardDescription>
                                    Auditee mengunggah/menautkan dokumen pendukung {audit.desk_review_due ? `paling lambat ${formatDate(audit.desk_review_due)}` : 'sebelum audit lapangan'}.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <LinkedEvidenceList
                                    items={evidence}
                                    mapTo={{ type: 'audit', id: audit.id, label: `${audit.code} — ${audit.auditee_name}` }}
                                    options={props as unknown as EvidenceFormOptions}
                                    canAttach={can.attach}
                                    defaultUnit={audit.auditee_key}
                                    emptyText="Belum ada dokumen yang dilampirkan auditee."
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="report">
                        <ReportForm audit={audit} editable={can.work && audit.status !== 'completed'} />
                    </TabsContent>
                </Tabs>

                <aside className="flex flex-col gap-5">
                    <Card>
                        <CardHeader>
                            <CardTitle>Kepatuhan</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div>
                                <div className="flex items-end gap-1.5">
                                    <span className="text-4xl leading-none font-extrabold text-primary">{formatPercent(compliance.score_percent, 0)}</span>
                                    <span className="mb-1 text-xs text-muted-foreground">skor kepatuhan</span>
                                </div>
                                <div className="mt-3 mb-1 flex justify-between text-xs">
                                    <span>Butir dinilai</span>
                                    <span className="font-semibold tabular">
                                        {compliance.answered}/{compliance.total}
                                    </span>
                                </div>
                                <Meter value={percent} severity={percent === 100 ? 'good' : 'neutral'} />
                            </div>
                            <div className="grid grid-cols-2 gap-2">
                                {compliance.breakdown.map((item) => (
                                    <div key={item.value} className="rounded-lg bg-muted/50 px-2.5 py-2">
                                        <div className="text-lg font-extrabold tabular">{item.count}</div>
                                        <div className="truncate text-[11px] text-muted-foreground">{item.label.split(' (')[0]}</div>
                                    </div>
                                ))}
                            </div>
                            <div className="flex flex-col gap-2.5 border-t pt-3">
                                {compliance.by_standard.map((row) => (
                                    <div key={row.code}>
                                        <div className="mb-1 flex justify-between gap-2 text-xs">
                                            <span className="truncate" title={row.name}>
                                                <b>{row.code}</b> {row.name}
                                            </span>
                                            <span className="shrink-0 font-semibold tabular">{formatPercent(row.score_percent, 0)}</span>
                                        </div>
                                        <Meter value={row.score_percent} size="sm" />
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Tim & auditee</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3 text-sm">
                            {audit.auditors.map((auditor) => (
                                <div key={auditor.id} className="flex items-center gap-2">
                                    <Users className="size-4 text-primary" />
                                    <span className="flex-1">{auditor.name}</span>
                                    <StatusBadge tone={auditor.role === 'lead' ? 'gold' : 'neutral'} dot={false}>
                                        {auditor.role === 'lead' ? 'Ketua' : 'Anggota'}
                                    </StatusBadge>
                                </div>
                            ))}
                            <div className="flex items-center gap-2 border-t pt-3">
                                <UserRound className="size-4 text-muted-foreground" />
                                <span className="flex-1">
                                    <span className="block text-[11px] text-muted-foreground">PIC auditee</span>
                                    {audit.auditee_pic ?? '—'}
                                </span>
                            </div>
                            <div className="text-xs text-muted-foreground">Instrumen: {audit.instrument ?? '—'}</div>
                        </CardContent>
                    </Card>
                </aside>
            </div>

            {findingDraft && <FindingForm auditId={audit.id} draft={findingDraft} options={findingOptions} onClose={() => setFindingDraft(null)} />}
            {editing && <AuditForm programId={audit.program.id} audit={audit} options={props} onClose={() => setEditing(false)} />}
        </>
    );
}

function ChecklistItem({ auditId, question, editable, onFinding }: { auditId: number; question: ChecklistQuestion; editable: boolean; onFinding: () => void }) {
    const [selected, setSelected] = useState<number | null>(question.answer?.option_id ?? null);
    const [note, setNote] = useState(question.answer?.auditor_note ?? '');
    const [showNote, setShowNote] = useState(!!question.answer?.auditor_note);
    const selectedValue = question.options.find((o) => o.id === selected)?.value;

    const save = (optionId: number | null, auditorNote: string) =>
        router.post(route('ami.audits.answers.save', auditId), { instrument_question_id: question.id, instrument_question_option_id: optionId, auditor_note: auditorNote }, { preserveScroll: true, preserveState: true });

    return (
        <div className={cn('px-5 py-4', (selectedValue === 'non_compliant' || selectedValue === 'partial') && 'bg-danger-soft/25')}>
            <div className="flex gap-3">
                <span className="mt-0.5 w-8 shrink-0 font-mono text-xs font-bold text-primary">{question.code}</span>
                <div className="min-w-0 flex-1">
                    <p className="text-[14px] leading-snug font-medium">{question.label}</p>
                    <div className="mt-1.5 flex flex-wrap gap-1.5">
                        {question.standard && (
                            <span className="rounded-md bg-secondary px-1.5 py-0.5 text-[11px] font-semibold text-secondary-foreground" title={question.standard.name}>
                                {question.standard.code}
                            </span>
                        )}
                        {question.requires_evidence && <span className="rounded-md bg-gold-soft px-1.5 py-0.5 text-[11px] font-semibold text-gold-foreground">Perlu bukti</span>}
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {question.options.map((option) => (
                            <button
                                key={option.id}
                                type="button"
                                disabled={!editable}
                                data-on={selected === option.id}
                                onClick={() => {
                                    setSelected(option.id);
                                    save(option.id, note);
                                }}
                                className={cn(
                                    'rounded-lg border-2 px-3 py-1.5 text-xs font-bold transition disabled:cursor-not-allowed',
                                    selected !== option.id && 'bg-card hover:border-primary/40',
                                    optionStyle[option.value] ?? 'data-[on=true]:border-primary data-[on=true]:bg-primary data-[on=true]:text-primary-foreground',
                                )}
                            >
                                {option.label.split(' (')[0]}
                            </button>
                        ))}
                        {editable && (
                            <Button variant="ghost" size="sm" className="h-8 text-muted-foreground" onClick={() => setShowNote((v) => !v)}>
                                <MessageSquare /> Catatan
                            </Button>
                        )}
                        {editable && (selectedValue === 'non_compliant' || selectedValue === 'partial') && (
                            <Button variant="outline" size="sm" className="h-8 border-destructive/30 text-destructive" onClick={onFinding}>
                                <ShieldAlert /> Catat temuan
                            </Button>
                        )}
                    </div>
                    {(showNote || note) && (
                        <Textarea
                            rows={2}
                            className="mt-3 bg-card text-sm"
                            placeholder="Catatan/bukti yang diperiksa auditor…"
                            value={note}
                            disabled={!editable}
                            onChange={(e) => setNote(e.target.value)}
                            onBlur={() => note !== (question.answer?.auditor_note ?? '') && save(selected, note)}
                        />
                    )}
                </div>
            </div>
        </div>
    );
}

function ReportForm({ audit, editable }: { audit: Props['audit']; editable: boolean }) {
    const form = useForm({ summary: audit.summary_text ?? '', strengths: audit.strengths ?? '', conclusion: audit.conclusion ?? '' });

    return (
        <Card>
            <CardHeader>
                <CardTitle>Laporan audit</CardTitle>
                <CardDescription>Ringkasan, kekuatan, dan kesimpulan akan dicetak pada laporan PDF.</CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('ami.audits.report.save', audit.id), { preserveScroll: true });
                    }}
                    className="flex flex-col gap-4"
                >
                    <fieldset disabled={!editable} className="flex flex-col gap-4">
                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                            Ringkasan pelaksanaan
                            <Textarea rows={4} value={form.data.summary} onChange={(e) => form.setData('summary', e.target.value)} />
                        </label>
                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                            Kekuatan / praktik baik
                            <Textarea rows={4} value={form.data.strengths} onChange={(e) => form.setData('strengths', e.target.value)} />
                        </label>
                        <label className="flex flex-col gap-1.5 text-sm font-semibold">
                            Kesimpulan <span className="text-xs font-normal text-muted-foreground">(wajib sebelum audit diselesaikan)</span>
                            <Textarea rows={4} value={form.data.conclusion} onChange={(e) => form.setData('conclusion', e.target.value)} />
                        </label>
                    </fieldset>
                    {editable && (
                        <Button type="submit" className="self-end" disabled={form.processing || !form.isDirty}>
                            Simpan laporan
                        </Button>
                    )}
                    {!editable && !audit.conclusion && (
                        <p className="flex items-center gap-2 text-xs text-muted-foreground">
                            <AlertTriangle className="size-3.5" /> Laporan disusun oleh tim auditor.
                        </p>
                    )}
                </form>
            </CardContent>
        </Card>
    );
}
