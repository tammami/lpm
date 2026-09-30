import { Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Archive, CheckCircle2, Copy, Download, FileUp, Lock, MoreHorizontal, Pencil, Plus, Settings2, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { SwitchField } from '@/components/switch-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { versionTone } from '@/lib/accreditation';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Indicator {
    id: number;
    criterion_id: number;
    code: string;
    statement: string;
    target: string | null;
    evidence_hint: string | null;
    weight: number;
    is_essential: boolean;
}

interface Criterion {
    id: number;
    parent_id: number | null;
    code: string;
    title: string;
    description: string | null;
    weight: number;
    indicators: Indicator[];
}

interface Props {
    version: { id: number; version: string; scale_max: number; notes: string | null; status: string; status_label: string; editable: boolean; thresholds: { label: string; min: number }[]; periods_count: number; published_at: string | null };
    instrument: { id: number; code: string; name: string; description: string | null; body: string | null; versions: { id: number; version: string; status: string; status_label: string }[] };
    criteria: Criterion[];
    issues: string[];
}

export default function Builder({ version, instrument, criteria, issues }: Props) {
    const [criterionForm, setCriterionForm] = useState<{ criterion?: Criterion; parentId?: number | null } | null>(null);
    const [indicatorForm, setIndicatorForm] = useState<{ indicator?: Indicator; criterion: Criterion } | null>(null);
    const [settings, setSettings] = useState(false);
    const [importing, setImporting] = useState(false);
    const roots = criteria.filter((c) => c.parent_id === null);
    const totalWeight = roots.reduce((sum, c) => sum + Number(c.weight), 0);
    const indicatorCount = criteria.reduce((sum, c) => sum + c.indicators.length, 0);
    const editable = version.editable;

    return (
        <>
            <PageHeader
                title={`${instrument.name}`}
                breadcrumbs={[{ label: 'Instrumen LAM', href: route('accreditation.instruments.index') }, { label: `${instrument.code} v${version.version}` }]}
                meta={
                    <>
                        <StatusBadge tone={versionTone[version.status]}>{version.status_label}</StatusBadge>
                        <StatusBadge tone="gold" dot={false}>
                            {instrument.body}
                        </StatusBadge>
                        <span className="text-xs text-muted-foreground">
                            {roots.length} kriteria · {indicatorCount} indikator · dipakai {version.periods_count} periode
                        </span>
                    </>
                }
                actions={
                    <>
                        <SelectField value={version.id} onChange={(v) => router.visit(route('accreditation.versions.show', Number(v)))} options={instrument.versions.map((v) => ({ value: v.id, label: `v${v.version} · ${v.status_label}` }))} className="w-44" />
                        {editable ? (
                            <ConfirmDialog
                                trigger={
                                    <Button disabled={issues.length > 0}>
                                        <CheckCircle2 /> Tetapkan berlaku
                                    </Button>
                                }
                                title="Tetapkan versi ini berlaku?"
                                description="Versi yang berlaku terkunci dan dapat dipilih untuk periode akreditasi. Perubahan berikutnya dilakukan pada versi baru."
                                href={route('accreditation.versions.transition', version.id)}
                                method="post"
                                data={{ status: 'published' }}
                                destructive={false}
                                confirmLabel="Tetapkan"
                            />
                        ) : (
                            <Button variant="outline" onClick={() => router.post(route('accreditation.versions.duplicate', version.id))}>
                                <Copy /> Buat versi baru
                            </Button>
                        )}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="icon" aria-label="Aksi lainnya">
                                    <MoreHorizontal />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-56">
                                <DropdownMenuItem onSelect={() => setSettings(true)}>
                                    <Settings2 /> Pengaturan skor & peringkat
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <a href={route('accreditation.versions.export', version.id)}>
                                        <Download /> Ekspor ke Excel
                                    </a>
                                </DropdownMenuItem>
                                {editable && (
                                    <DropdownMenuItem onSelect={() => setImporting(true)}>
                                        <FileUp /> Impor dari Excel
                                    </DropdownMenuItem>
                                )}
                                {editable && (
                                    <DropdownMenuItem onSelect={() => router.post(route('accreditation.versions.duplicate', version.id))}>
                                        <Copy /> Salin ke versi baru
                                    </DropdownMenuItem>
                                )}
                                {version.status === 'published' && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem onSelect={() => router.post(route('accreditation.versions.transition', version.id), { status: 'archived' })}>
                                            <Archive /> Arsipkan versi
                                        </DropdownMenuItem>
                                    </>
                                )}
                                {version.status === 'archived' && (
                                    <DropdownMenuItem onSelect={() => router.post(route('accreditation.versions.transition', version.id), { status: 'published' })}>
                                        <CheckCircle2 /> Berlakukan kembali
                                    </DropdownMenuItem>
                                )}
                                {editable && (
                                    <>
                                        <DropdownMenuSeparator />
                                        <ConfirmDialog
                                            trigger={
                                                <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                                    <Trash2 /> Hapus draf
                                                </DropdownMenuItem>
                                            }
                                            title={`Hapus draf v${version.version}?`}
                                            description="Seluruh kriteria dan indikator pada draf ini ikut terhapus."
                                            href={route('accreditation.versions.destroy', version.id)}
                                            confirmLabel="Hapus draf"
                                        />
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </>
                }
            />

            {!editable && (
                <div className="mb-5 flex items-center gap-2 rounded-xl border border-primary/20 bg-secondary/50 px-4 py-3 text-sm">
                    <Lock className="size-4 text-primary" /> Versi ini {version.status === 'published' ? 'berlaku' : 'diarsipkan'} dan terkunci. Gunakan <b>Buat versi baru</b> untuk mengubah struktur.
                </div>
            )}

            {editable && issues.length > 0 && (
                <div className="mb-5 rounded-xl border border-warning/40 bg-warning-soft/50 px-4 py-3 text-sm">
                    <div className="flex items-center gap-2 font-semibold">
                        <AlertTriangle className="size-4" /> Belum dapat ditetapkan berlaku
                    </div>
                    <ul className="mt-1 list-disc pl-6 text-[13px]">
                        {issues.map((issue) => (
                            <li key={issue}>{issue}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mb-4 flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-2 text-sm">
                    <span className="text-muted-foreground">Total bobot kriteria</span>
                    <StatusBadge tone={Math.abs(totalWeight - 100) < 0.01 || totalWeight === 0 ? 'success' : 'warning'}>{formatNumber(totalWeight)}%</StatusBadge>
                </div>
                <div className="text-xs text-muted-foreground">
                    Skala skor 0–{version.scale_max} · {version.thresholds.map((t) => `${t.label} ≥ ${formatNumber(t.min)}`).join(' · ')}
                </div>
                {editable && (
                    <Button className="ml-auto" onClick={() => setCriterionForm({ parentId: null })}>
                        <Plus /> Kriteria
                    </Button>
                )}
            </div>

            <div className="flex flex-col gap-4">
                {roots.length === 0 && (
                    <Card>
                        <CardContent className="py-12 text-center text-sm text-muted-foreground">Belum ada kriteria. Tambahkan manual atau impor dari Excel (unduh ekspor versi lain sebagai templat).</CardContent>
                    </Card>
                )}
                {roots.map((root) => {
                    const children = criteria.filter((c) => c.parent_id === root.id);
                    return (
                        <Card key={root.id} className="gap-0 py-0">
                            <div className="flex items-start gap-3 border-b bg-muted/30 px-5 py-3.5">
                                <span className="mt-0.5 flex h-8 min-w-10 items-center justify-center rounded-lg bg-primary px-2 text-xs font-extrabold text-primary-foreground">{root.code}</span>
                                <div className="min-w-0 flex-1">
                                    <div className="font-bold">{root.title}</div>
                                    {root.description && <p className="text-xs text-muted-foreground">{root.description}</p>}
                                </div>
                                <StatusBadge tone="neutral" dot={false}>
                                    Bobot {formatNumber(root.weight)}%
                                </StatusBadge>
                                {editable && <CriterionActions criterion={root} onEdit={() => setCriterionForm({ criterion: root })} onAddSub={() => setCriterionForm({ parentId: root.id })} onAddIndicator={() => setIndicatorForm({ criterion: root })} />}
                            </div>
                            <CardContent className="flex flex-col gap-3 py-4">
                                <IndicatorList indicators={root.indicators} editable={editable} onEdit={(indicator) => setIndicatorForm({ indicator, criterion: root })} />
                                {children.map((child) => (
                                    <div key={child.id} className="rounded-xl border border-dashed p-3">
                                        <div className="mb-2 flex items-center gap-2">
                                            <span className="font-mono text-xs font-bold text-primary">{child.code}</span>
                                            <span className="text-sm font-semibold">{child.title}</span>
                                            {editable && (
                                                <div className="ml-auto">
                                                    <CriterionActions criterion={child} onEdit={() => setCriterionForm({ criterion: child })} onAddIndicator={() => setIndicatorForm({ criterion: child })} />
                                                </div>
                                            )}
                                        </div>
                                        <IndicatorList indicators={child.indicators} editable={editable} onEdit={(indicator) => setIndicatorForm({ indicator, criterion: child })} />
                                    </div>
                                ))}
                                {root.indicators.length === 0 && children.length === 0 && <p className="text-sm text-muted-foreground">Belum ada indikator.</p>}
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            {criterionForm && <CriterionDialog versionId={version.id} roots={roots} {...criterionForm} onClose={() => setCriterionForm(null)} />}
            {indicatorForm && <IndicatorDialog {...indicatorForm} onClose={() => setIndicatorForm(null)} />}
            {settings && <SettingsDialog version={version} onClose={() => setSettings(false)} />}
            {importing && <ImportDialog versionId={version.id} onClose={() => setImporting(false)} />}
            <p className="mt-6 text-xs text-muted-foreground">
                Struktur instrumen bawaan bersifat ilustratif. Sesuaikan butir, bobot, dan ambang peringkat dengan dokumen resmi LAM yang berlaku.{' '}
                <Link href={route('accreditation.periods.index')} className="font-semibold text-primary hover:underline">
                    Ke periode akreditasi →
                </Link>
            </p>
        </>
    );
}

function CriterionActions({ criterion, onEdit, onAddSub, onAddIndicator }: { criterion: Criterion; onEdit: () => void; onAddSub?: () => void; onAddIndicator: () => void }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon-sm" aria-label={`Aksi ${criterion.code}`}>
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem onSelect={onAddIndicator}>
                    <Plus /> Tambah indikator
                </DropdownMenuItem>
                {onAddSub && (
                    <DropdownMenuItem onSelect={onAddSub}>
                        <Plus /> Tambah sub-kriteria
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem onSelect={onEdit}>
                    <Pencil /> Ubah
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <ConfirmDialog
                    trigger={
                        <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                            <Trash2 /> Hapus beserta isinya
                        </DropdownMenuItem>
                    }
                    title={`Hapus kriteria ${criterion.code}?`}
                    description="Sub-kriteria dan seluruh indikator di dalamnya ikut terhapus."
                    href={route('accreditation.criteria.destroy', criterion.id)}
                    confirmLabel="Hapus"
                />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function IndicatorList({ indicators, editable, onEdit }: { indicators: Indicator[]; editable: boolean; onEdit: (indicator: Indicator) => void }) {
    if (indicators.length === 0) return null;

    return (
        <ul className="flex flex-col divide-y rounded-xl border">
            {indicators.map((indicator) => (
                <li key={indicator.id} className="group flex items-start gap-3 px-3 py-2.5">
                    <span className="mt-0.5 w-12 shrink-0 font-mono text-xs font-bold text-primary">{indicator.code}</span>
                    <div className="min-w-0 flex-1">
                        <p className="text-[14px]">{indicator.statement}</p>
                        {(indicator.target || indicator.evidence_hint) && (
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {indicator.target && <>Target: {indicator.target}</>}
                                {indicator.target && indicator.evidence_hint && ' · '}
                                {indicator.evidence_hint && <>Bukti: {indicator.evidence_hint}</>}
                            </p>
                        )}
                    </div>
                    <div className="flex shrink-0 items-center gap-1.5">
                        {indicator.is_essential && (
                            <StatusBadge tone="gold" dot={false} className="h-5 text-[11px]">
                                <Star className="size-3" /> Esensial
                            </StatusBadge>
                        )}
                        <span className="text-[11px] text-muted-foreground tabular">×{formatNumber(indicator.weight)}</span>
                        {editable && (
                            <>
                                <Button variant="ghost" size="icon-sm" onClick={() => onEdit(indicator)} aria-label={`Ubah ${indicator.code}`}>
                                    <Pencil />
                                </Button>
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="ghost" size="icon-sm" aria-label={`Hapus ${indicator.code}`}>
                                            <Trash2 className="text-muted-foreground" />
                                        </Button>
                                    }
                                    title={`Hapus indikator ${indicator.code}?`}
                                    href={route('accreditation.indicators.destroy', indicator.id)}
                                />
                            </>
                        )}
                    </div>
                </li>
            ))}
        </ul>
    );
}

function CriterionDialog({ versionId, roots, criterion, parentId, onClose }: { versionId: number; roots: Criterion[]; criterion?: Criterion; parentId?: number | null; onClose: () => void }) {
    const form = useForm({
        code: criterion?.code ?? '',
        title: criterion?.title ?? '',
        description: criterion?.description ?? '',
        weight: criterion?.weight ?? ('' as number | string),
        parent_id: criterion ? (criterion.parent_id ? String(criterion.parent_id) : '') : parentId ? String(parentId) : '',
    });
    const isSub = form.data.parent_id !== '';

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        form.transform((data) => ({ ...data, parent_id: data.parent_id || null, weight: data.weight === '' ? 0 : data.weight }));
        if (criterion) form.put(route('accreditation.criteria.update', criterion.id), options);
        else form.post(route('accreditation.criteria.store', versionId), options);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={criterion ? 'Ubah kriteria' : isSub ? 'Sub-kriteria baru' : 'Kriteria baru'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-[120px_minmax(0,1fr)]">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder={isSub ? 'C6.1' : 'C1'} />
                </FormField>
                <FormField label="Judul" error={form.errors.title} required>
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </FormField>
                <FormField label="Induk" error={form.errors.parent_id} className="sm:col-span-2">
                    <SelectField
                        value={form.data.parent_id || 'none'}
                        onChange={(v) => form.setData('parent_id', v === 'none' ? '' : v)}
                        options={[{ value: 'none', label: 'Tidak ada (kriteria utama)' }, ...roots.filter((r) => r.id !== criterion?.id).map((r) => ({ value: String(r.id), label: `${r.code} ${r.title}` }))]}
                    />
                </FormField>
                {!isSub && (
                    <FormField label="Bobot (%)" error={form.errors.weight}>
                        <Input type="number" value={form.data.weight} onChange={(e) => form.setData('weight', e.target.value)} />
                    </FormField>
                )}
                <FormField label="Deskripsi" error={form.errors.description} className={cn(isSub ? 'sm:col-span-2' : '')}>
                    <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}

function IndicatorDialog({ indicator, criterion, onClose }: { indicator?: Indicator; criterion: Criterion; onClose: () => void }) {
    const form = useForm({
        code: indicator?.code ?? `${criterion.code}.${criterion.indicators.length + 1}`,
        statement: indicator?.statement ?? '',
        target: indicator?.target ?? '',
        evidence_hint: indicator?.evidence_hint ?? '',
        weight: indicator?.weight ?? (1 as number | string),
        is_essential: indicator?.is_essential ?? false,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (indicator) form.put(route('accreditation.indicators.update', indicator.id), options);
        else form.post(route('accreditation.indicators.store', criterion.id), options);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={indicator ? `Ubah indikator ${indicator.code}` : `Indikator baru · ${criterion.code}`} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-[140px_minmax(0,1fr)_120px]">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                </FormField>
                <div />
                <FormField label="Bobot" error={form.errors.weight} required>
                    <Input type="number" step="0.5" value={form.data.weight} onChange={(e) => form.setData('weight', e.target.value)} />
                </FormField>
                <FormField label="Pernyataan indikator" error={form.errors.statement} required className="sm:col-span-3">
                    <Textarea rows={3} value={form.data.statement} onChange={(e) => form.setData('statement', e.target.value)} />
                </FormField>
                <FormField label="Target / kondisi yang diharapkan" error={form.errors.target} className="sm:col-span-3">
                    <Textarea rows={2} value={form.data.target} onChange={(e) => form.setData('target', e.target.value)} />
                </FormField>
                <FormField label="Bukti yang dibutuhkan" error={form.errors.evidence_hint} className="sm:col-span-3" hint="Pisahkan beberapa dokumen dengan titik koma.">
                    <Textarea rows={2} value={form.data.evidence_hint} onChange={(e) => form.setData('evidence_hint', e.target.value)} />
                </FormField>
                <div className="sm:col-span-3">
                    <SwitchField label="Syarat perlu (esensial)" description="Wajib memiliki bukti terverifikasi dan skor diri ≥ 2." checked={form.data.is_essential} onChange={(v) => form.setData('is_essential', v)} />
                </div>
            </div>
        </FormDialog>
    );
}

function SettingsDialog({ version, onClose }: { version: Props['version']; onClose: () => void }) {
    const form = useForm({
        version: version.version,
        scale_max: version.scale_max as number | string,
        notes: version.notes ?? '',
        thresholds: version.thresholds.map((t) => ({ label: t.label, min: t.min as number | string })),
    });
    const locked = !version.editable;

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title="Pengaturan skor & peringkat" description={locked ? 'Versi terkunci — hanya dapat dilihat.' : 'Estimasi skor dihitung pada skala 0–400.'} onSubmit={() => (locked ? onClose() : form.put(route('accreditation.versions.update', version.id), { preserveScroll: true, onSuccess: onClose }))} processing={form.processing} submitLabel={locked ? 'Tutup' : 'Simpan'}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Nomor versi" error={form.errors.version} required>
                    <Input disabled={locked} value={form.data.version} onChange={(e) => form.setData('version', e.target.value)} />
                </FormField>
                <FormField label="Skor maksimal per indikator" error={form.errors.scale_max} required>
                    <Input disabled={locked} type="number" value={form.data.scale_max} onChange={(e) => form.setData('scale_max', e.target.value)} />
                </FormField>
                <div className="sm:col-span-2">
                    <div className="mb-2 text-sm font-medium">Ambang peringkat (nilai minimal)</div>
                    <div className="flex flex-col gap-2">
                        {form.data.thresholds.map((threshold, index) => (
                            <div key={index} className="flex gap-2">
                                <Input disabled={locked} value={threshold.label} onChange={(e) => form.setData('thresholds', form.data.thresholds.map((t, i) => (i === index ? { ...t, label: e.target.value } : t)))} />
                                <Input disabled={locked} type="number" className="w-28" value={threshold.min} onChange={(e) => form.setData('thresholds', form.data.thresholds.map((t, i) => (i === index ? { ...t, min: e.target.value } : t)))} />
                                {!locked && (
                                    <Button type="button" variant="ghost" size="icon" onClick={() => form.setData('thresholds', form.data.thresholds.filter((_, i) => i !== index))} aria-label="Hapus ambang">
                                        <Trash2 className="text-muted-foreground" />
                                    </Button>
                                )}
                            </div>
                        ))}
                        {!locked && (
                            <Button type="button" variant="outline" size="sm" className="self-start" onClick={() => form.setData('thresholds', [...form.data.thresholds, { label: '', min: 0 }])}>
                                <Plus /> Ambang
                            </Button>
                        )}
                        {form.errors.thresholds && <p className="text-xs text-destructive">{form.errors.thresholds}</p>}
                    </div>
                </div>
                <FormField label="Catatan versi" error={form.errors.notes} className="sm:col-span-2">
                    <Textarea disabled={locked} rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}

function ImportDialog({ versionId, onClose }: { versionId: number; onClose: () => void }) {
    const form = useForm<{ file: File | null }>({ file: null });

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title="Impor kriteria & indikator"
            description="Seluruh isi versi draf ini akan diganti dengan isi berkas."
            onSubmit={() => form.post(route('accreditation.versions.import', versionId), { forceFormData: true, preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
            submitLabel="Impor"
        >
            <div className="flex flex-col gap-3 text-sm">
                <p className="text-muted-foreground">
                    Kolom: <code className="text-xs">kode_kriteria, kriteria, bobot_kriteria, induk, kode_indikator, indikator, target, bukti, bobot, esensial</code>. Satu baris = satu indikator; isi <code className="text-xs">induk</code> dengan kode kriteria utama untuk sub-kriteria. Gunakan
                    hasil <b>Ekspor ke Excel</b> sebagai templat.
                </p>
                <FormField label="Berkas (.xlsx / .csv)" error={form.errors.file} required>
                    <Input type="file" accept=".xlsx,.csv" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
