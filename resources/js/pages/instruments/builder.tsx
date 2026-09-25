import { Link, router, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDown,
    ArrowUp,
    Archive,
    CheckCircle2,
    ChevronDown,
    Copy,
    Eye,
    FileStack,
    GitBranchPlus,
    ListPlus,
    Lock,
    Pencil,
    Plus,
    Rocket,
    Send,
    Trash2,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { QuestionEditor } from '@/components/instruments/question-editor';
import type { AnswerScale, BuilderQuestion, BuilderSection, BuilderVersion, QuestionTypeOption } from '@/components/instruments/types';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { formatDate, formatDateTime } from '@/lib/format';
import { instrumentTypeTone, versionTone } from '@/lib/instrument';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

interface Props {
    instrument: { id: number; code: string; name: string; description: string | null; type: string; type_label: string; respondent_label: string; archived: boolean };
    version: BuilderVersion;
    versions: { id: number; version: string; status: string; status_label: string; published_at: string | null; created_at: string }[];
    sections: BuilderSection[];
    publishIssues: string[];
    questionTypes: QuestionTypeOption[];
    scoringMethods: (Option & { formula: string })[];
    classificationSchemes: (Option & { classifications: { label: string; min_score: number; max_score: number; color: string }[] })[];
    answerScales: AnswerScale[];
    can: { manage: boolean; approve: boolean; publish: boolean };
}

type Transition = { status: string; label: string; description: string; icon: typeof Send; destructive?: boolean; notes?: boolean };

export default function InstrumentBuilder(props: Props) {
    const { instrument, version, versions, sections, publishIssues, questionTypes, answerScales, can } = props;
    const editable = version.is_editable && can.manage;
    const [editor, setEditor] = useState<{ question: BuilderQuestion | null; sectionId: number } | null>(null);
    const [sectionDialog, setSectionDialog] = useState<BuilderSection | 'new' | null>(null);
    const [transition, setTransition] = useState<Transition | null>(null);
    const [newVersion, setNewVersion] = useState(false);

    const totalQuestions = sections.reduce((sum, s) => sum + s.questions.length, 0);
    const scoredQuestions = sections.reduce((sum, s) => sum + s.questions.filter((q) => q.is_scored && q.is_active).length, 0);

    const transitions: Transition[] = [];
    if (version.status === 'draft' && can.manage)
        transitions.push({ status: 'review', label: 'Ajukan tinjauan', description: 'Versi akan dikunci sementara dan diajukan kepada peninjau.', icon: Send, notes: true });
    if (version.status === 'review' && can.approve) {
        transitions.push({ status: 'approved', label: 'Setujui', description: 'Instrumen dinyatakan layak dan siap diterbitkan.', icon: CheckCircle2, notes: true });
        transitions.push({ status: 'draft', label: 'Kembalikan ke draf', description: 'Sertakan catatan perbaikan untuk penyusun.', icon: Undo2, notes: true });
    }
    if (version.status === 'approved' && can.publish)
        transitions.push({ status: 'published', label: 'Terbitkan', description: 'Versi terbit dikunci permanen dan dapat dipakai pada kegiatan Monev/AMI.', icon: Rocket });
    if (version.status === 'approved' && can.manage) transitions.push({ status: 'draft', label: 'Kembalikan ke draf', description: 'Buka kembali untuk diubah.', icon: Undo2, notes: true });
    if (version.status === 'published' && can.publish)
        transitions.push({ status: 'archived', label: 'Arsipkan versi', description: 'Versi arsip tidak dapat dipilih untuk kegiatan baru. Respons lama tetap terbaca.', icon: Archive, destructive: true });

    const move = (url: string, direction: 'up' | 'down') => router.post(url, { direction }, { preserveScroll: true });

    return (
        <>
            <PageHeader
                title={instrument.name}
                breadcrumbs={[{ label: 'e-Monev' }, { label: 'Instrumen', href: route('instruments.index') }, { label: `v${version.version}` }]}
                meta={
                    <>
                        <StatusBadge tone={instrumentTypeTone[instrument.type] ?? 'neutral'} dot={false}>
                            {instrument.type_label}
                        </StatusBadge>
                        <StatusBadge tone="neutral" dot={false}>
                            Responden: {instrument.respondent_label}
                        </StatusBadge>
                        <VersionSwitcher current={version} versions={versions} />
                    </>
                }
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link href={route('instrument-versions.preview', version.id)}>
                                <Eye /> Pratinjau
                            </Link>
                        </Button>
                        {can.manage && (
                            <Button variant="outline" onClick={() => setNewVersion(true)}>
                                <GitBranchPlus /> Versi baru
                            </Button>
                        )}
                        {transitions.map((t) => (
                            <Button key={t.status + t.label} variant={t.status === 'draft' || t.destructive ? 'outline' : 'default'} onClick={() => setTransition(t)}>
                                <t.icon /> {t.label}
                            </Button>
                        ))}
                    </>
                }
            />

            {!version.is_editable && (
                <Alert className="mb-6 border-primary/20 bg-secondary/60">
                    <Lock className="text-primary" />
                    <AlertTitle>Versi {version.version} terkunci ({version.status_label})</AlertTitle>
                    <AlertDescription>
                        {version.status === 'review'
                            ? 'Sedang ditinjau. Peninjau dapat menyetujui atau mengembalikannya ke draf.'
                            : 'Untuk mengubah isi, buat versi baru. Respons yang sudah masuk tetap terhubung ke versi ini.'}
                        {version.responses_count > 0 && ` Versi ini dipakai oleh ${version.responses_count} respons.`}
                    </AlertDescription>
                </Alert>
            )}

            {version.review_notes && version.status === 'draft' && (
                <Alert className="mb-6 border-warning/40 bg-warning-soft">
                    <AlertTriangle className="text-gold-foreground" />
                    <AlertTitle>Catatan peninjau</AlertTitle>
                    <AlertDescription className="whitespace-pre-line">{version.review_notes}</AlertDescription>
                </Alert>
            )}

            {publishIssues.length > 0 && (
                <Collapsible className="mb-6 rounded-xl border border-warning/40 bg-warning-soft/60">
                    <CollapsibleTrigger className="flex w-full items-center gap-3 px-4 py-3 text-left">
                        <AlertTriangle className="size-4 shrink-0 text-gold-foreground" />
                        <span className="flex-1 text-sm font-semibold text-gold-foreground">Belum siap diterbitkan — {publishIssues.length} hal perlu dilengkapi</span>
                        <ChevronDown className="size-4 text-gold-foreground" />
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <ul className="list-disc space-y-1 px-10 pb-4 text-sm text-gold-foreground/90">
                            {publishIssues.map((issue) => (
                                <li key={issue}>{issue}</li>
                            ))}
                        </ul>
                    </CollapsibleContent>
                </Collapsible>
            )}

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[220px_minmax(0,1fr)_320px]">
                {/* Outline */}
                <aside className="hidden xl:block">
                    <div className="sticky top-24 flex flex-col gap-1">
                        <div className="px-2 pb-2 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">Struktur</div>
                        {sections.map((section) => (
                            <a key={section.id} href={`#section-${section.id}`} className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-muted">
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-secondary text-[11px] font-bold text-secondary-foreground">{section.code}</span>
                                <span className="min-w-0 flex-1 truncate">{section.title}</span>
                                <span className="text-xs text-muted-foreground tabular">{section.questions.length}</span>
                            </a>
                        ))}
                        {editable && (
                            <Button variant="ghost" size="sm" className="mt-1 justify-start text-primary" onClick={() => setSectionDialog('new')}>
                                <Plus /> Tambah bagian
                            </Button>
                        )}
                    </div>
                </aside>

                {/* Sections */}
                <div className="flex min-w-0 flex-col gap-5">
                    {sections.length === 0 && (
                        <Card>
                            <EmptyState title="Belum ada bagian" description="Kelompokkan pertanyaan ke dalam bagian, mis. A. Perencanaan, B. Pelaksanaan." />
                        </Card>
                    )}
                    {sections.map((section, sectionIndex) => (
                        <section key={section.id} id={`section-${section.id}`} className="scroll-mt-24 overflow-hidden rounded-2xl border bg-card">
                            <header className="flex items-start gap-3 border-b bg-gradient-to-r from-secondary/70 to-transparent px-5 py-4">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary text-sm font-extrabold text-primary-foreground">{section.code}</span>
                                <div className="min-w-0 flex-1">
                                    <h2 className="font-bold">{section.title}</h2>
                                    {section.description && <p className="text-sm text-muted-foreground">{section.description}</p>}
                                </div>
                                {editable && (
                                    <div className="flex items-center gap-0.5">
                                        <IconButton label="Naikkan" onClick={() => move(route('instrument-sections.move', section.id), 'up')} disabled={sectionIndex === 0}>
                                            <ArrowUp />
                                        </IconButton>
                                        <IconButton label="Turunkan" onClick={() => move(route('instrument-sections.move', section.id), 'down')} disabled={sectionIndex === sections.length - 1}>
                                            <ArrowDown />
                                        </IconButton>
                                        <IconButton label="Ubah bagian" onClick={() => setSectionDialog(section)}>
                                            <Pencil />
                                        </IconButton>
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="icon-sm" aria-label="Hapus bagian">
                                                    <Trash2 className="text-destructive" />
                                                </Button>
                                            }
                                            title={`Hapus bagian ${section.code}?`}
                                            description="Bagian harus kosong sebelum dihapus."
                                            href={route('instrument-sections.destroy', section.id)}
                                            confirmLabel="Hapus"
                                        />
                                    </div>
                                )}
                            </header>

                            <ol className="divide-y">
                                {section.questions.map((question, index) => (
                                    <li key={question.id} className={cn('group flex items-start gap-3 px-5 py-3.5 transition hover:bg-muted/40', !question.is_active && 'opacity-55')}>
                                        <span className="mt-0.5 w-9 shrink-0 font-mono text-xs font-bold text-primary">{question.code}</span>
                                        <button type="button" onClick={() => setEditor({ question, sectionId: section.id })} className="min-w-0 flex-1 text-left">
                                            <div className="text-[14px] leading-snug font-medium">{question.label}</div>
                                            <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                                <Chip>{question.type_label}</Chip>
                                                {question.options.length > 0 && <Chip>{question.options.length} opsi</Chip>}
                                                {question.is_required && <Chip tone="primary">Wajib</Chip>}
                                                {question.is_scored ? <Chip tone="gold">Bobot ×{Number(question.weight)}</Chip> : <Chip>Tanpa skor</Chip>}
                                                {question.indicator && <Chip>{question.indicator}</Chip>}
                                                {!question.is_active && <Chip>Nonaktif</Chip>}
                                            </div>
                                        </button>
                                        {editable && (
                                            <div className="flex shrink-0 items-center gap-0.5 opacity-100 transition md:opacity-0 md:group-hover:opacity-100 md:focus-within:opacity-100">
                                                <IconButton label="Naikkan" onClick={() => move(route('instrument-questions.move', question.id), 'up')} disabled={index === 0}>
                                                    <ArrowUp />
                                                </IconButton>
                                                <IconButton label="Turunkan" onClick={() => move(route('instrument-questions.move', question.id), 'down')} disabled={index === section.questions.length - 1}>
                                                    <ArrowDown />
                                                </IconButton>
                                                <IconButton label="Duplikat" onClick={() => router.post(route('instrument-questions.duplicate', question.id), {}, { preserveScroll: true })}>
                                                    <Copy />
                                                </IconButton>
                                                <ConfirmDialog
                                                    trigger={
                                                        <Button variant="ghost" size="icon-sm" aria-label="Hapus butir">
                                                            <Trash2 className="text-destructive" />
                                                        </Button>
                                                    }
                                                    title={`Hapus butir ${question.code}?`}
                                                    href={route('instrument-questions.destroy', question.id)}
                                                    confirmLabel="Hapus"
                                                />
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ol>
                            {section.questions.length === 0 && <p className="px-5 py-6 text-center text-sm text-muted-foreground">Belum ada pertanyaan pada bagian ini.</p>}
                            {editable && (
                                <div className="border-t border-dashed px-5 py-3">
                                    <Button variant="ghost" size="sm" className="text-primary" onClick={() => setEditor({ question: null, sectionId: section.id })}>
                                        <ListPlus /> Tambah pertanyaan
                                    </Button>
                                </div>
                            )}
                        </section>
                    ))}
                    {editable && (
                        <button
                            type="button"
                            onClick={() => setSectionDialog('new')}
                            className="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed py-6 text-sm font-semibold text-muted-foreground transition hover:border-primary/40 hover:text-primary"
                        >
                            <Plus className="size-4" /> Tambah bagian
                        </button>
                    )}
                </div>

                {/* Settings */}
                <aside className="flex flex-col gap-5">
                    <div className="grid grid-cols-3 gap-2">
                        <Stat label="Bagian" value={sections.length} />
                        <Stat label="Butir" value={totalQuestions} />
                        <Stat label="Diskor" value={scoredQuestions} />
                    </div>
                    <ScoringSettings {...props} editable={editable} />
                    <Card className="gap-3">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-sm">
                                <FileStack className="size-4 text-primary" /> Riwayat versi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-1">
                            {versions.map((item) => (
                                <Link
                                    key={item.id}
                                    href={route('instrument-versions.show', item.id)}
                                    className={cn('flex items-center gap-2 rounded-lg px-2 py-2 text-sm hover:bg-muted', item.id === version.id && 'bg-secondary')}
                                >
                                    <span className="font-mono text-xs font-bold">v{item.version}</span>
                                    <StatusBadge tone={versionTone[item.status]} className="text-[11px]">
                                        {item.status_label}
                                    </StatusBadge>
                                    <span className="ml-auto text-[11px] text-muted-foreground">{formatDate(item.published_at ?? item.created_at)}</span>
                                </Link>
                            ))}
                            {version.published_at && (
                                <p className="mt-2 border-t pt-3 text-xs text-muted-foreground">
                                    Diterbitkan {formatDateTime(version.published_at)}
                                    {version.publisher ? ` oleh ${version.publisher}` : ''}.
                                </p>
                            )}
                            {version.status === 'draft' && versions.length > 1 && can.manage && (
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="ghost" size="sm" className="mt-2 justify-start text-destructive">
                                            <Trash2 /> Hapus draf ini
                                        </Button>
                                    }
                                    title={`Hapus draf v${version.version}?`}
                                    href={route('instrument-versions.destroy', version.id)}
                                    confirmLabel="Hapus"
                                />
                            )}
                        </CardContent>
                    </Card>
                </aside>
            </div>

            {editor && (
                <QuestionEditor
                    key={editor.question?.id ?? `new-${editor.sectionId}`}
                    open
                    onClose={() => setEditor(null)}
                    question={editor.question}
                    sectionId={editor.sectionId}
                    sections={sections}
                    questionTypes={questionTypes}
                    answerScales={answerScales}
                    editable={editable}
                    scaleMax={version.scale_max}
                />
            )}
            {sectionDialog && <SectionDialog versionId={version.id} section={sectionDialog === 'new' ? null : sectionDialog} nextCode={String.fromCharCode(65 + sections.length)} onClose={() => setSectionDialog(null)} />}
            {transition && <TransitionDialog versionId={version.id} transition={transition} onClose={() => setTransition(null)} />}
            {newVersion && <NewVersionDialog version={version} onClose={() => setNewVersion(false)} />}
        </>
    );
}

function VersionSwitcher({ current, versions }: { current: BuilderVersion; versions: Props['versions'] }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger className="inline-flex items-center gap-1.5 rounded-full bg-card px-2.5 py-0.5 text-xs font-semibold ring-1 ring-border hover:bg-muted">
                <span className={cn('size-1.5 rounded-full', current.status === 'published' ? 'bg-success' : current.status === 'draft' ? 'bg-muted-foreground' : 'bg-info')} />
                v{current.version} · {current.status_label}
                <ChevronDown className="size-3" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start">
                <DropdownMenuLabel>Pilih versi</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {versions.map((item) => (
                    <DropdownMenuItem key={item.id} asChild>
                        <Link href={route('instrument-versions.show', item.id)} className="flex items-center justify-between gap-6">
                            <span className="font-mono font-semibold">v{item.version}</span>
                            <StatusBadge tone={versionTone[item.status]}>{item.status_label}</StatusBadge>
                        </Link>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function ScoringSettings({ version, scoringMethods, classificationSchemes, editable }: Props & { editable: boolean }) {
    const form = useForm({
        scoring_method: version.scoring_method,
        scale_min: version.scale_min,
        scale_max: version.scale_max,
        classification_scheme_id: version.classification_scheme_id ? String(version.classification_scheme_id) : '',
        changelog: version.changelog ?? '',
    });
    const scheme = classificationSchemes.find((s) => String(s.value) === form.data.classification_scheme_id);
    const colors: Record<string, string> = { danger: 'bg-destructive', warning: 'bg-warning', info: 'bg-info', success: 'bg-success', neutral: 'bg-muted-foreground' };

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="text-sm">Pengaturan penilaian</CardTitle>
            </CardHeader>
            <CardContent>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('instrument-versions.update', version.id), { preserveScroll: true });
                    }}
                >
                    <fieldset disabled={!editable} className="flex flex-col gap-4">
                        <RadioGroup value={form.data.scoring_method} onValueChange={(v) => form.setData('scoring_method', v)} className="gap-2">
                            {scoringMethods.map((method) => (
                                <Label
                                    key={method.value}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-xl border p-3 font-normal',
                                        form.data.scoring_method === method.value && 'border-primary bg-secondary/60',
                                    )}
                                >
                                    <RadioGroupItem value={String(method.value)} className="mt-0.5" disabled={!editable} />
                                    <div>
                                        <div className="text-[13px] font-semibold">{method.label}</div>
                                        <div className="mt-0.5 font-mono text-[11px] text-muted-foreground">{method.formula}</div>
                                    </div>
                                </Label>
                            ))}
                        </RadioGroup>
                        <div className="grid grid-cols-2 gap-3">
                            <FormField label="Skala min." error={form.errors.scale_min}>
                                <Input type="number" step="0.5" value={form.data.scale_min} onChange={(e) => form.setData('scale_min', Number(e.target.value))} />
                            </FormField>
                            <FormField label="Skala maks." error={form.errors.scale_max}>
                                <Input type="number" step="0.5" value={form.data.scale_max} onChange={(e) => form.setData('scale_max', Number(e.target.value))} />
                            </FormField>
                        </div>
                        <FormField label="Klasifikasi skor" error={form.errors.classification_scheme_id}>
                            <SelectField value={form.data.classification_scheme_id} onChange={(v) => form.setData('classification_scheme_id', v)} options={classificationSchemes} disabled={!editable} />
                        </FormField>
                        {scheme && (
                            <div className="flex flex-col gap-1 rounded-lg bg-muted/50 p-2.5">
                                {scheme.classifications.map((c) => (
                                    <div key={c.label} className="flex items-center gap-2 text-xs">
                                        <span className={cn('size-2 rounded-full', colors[c.color] ?? 'bg-muted-foreground')} />
                                        <span className="font-medium">{c.label}</span>
                                        <span className="ml-auto text-muted-foreground tabular">
                                            {c.min_score}–{c.max_score}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                        <FormField label="Catatan perubahan (changelog)" error={form.errors.changelog}>
                            <Textarea rows={3} value={form.data.changelog} onChange={(e) => form.setData('changelog', e.target.value)} />
                        </FormField>
                        {editable && (
                            <Button type="submit" variant="secondary" disabled={form.processing || !form.isDirty}>
                                Simpan pengaturan
                            </Button>
                        )}
                    </fieldset>
                </form>
            </CardContent>
        </Card>
    );
}

function SectionDialog({ versionId, section, nextCode, onClose }: { versionId: number; section: BuilderSection | null; nextCode: string; onClose: () => void }) {
    const form = useForm({ code: section?.code ?? nextCode, title: section?.title ?? '', description: section?.description ?? '' });
    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (section) form.put(route('instrument-sections.update', section.id), options);
        else form.post(route('instrument-sections.store', versionId), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={section ? 'Ubah bagian' : 'Tambah bagian'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Judul bagian" error={form.errors.title} required className="sm:col-span-3">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Perencanaan Pembelajaran" autoFocus />
                </FormField>
                <FormField label="Deskripsi" error={form.errors.description} className="sm:col-span-4">
                    <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}

function TransitionDialog({ versionId, transition, onClose }: { versionId: number; transition: Transition; onClose: () => void }) {
    const form = useForm({ status: transition.status, notes: '' });

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={transition.label}
            description={transition.description}
            submitLabel={transition.label}
            processing={form.processing}
            onSubmit={() =>
                form.post(route('instrument-versions.transition', versionId), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: (errors) => {
                        if (errors.status) toast.error(errors.status);
                        onClose();
                    },
                })
            }
        >
            {transition.notes ? (
                <FormField label="Catatan (opsional)" error={form.errors.notes}>
                    <Textarea rows={4} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Catatan untuk riwayat tinjauan…" />
                </FormField>
            ) : (
                <p className="text-sm text-muted-foreground">Tindakan ini tercatat pada log audit.</p>
            )}
        </FormDialog>
    );
}

function NewVersionDialog({ version, onClose }: { version: BuilderVersion; onClose: () => void }) {
    const form = useForm({ major: false, changelog: '' });

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title="Buat versi baru"
            description={`Seluruh bagian & butir v${version.version} disalin ke versi draf baru. Versi ${version.version} tidak berubah.`}
            submitLabel="Buat versi"
            processing={form.processing}
            onSubmit={() => form.post(route('instrument-versions.duplicate', version.id))}
        >
            <div className="flex flex-col gap-4">
                <RadioGroup value={form.data.major ? 'major' : 'minor'} onValueChange={(v) => form.setData('major', v === 'major')} className="grid grid-cols-2 gap-3">
                    <Label className={cn('flex cursor-pointer flex-col items-start gap-1 rounded-xl border p-3 font-normal', !form.data.major && 'border-primary bg-secondary/60')}>
                        <span className="flex items-center gap-2 text-sm font-semibold">
                            <RadioGroupItem value="minor" /> Minor
                        </span>
                        <span className="text-xs text-muted-foreground">Perbaikan redaksi/opsi (mis. 1.0 → 1.1)</span>
                    </Label>
                    <Label className={cn('flex cursor-pointer flex-col items-start gap-1 rounded-xl border p-3 font-normal', form.data.major && 'border-primary bg-secondary/60')}>
                        <span className="flex items-center gap-2 text-sm font-semibold">
                            <RadioGroupItem value="major" /> Mayor
                        </span>
                        <span className="text-xs text-muted-foreground">Perubahan struktur/butir (mis. 1.1 → 2.0)</span>
                    </Label>
                </RadioGroup>
                <FormField label="Catatan perubahan" error={form.errors.changelog}>
                    <Textarea rows={3} value={form.data.changelog} onChange={(e) => form.setData('changelog', e.target.value)} placeholder="Apa yang akan diubah pada versi ini?" />
                </FormField>
            </div>
        </FormDialog>
    );
}

function IconButton({ label, onClick, disabled, children }: { label: string; onClick: () => void; disabled?: boolean; children: React.ReactNode }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button variant="ghost" size="icon-sm" onClick={onClick} disabled={disabled} aria-label={label}>
                    {children}
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

function Chip({ children, tone }: { children: React.ReactNode; tone?: 'primary' | 'gold' }) {
    return (
        <span
            className={cn(
                'rounded-md px-1.5 py-0.5 text-[11px] font-semibold',
                tone === 'primary' ? 'bg-secondary text-secondary-foreground' : tone === 'gold' ? 'bg-gold-soft text-gold-foreground' : 'bg-muted text-muted-foreground',
            )}
        >
            {children}
        </span>
    );
}

function Stat({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-xl border bg-card px-3 py-2.5 text-center">
            <div className="text-xl font-extrabold tabular">{value}</div>
            <div className="text-[11px] font-medium text-muted-foreground">{label}</div>
        </div>
    );
}
