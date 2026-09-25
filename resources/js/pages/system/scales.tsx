import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Star, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { SwitchField } from '@/components/switch-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

interface ClassDef {
    label: string;
    min_score: number;
    max_score: number;
    color: string;
    description?: string | null;
}

interface Scheme {
    id: number;
    name: string;
    scale_min: number;
    scale_max: number;
    description: string | null;
    is_default: boolean;
    used_by: number;
    classes: ClassDef[];
}

interface Scale {
    id: number;
    name: string;
    question_type: string;
    options: { label: string; value: string; score: number | null }[];
    description: string | null;
    is_active: boolean;
}

const colors = [
    { value: 'danger', label: 'Merah (kritis)', swatch: 'bg-destructive' },
    { value: 'warning', label: 'Kuning (perlu perhatian)', swatch: 'bg-warning' },
    { value: 'neutral', label: 'Abu-abu (netral)', swatch: 'bg-muted-foreground' },
    { value: 'info', label: 'Biru (baik)', swatch: 'bg-info' },
    { value: 'success', label: 'Hijau (sangat baik)', swatch: 'bg-success' },
];
const swatch = (color: string) => colors.find((c) => c.value === color)?.swatch ?? 'bg-muted-foreground';
const typeLabels: Record<string, string> = { likert: 'Skala Likert', single_choice: 'Pilihan tunggal', multiple_choice: 'Pilihan ganda', yes_no: 'Ya / Tidak' };

