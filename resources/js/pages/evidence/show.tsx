import { Link, useForm } from '@inertiajs/react';
import { CheckCircle2, Download, ExternalLink, History, Link2, Pencil, ShieldCheck, Trash2, Upload, XCircle } from 'lucide-react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FileIcon } from '@/components/evidence/file-icon';
import { evidenceTone, type EvidenceRow } from '@/components/evidence/types';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { formatBytes, formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

interface Detail extends EvidenceRow {
    description: string | null;
    valid_until: string | null;
    confidentiality: string;
    academic_period_id: number | null;
    unit: string | null;
    versions: { id: number; version: number; original_name: string | null; mime_type: string | null; size: number | null; url: string | null; notes: string | null; checksum: string | null; uploader: string | null; created_at: string; is_current: boolean }[];
    verifications: { decision: string; notes: string | null; verifier: string | null; verified_at: string }[];
    mappings: { id: number; type: string; type_label: string; label: string; url: string | null; note: string | null; creator: string | null; created_at: string }[];
}

interface Props {
    evidence: Detail;
    categories: Option[];
    units: Option[];
    periods: Option[];
    maxUploadMb: number;
    allowedExtensions: string[];
    can: { manage: boolean; verify: boolean };
}

const confidentialityLabels: Record<string, string> = { public: 'Publik (seluruh pengguna)', internal: 'Internal (sesuai cakupan)', restricted: 'Terbatas' };

export default function EvidenceShow({ evidence, categories, units, periods, maxUploadMb, allowedExtensions, can }: Props) {
    const [editing, setEditing] = useState(false);
    const [newVersion, setNewVersion] = useState(false);
    const [verifying, setVerifying] = useState<'accepted' | 'rejected' | null>(null);
    const current = evidence.versions.find((v) => v.is_current);
    const previewable = current && !current.url && (current.mime_type === 'application/pdf' || current.mime_type?.startsWith('image/'));

    return (
        <>
            <PageHeader
                title={evidence.title}
                breadcrumbs={[{ label: 'Dokumen Bukti', href: route('evidence.index') }, { label: evidence.code }]}
                meta={
                    <>
                        <StatusBadge tone={evidenceTone[evidence.status] ?? 'neutral'}>{evidence.status_label}</StatusBadge>
                        <StatusBadge tone="neutral" dot={false}>
                            {evidence.code} · v{evidence.version}
                        </StatusBadge>
                        {evidence.category && <StatusBadge tone="primary" dot={false}>{evidence.category}</StatusBadge>}
                    </>
                }
                actions={
                    <>
                        <Button asChild>
                            <a href={evidence.download_url} target="_blank" rel="noreferrer">
                                {evidence.document_type === 'link' ? <ExternalLink /> : <Download />} {evidence.document_type === 'link' ? 'Buka tautan' : 'Unduh'}
                            </a>
                        </Button>
                        {can.manage && (
                            <>
                                <Button variant="outline" onClick={() => setNewVersion(true)}>
                                    <Upload /> Versi baru
                                </Button>
                                <Button variant="outline" onClick={() => setEditing(true)}>
                                    <Pencil /> Metadata
                                </Button>
                                <ConfirmDialog
                                    trigger={
                                        <Button variant="ghost" size="icon" aria-label="Hapus">
                                            <Trash2 className="text-destructive" />
                                        </Button>
                                    }
                                    title="Hapus dokumen?"
                                    description="Dokumen yang sudah terverifikasi atau dipakai sebagai bukti tidak dapat dihapus."
                                    href={route('evidence.destroy', evidence.id)}
                                    confirmLabel="Hapus"
                                />
                            </>
                        )}
                    </>
                }
            />

            <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div className="flex flex-col gap-6">
                    <Card className="gap-0 overflow-hidden p-0">
                        {previewable ? (
                            current?.mime_type === 'application/pdf' ? (
                                <iframe title="Pratinjau dokumen" src={`${evidence.download_url}?inline=1`} className="h-[640px] w-full bg-muted" />
                            ) : (
                                <img src={`${evidence.download_url}?inline=1`} alt={evidence.title} className="max-h-[640px] w-full bg-muted object-contain" />
                            )
                        ) : (
                            <div className="flex flex-col items-center gap-3 px-6 py-16 text-center">
                                <FileIcon mime={current?.mime_type} link={!!current?.url} className="size-16 rounded-2xl [&_svg]:size-8" />
                                <div className="font-semibold">{current?.original_name ?? current?.url ?? 'Dokumen'}</div>
                                <p className="max-w-sm text-sm text-muted-foreground">Pratinjau tersedia untuk PDF dan gambar. Gunakan tombol unduh untuk berkas lain.</p>
                            </div>
                        )}
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Link2 className="size-4 text-primary" /> Dipakai sebagai bukti ({evidence.mappings.length})
                            </CardTitle>
                            <CardDescription>Satu dokumen dapat mendukung banyak kebutuhan tanpa unggah ulang.</CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {evidence.mappings.length === 0 && <p className="text-sm text-muted-foreground">Belum ditautkan ke temuan, rencana aksi, atau indikator akreditasi.</p>}
                            {evidence.mappings.map((mapping) => (
                                <div key={mapping.id} className="flex items-center gap-3 rounded-xl border p-3">
                                    <StatusBadge tone="gold" dot={false}>
                                        {mapping.type_label}
                                    </StatusBadge>
                                    {mapping.url ? (
                                        <Link href={mapping.url} className="min-w-0 flex-1 truncate text-sm font-medium hover:text-primary">
                                            {mapping.label}
                                        </Link>
                                    ) : (
                                        <span className="min-w-0 flex-1 truncate text-sm font-medium">{mapping.label}</span>
                                    )}
                                    <span className="shrink-0 text-[11px] text-muted-foreground">{formatDate(mapping.created_at)}</span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    {can.verify && (
                        <Card className="border-primary/20 bg-secondary/30">
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ShieldCheck className="size-4 text-primary" /> Verifikasi dokumen
                                </CardTitle>
                                <CardDescription>Periksa keabsahan dan kesesuaian isi dokumen versi terbaru.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-2">
                                <Button onClick={() => setVerifying('accepted')} disabled={evidence.status === 'verified'}>
                                    <CheckCircle2 /> Sah
                                </Button>
                                <Button variant="outline" className="text-destructive" onClick={() => setVerifying('rejected')}>
                                    <XCircle /> Tolak
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3 text-sm">
                            <Info label="Unit pemilik" value={evidence.unit_name} />
                            <Info label="Pengunggah" value={evidence.owner} />
                            <Info label="Tahun" value={evidence.year} />
                            <Info label="Berlaku sampai" value={evidence.valid_until ? formatDate(evidence.valid_until) : 'Tidak dibatasi'} />
                            <Info label="Kerahasiaan" value={confidentialityLabels[evidence.confidentiality]} />
                            {evidence.description && <Info label="Keterangan" value={evidence.description} />}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <History className="size-4 text-primary" /> Riwayat versi
                            </CardTitle>
                            <CardDescription>Versi lama tidak pernah dihapus.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <ol className="relative flex flex-col gap-4 border-l pl-5">
                                {evidence.versions.map((version) => (
                                    <li key={version.id} className="relative">
                                        <span className={cn('absolute top-1 -left-[26px] size-3 rounded-full ring-4 ring-card', version.is_current ? 'bg-primary' : 'bg-muted-foreground/40')} />
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-bold">
                                                v{version.version} {version.is_current && <span className="ml-1 text-[11px] font-semibold text-primary">(terbaru)</span>}
                                            </span>
                                            <a href={route('evidence.download', [evidence.id, version.id])} className="text-xs font-semibold text-primary hover:underline" target="_blank" rel="noreferrer">
                                                Unduh
                                            </a>
                                        </div>
                                        <div className="truncate text-xs text-muted-foreground">
                                            {version.original_name ?? version.url} {version.size ? `· ${formatBytes(version.size)}` : ''}
                                        </div>
                                        <div className="text-[11px] text-muted-foreground">
                                            {version.uploader} · {formatDateTime(version.created_at)}
                                        </div>
                                        {version.notes && <div className="mt-1 text-xs">{version.notes}</div>}
                                    </li>
                                ))}
                            </ol>
                        </CardContent>
                    </Card>

                    {evidence.verifications.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Riwayat verifikasi</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                {evidence.verifications.map((item, index) => (
                                    <div key={index} className="rounded-xl bg-muted/50 p-3 text-xs">
                                        <div className="flex items-center justify-between">
                                            <StatusBadge tone={item.decision === 'accepted' ? 'success' : 'danger'}>{item.decision === 'accepted' ? 'Sah' : 'Ditolak'}</StatusBadge>
                                            <span className="text-muted-foreground">{formatDateTime(item.verified_at)}</span>
                                        </div>
                                        <div className="mt-1.5 font-semibold">{item.verifier}</div>
                                        {item.notes && <p className="mt-0.5 text-muted-foreground">{item.notes}</p>}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            {editing && <MetadataDialog evidence={evidence} categories={categories} units={units} periods={periods} onClose={() => setEditing(false)} />}
            {newVersion && <VersionDialog evidenceId={evidence.id} maxUploadMb={maxUploadMb} allowedExtensions={allowedExtensions} onClose={() => setNewVersion(false)} />}
            {verifying && <VerifyDialog evidenceId={evidence.id} decision={verifying} onClose={() => setVerifying(null)} />}
        </>
    );
}

function Info({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div>
            <div className="text-[11px] font-semibold text-muted-foreground uppercase">{label}</div>
            <div className="mt-0.5 font-medium">{value ?? '—'}</div>
        </div>
    );
}

function MetadataDialog({ evidence, categories, units, periods, onClose }: { evidence: Detail; categories: Option[]; units: Option[]; periods: Option[]; onClose: () => void }) {
    const form = useForm({
        title: evidence.title,
        code: evidence.code,
        description: evidence.description ?? '',
        evidence_category_id: evidence.evidence_category_id ? String(evidence.evidence_category_id) : '',
        unit: evidence.unit ?? '',
        year: evidence.year ?? new Date().getFullYear(),
        academic_period_id: evidence.academic_period_id ? String(evidence.academic_period_id) : '',
        valid_until: evidence.valid_until ?? '',
        confidentiality: evidence.confidentiality,
    });

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title="Ubah metadata" onSubmit={() => form.put(route('evidence.update', evidence.id), { preserveScroll: true, onSuccess: onClose })} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-6">
                <FormField label="Judul" error={form.errors.title} required className="sm:col-span-4">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </FormField>
                <FormField label="Kode" error={form.errors.code} required className="sm:col-span-2">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Kategori" className="sm:col-span-3">
                    <SelectField value={form.data.evidence_category_id} onChange={(v) => form.setData('evidence_category_id', v)} options={categories} />
                </FormField>
                <FormField label="Unit pemilik" className="sm:col-span-3">
                    <Combobox value={form.data.unit} onChange={(v) => form.setData('unit', v)} options={units} />
                </FormField>
                <FormField label="Tahun" className="sm:col-span-2">
                    <Input type="number" value={form.data.year} onChange={(e) => form.setData('year', Number(e.target.value))} />
                </FormField>
                <FormField label="Periode" className="sm:col-span-2">
                    <SelectField value={form.data.academic_period_id} onChange={(v) => form.setData('academic_period_id', v)} options={periods} />
                </FormField>
                <FormField label="Berlaku sampai" className="sm:col-span-2">
                    <Input type="date" value={form.data.valid_until} onChange={(e) => form.setData('valid_until', e.target.value)} />
                </FormField>
                <FormField label="Kerahasiaan" className="sm:col-span-6">
                    <SelectField value={form.data.confidentiality} onChange={(v) => form.setData('confidentiality', v)} options={Object.entries(confidentialityLabels).map(([value, label]) => ({ value, label }))} />
                </FormField>
                <FormField label="Keterangan" className="sm:col-span-6">
                    <Textarea rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}

function VersionDialog({ evidenceId, maxUploadMb, allowedExtensions, onClose }: { evidenceId: number; maxUploadMb: number; allowedExtensions: string[]; onClose: () => void }) {
    const form = useForm({ mode: 'file', file: null as File | null, url: '', notes: '' });

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title="Unggah versi baru"
            description="Versi baru akan diverifikasi ulang. Versi sebelumnya tetap tersimpan."
            onSubmit={() => {
                form.transform((d) => ({ ...d, file: d.mode === 'file' ? d.file : null, url: d.mode === 'link' ? d.url : '' }));
                form.post(route('evidence.versions.store', evidenceId), { forceFormData: true, preserveScroll: true, onSuccess: onClose });
            }}
            processing={form.processing}
            submitLabel="Unggah versi"
        >
            <Tabs value={form.data.mode} onValueChange={(v) => form.setData('mode', v)}>
                <TabsList className="mb-3 grid w-full grid-cols-2">
                    <TabsTrigger value="file">Berkas</TabsTrigger>
                    <TabsTrigger value="link">Tautan</TabsTrigger>
                </TabsList>
                <TabsContent value="file">
                    <FormField error={form.errors.file} hint={`${allowedExtensions.join(', ').toUpperCase()} · maks. ${maxUploadMb} MB`}>
                        <Input type="file" accept={allowedExtensions.map((e) => `.${e}`).join(',')} onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
                    </FormField>
                </TabsContent>
                <TabsContent value="link">
                    <FormField error={form.errors.url}>
                        <Input value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} placeholder="https://" />
                    </FormField>
                </TabsContent>
            </Tabs>
            <FormField label="Catatan perubahan" error={form.errors.notes} className="mt-4">
                <Textarea rows={2} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Apa yang berubah dari versi sebelumnya?" />
            </FormField>
        </FormDialog>
    );
}

function VerifyDialog({ evidenceId, decision, onClose }: { evidenceId: number; decision: 'accepted' | 'rejected'; onClose: () => void }) {
    const form = useForm({ decision, notes: '' });

    return (
        <FormDialog
            open
            onOpenChange={(o) => !o && onClose()}
            title={decision === 'accepted' ? 'Nyatakan dokumen sah?' : 'Tolak dokumen?'}
            onSubmit={() => form.post(route('evidence.verify', evidenceId), { preserveScroll: true, onSuccess: onClose })}
            processing={form.processing}
            submitLabel={decision === 'accepted' ? 'Verifikasi' : 'Tolak'}
        >
            <FormField label={decision === 'accepted' ? 'Catatan (opsional)' : 'Alasan penolakan'} error={form.errors.notes} required={decision === 'rejected'}>
                <Textarea rows={4} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}
