import { useForm } from '@inertiajs/react';
import { Link2, Upload } from 'lucide-react';
import { Combobox } from '@/components/combobox';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

export interface EvidenceFormOptions {
    categories: Option[];
    units: Option[];
    periods: Option[];
    maxUploadMb: number;
    allowedExtensions: string[];
}

interface Props extends EvidenceFormOptions {
    onClose: () => void;
    /** Bila diisi, dokumen langsung ditautkan ke objek ini setelah diunggah. */
    mapTo?: { type: string; id: number; context?: number | null; label?: string };
    defaultUnit?: string | null;
    defaultTitle?: string;
}

export function EvidenceUploadDialog({ onClose, mapTo, categories, units, periods, maxUploadMb, allowedExtensions, defaultUnit, defaultTitle }: Props) {
    const form = useForm({
        mode: 'file',
        title: defaultTitle ?? '',
        code: '',
        description: '',
        evidence_category_id: '',
        unit: defaultUnit ?? '',
        year: new Date().getFullYear(),
        academic_period_id: '',
        valid_until: '',
        confidentiality: 'internal',
        file: null as File | null,
        url: '',
        map_type: mapTo?.type ?? '',
        map_id: mapTo?.id ?? '',
        map_context: mapTo?.context ?? '',
    });

    const submit = () => {
        form.transform((data) => ({ ...data, file: data.mode === 'file' ? data.file : null, url: data.mode === 'link' ? data.url : '' }));
        form.post(route('evidence.store'), { forceFormData: true, preserveScroll: true, onSuccess: onClose });
    };

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title="Unggah dokumen bukti"
            description={mapTo?.label ? `Dokumen akan ditautkan ke: ${mapTo.label}` : 'Dokumen masuk ke repositori dan dapat dipakai ulang untuk banyak kebutuhan.'}
            onSubmit={submit}
            processing={form.processing}
            submitLabel="Unggah"
            size="lg"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-6">
                <FormField label="Judul dokumen" error={form.errors.title} required className="sm:col-span-4">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="RPS Matematika Diskrit 2026" autoFocus />
                </FormField>
                <FormField label="Kode" error={form.errors.code} hint="Otomatis bila kosong" className="sm:col-span-2">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} placeholder="RPS-MTK-001" />
                </FormField>
                <div className="sm:col-span-6">
                    <Tabs value={form.data.mode} onValueChange={(v) => form.setData('mode', v)}>
                        <TabsList className="mb-3 grid w-full grid-cols-2">
                            <TabsTrigger value="file">
                                <Upload className="size-4" /> Unggah berkas
                            </TabsTrigger>
                            <TabsTrigger value="link">
                                <Link2 className="size-4" /> Tautan dokumen
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="file">
                            <label
                                className={cn(
                                    'flex cursor-pointer flex-col items-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-6 text-center transition hover:border-primary/40 hover:bg-secondary/40',
                                    form.data.file && 'border-primary/40 bg-secondary/40',
                                    form.errors.file && 'border-destructive/50',
                                )}
                            >
                                <Upload className="size-5 text-primary" />
                                <span className="text-sm font-semibold">{form.data.file ? form.data.file.name : 'Pilih berkas'}</span>
                                <span className="text-xs text-muted-foreground">
                                    {allowedExtensions.join(', ').toUpperCase()} · maks. {maxUploadMb} MB
                                </span>
                                <input type="file" className="sr-only" accept={allowedExtensions.map((e) => `.${e}`).join(',')} onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
                            </label>
                            {form.errors.file && <p className="mt-1.5 text-xs font-medium text-destructive">{form.errors.file}</p>}
                        </TabsContent>
                        <TabsContent value="link">
                            <FormField error={form.errors.url} hint="Mis. tautan Google Drive/OneDrive/SIAKAD yang dapat diakses tim mutu.">
                                <Input value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} placeholder="https://" />
                            </FormField>
                        </TabsContent>
                    </Tabs>
                </div>
                <FormField label="Kategori" error={form.errors.evidence_category_id} className="sm:col-span-3">
                    <SelectField value={form.data.evidence_category_id} onChange={(v) => form.setData('evidence_category_id', v)} options={categories} placeholder="Pilih kategori" />
                </FormField>
                <FormField label="Unit pemilik" error={form.errors.unit} className="sm:col-span-3">
                    <Combobox value={form.data.unit} onChange={(v) => form.setData('unit', v)} options={units} placeholder="Prodi / fakultas / unit…" />
                </FormField>
                <FormField label="Tahun" error={form.errors.year} className="sm:col-span-2">
                    <Input type="number" value={form.data.year} onChange={(e) => form.setData('year', Number(e.target.value))} />
                </FormField>
                <FormField label="Periode (opsional)" error={form.errors.academic_period_id} className="sm:col-span-2">
                    <SelectField value={form.data.academic_period_id} onChange={(v) => form.setData('academic_period_id', v)} options={periods} placeholder="—" />
                </FormField>
                <FormField label="Berlaku sampai" error={form.errors.valid_until} className="sm:col-span-2" hint="Untuk SK/sertifikat">
                    <Input type="date" value={form.data.valid_until} onChange={(e) => form.setData('valid_until', e.target.value)} />
                </FormField>
                <FormField label="Keterangan" error={form.errors.description} className="sm:col-span-6">
                    <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