export default function Scales({ schemes, scales }: { schemes: Scheme[]; scales: Scale[] }) {
    const [scheme, setScheme] = useState<Scheme | 'new' | null>(null);
    const [scale, setScale] = useState<Scale | 'new' | null>(null);

    return (
        <>
            <PageHeader
                title="Klasifikasi & Skala"
                description="Klasifikasi skor (mis. Sangat Baik) dan preset opsi jawaban tidak ditanam di kode — sesuaikan dengan kebijakan LPM."
                breadcrumbs={[{ label: 'Sistem' }, { label: 'Klasifikasi & Skala' }]}
            />
            <Tabs defaultValue="schemes">
                <TabsList className="mb-5">
                    <TabsTrigger value="schemes">Klasifikasi skor</TabsTrigger>
                    <TabsTrigger value="scales">Preset skala jawaban</TabsTrigger>
                </TabsList>
                <TabsContent value="schemes">
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {schemes.map((item) => (
                            <Card key={item.id}>
                                <CardHeader className="flex flex-row items-start justify-between gap-3">
                                    <div>
                                        <CardTitle className="flex items-center gap-2">
                                            {item.name} {item.is_default && <StatusBadge tone="gold"><Star className="size-3" /> Bawaan</StatusBadge>}
                                        </CardTitle>
                                        <CardDescription>
                                            Skala {item.scale_min}–{item.scale_max} · dipakai {item.used_by} versi instrumen
                                        </CardDescription>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="icon-sm" onClick={() => setScheme(item)} aria-label="Ubah">
                                            <Pencil />
                                        </Button>
                                        {!item.is_default && (
                                            <ConfirmDialog
                                                trigger={
                                                    <Button variant="ghost" size="icon-sm" aria-label="Hapus">
                                                        <Trash2 className="text-destructive" />
                                                    </Button>
                                                }
                                                title={`Hapus skema ${item.name}?`}
                                                href={route('settings.schemes.destroy', item.id)}
                                                confirmLabel="Hapus"
                                            />
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex h-9 overflow-hidden rounded-lg">
                                        {item.classes.map((c) => (
                                            <div
                                                key={c.label}
                                                className={cn('flex items-center justify-center px-1 text-[11px] font-bold text-white', swatch(c.color))}
                                                style={{ width: `${((c.max_score - c.min_score) / (item.scale_max - item.scale_min)) * 100}%` }}
                                                title={`${c.label}: ${c.min_score}–${c.max_score}`}
                                            >
                                                <span className="truncate">{c.label}</span>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="mt-2 flex justify-between text-[11px] text-muted-foreground tabular">
                                        <span>{item.scale_min}</span>
                                        <span>{item.scale_max}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                        <button type="button" onClick={() => setScheme('new')} className="flex min-h-40 items-center justify-center gap-2 rounded-2xl border-2 border-dashed text-sm font-semibold text-muted-foreground hover:border-primary/40 hover:text-primary">
                            <Plus className="size-4" /> Skema klasifikasi baru
                        </button>
                    </div>
                </TabsContent>
                <TabsContent value="scales">
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {scales.map((item) => (
                            <Card key={item.id} className={cn(!item.is_active && 'opacity-60')}>
                                <CardHeader className="flex flex-row items-start justify-between gap-3">
                                    <div>
                                        <CardTitle>{item.name}</CardTitle>
                                        <CardDescription>{typeLabels[item.question_type] ?? item.question_type}</CardDescription>
                                    </div>
                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="icon-sm" onClick={() => setScale(item)} aria-label="Ubah">
                                            <Pencil />
                                        </Button>
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="icon-sm" aria-label="Hapus">
                                                    <Trash2 className="text-destructive" />
                                                </Button>
                                            }
                                            title={`Hapus preset ${item.name}?`}
                                            href={route('settings.answer-scales.destroy', item.id)}
                                            confirmLabel="Hapus"
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-wrap gap-2">
                                    {item.options.map((option) => (
                                        <span key={option.value} className="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs">
                                            <b>{option.label}</b>
                                            <span className="text-muted-foreground tabular">skor {option.score ?? '—'}</span>
                                        </span>
                                    ))}
                                </CardContent>
                            </Card>
                        ))}
                        <button type="button" onClick={() => setScale('new')} className="flex min-h-32 items-center justify-center gap-2 rounded-2xl border-2 border-dashed text-sm font-semibold text-muted-foreground hover:border-primary/40 hover:text-primary">
                            <Plus className="size-4" /> Preset skala baru
                        </button>
                    </div>
                </TabsContent>
            </Tabs>
            {scheme !== null && <SchemeForm scheme={scheme === 'new' ? null : scheme} onClose={() => setScheme(null)} />}
            {scale !== null && <ScaleForm scale={scale === 'new' ? null : scale} onClose={() => setScale(null)} />}
        </>
    );
}

function SchemeForm({ scheme, onClose }: { scheme: Scheme | null; onClose: () => void }) {
    const form = useForm({
        name: scheme?.name ?? '',
        scale_min: scheme?.scale_min ?? 0,
        scale_max: scheme?.scale_max ?? 4,
        description: scheme?.description ?? '',
        is_default: scheme?.is_default ?? false,
        classes: (scheme?.classes ?? [
            { label: 'Rendah', min_score: 0, max_score: 2, color: 'warning' },
            { label: 'Baik', min_score: 2, max_score: 4, color: 'success' },
        ]) as ClassDef[],
    });
    const errors = form.errors as Record<string, string>;
    const setClass = (index: number, key: keyof ClassDef, value: string | number) =>
        form.setData('classes', form.data.classes.map((c, i) => (i === index ? { ...c, [key]: value } : c)));

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (scheme) form.put(route('settings.schemes.update', scheme.id), options);
        else form.post(route('settings.schemes.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={scheme ? 'Ubah skema klasifikasi' : 'Skema klasifikasi baru'} description="Rentang harus bersambung dan mencakup seluruh skala. Batas bawah kelas pertama inklusif; kelas berikutnya dimulai sesudah batas atas kelas sebelumnya." onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <FormField label="Nama skema" error={errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Skala min." error={errors.scale_min} required>
                    <Input type="number" step="0.1" value={form.data.scale_min} onChange={(e) => form.setData('scale_min', Number(e.target.value))} />
                </FormField>
                <FormField label="Skala maks." error={errors.scale_max} required>
                    <Input type="number" step="0.1" value={form.data.scale_max} onChange={(e) => form.setData('scale_max', Number(e.target.value))} />
                </FormField>
            </div>
            <div className="mt-5 rounded-xl border bg-muted/30 p-4">
                <div className="mb-2 grid grid-cols-[1fr_80px_80px_170px_32px] gap-2 text-[11px] font-semibold text-muted-foreground uppercase">
                    <span>Label</span>
                    <span>Dari</span>
                    <span>Sampai</span>
                    <span>Warna</span>
                    <span />
                </div>
                <div className="flex flex-col gap-2">
                    {form.data.classes.map((c, index) => (
                        <div key={index} className="grid grid-cols-[1fr_80px_80px_170px_32px] items-center gap-2">
                            <Input value={c.label} onChange={(e) => setClass(index, 'label', e.target.value)} className="h-8 bg-card" />
                            <Input type="number" step="0.01" value={c.min_score} onChange={(e) => setClass(index, 'min_score', Number(e.target.value))} className="h-8 bg-card" />
                            <Input type="number" step="0.01" value={c.max_score} onChange={(e) => setClass(index, 'max_score', Number(e.target.value))} className="h-8 bg-card" />
                            <SelectField value={c.color} onChange={(v) => setClass(index, 'color', v)} options={colors} className="h-8" />
                            <Button type="button" variant="ghost" size="icon-sm" onClick={() => form.setData('classes', form.data.classes.filter((_, i) => i !== index))} aria-label="Hapus">
                                <Trash2 className="text-muted-foreground" />
                            </Button>
                        </div>
                    ))}
                </div>
                {errors.classes && <p className="mt-2 text-xs font-medium text-destructive">{errors.classes}</p>}
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="mt-2 text-primary"
                    onClick={() => {
                        const last = form.data.classes[form.data.classes.length - 1];
                        form.setData('classes', [...form.data.classes, { label: '', min_score: last?.max_score ?? form.data.scale_min, max_score: form.data.scale_max, color: 'success' }]);
                    }}
                >
                    <Plus /> Tambah kelas
                </Button>
            </div>
            <div className="mt-4">
                <SwitchField checked={form.data.is_default} onChange={(v) => form.setData('is_default', v)} label="Jadikan skema bawaan" description="Dipakai untuk instrumen baru yang tidak memilih skema." />
            </div>
        </FormDialog>
    );
}

function ScaleForm({ scale, onClose }: { scale: Scale | null; onClose: () => void }) {
    const form = useForm({
        name: scale?.name ?? '',
        question_type: scale?.question_type ?? 'likert',
        description: scale?.description ?? '',
        is_active: scale?.is_active ?? true,
        options: (scale?.options ?? [
            { label: '', value: '1', score: 1 },
            { label: '', value: '2', score: 2 },
        ]) as { label: string; value: string; score: number | string | null }[],
    });
    const setOption = (index: number, key: string, value: string) => form.setData('options', form.data.options.map((o, i) => (i === index ? { ...o, [key]: value } : o)));
    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (scale) form.put(route('settings.answer-scales.update', scale.id), options);
        else form.post(route('settings.answer-scales.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={scale ? 'Ubah preset skala' : 'Preset skala baru'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Nama preset" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Tipe pertanyaan" error={form.errors.question_type} required className="sm:col-span-2">
                    <SelectField value={form.data.question_type} onChange={(v) => form.setData('question_type', v)} options={Object.entries(typeLabels).map(([value, label]) => ({ value, label }))} />
                </FormField>
            </div>
            <div className="mt-4 flex flex-col gap-2">
                {form.data.options.map((option, index) => (
                    <div key={index} className="grid grid-cols-[1fr_72px_72px_32px] gap-2">
                        <Input placeholder="Label" value={option.label} onChange={(e) => setOption(index, 'label', e.target.value)} className="h-8" />
                        <Input placeholder="Nilai" value={option.value} onChange={(e) => setOption(index, 'value', e.target.value)} className="h-8 font-mono text-xs" />
                        <Input placeholder="Skor" type="number" value={option.score ?? ''} onChange={(e) => setOption(index, 'score', e.target.value)} className="h-8" />
                        <Button type="button" variant="ghost" size="icon-sm" onClick={() => form.setData('options', form.data.options.filter((_, i) => i !== index))} aria-label="Hapus">
                            <Trash2 className="text-muted-foreground" />
                        </Button>
                    </div>
                ))}
                <Button type="button" variant="ghost" size="sm" className="self-start text-primary" onClick={() => form.setData('options', [...form.data.options, { label: '', value: String(form.data.options.length + 1), score: '' }])}>
                    <Plus /> Tambah opsi
                </Button>
            </div>
            <div className="mt-4">
                <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Aktif" description="Preset aktif muncul di builder instrumen." />
            </div>
        </FormDialog>
    );
}
