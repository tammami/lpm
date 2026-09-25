import { useForm } from '@inertiajs/react';
import { GripVertical, LoaderCircle, Plus, Save, Sparkles, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { SelectField } from '@/components/select-field';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { AnswerScale, BuilderOption, BuilderQuestion, BuilderSection, QuestionTypeOption } from './types';

interface Props {
    open: boolean;
    onClose: () => void;
    question: BuilderQuestion | null;
    sectionId: number;
    sections: BuilderSection[];
    questionTypes: QuestionTypeOption[];
    answerScales: AnswerScale[];
    editable: boolean;
    scaleMax: number;
}

export function QuestionEditor({ open, onClose, question, sectionId, sections, questionTypes, answerScales, editable, scaleMax }: Props) {
    const defaultScale = answerScales.find((s) => s.question_type === 'likert');

    const form = useForm({
        instrument_section_id: String(question?.instrument_section_id ?? sectionId),
        code: question?.code ?? '',
        label: question?.label ?? '',
        description: question?.description ?? '',
        type: question?.type ?? 'likert',
        category: question?.category ?? '',
        indicator: question?.indicator ?? '',
        is_required: question?.is_required ?? true,
        is_scored: question?.is_scored ?? true,
        weight: question?.weight ?? 1,
        min_score: (question?.min_score ?? '') as number | string,
        max_score: (question?.max_score ?? '') as number | string,
        requires_evidence: question?.requires_evidence ?? false,
        visible_to_evaluatee: question?.visible_to_evaluatee ?? true,
        is_active: question?.is_active ?? true,
        settings: (question?.settings ?? {}) as Record<string, string | number | boolean | null>,
        options: (question?.options ?? defaultScale?.options ?? []).map((o) => ({ label: o.label, value: String(o.value), score: o.score ?? '' })) as BuilderOption[],
    });

    const type = questionTypes.find((t) => t.value === form.data.type);
    const errors = form.errors as Record<string, string>;

    const changeType = (value: string) => {
        const next = questionTypes.find((t) => t.value === value);
        form.setData((data) => {
            let options = data.options;
            if (next?.has_options && options.length === 0) {
                const preset = answerScales.find((s) => s.question_type === value) ?? (value === 'likert' ? defaultScale : undefined);
                options = (preset?.options ?? []).map((o) => ({ label: o.label, value: String(o.value), score: o.score ?? '' }));
            }
            return { ...data, type: value, options, is_scored: next?.scorable ? data.is_scored : false };
        });
    };

    const applyScale = (scale: AnswerScale) =>
        form.setData('options', scale.options.map((o) => ({ label: o.label, value: String(o.value), score: o.score ?? '' })));

    const setOption = (index: number, key: keyof BuilderOption, value: string) =>
        form.setData(
            'options',
            form.data.options.map((option, i) => (i === index ? { ...option, [key]: value } : option)),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (question) form.put(route('instrument-questions.update', question.id), options);
        else form.post(route('instrument-questions.store', Number(form.data.instrument_section_id)), options);
    };

    return (
        <Sheet open={open} onOpenChange={(value) => !value && onClose()}>
            <SheetContent className="w-full gap-0 p-0 sm:max-w-xl">
                <form onSubmit={submit} className="flex h-full flex-col">
                    <SheetHeader className="border-b px-6 py-5">
                        <SheetTitle className="text-lg">{question ? `Butir ${question.code}` : 'Pertanyaan baru'}</SheetTitle>
                        <SheetDescription>{editable ? 'Atur isi, tipe jawaban, dan cara penilaian butir.' : 'Versi terkunci — hanya dapat dilihat.'}</SheetDescription>
                    </SheetHeader>

                    <fieldset disabled={!editable} className="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                            <FormField label="Bagian" error={errors.instrument_section_id} className="sm:col-span-3">
                                <SelectField
                                    value={form.data.instrument_section_id}
                                    onChange={(v) => form.setData('instrument_section_id', v)}
                                    options={sections.map((s) => ({ value: s.id, label: `${s.code}. ${s.title}` }))}
                                    disabled={!editable}
                                />
                            </FormField>
                            <FormField label="Kode" error={errors.code} hint={question ? undefined : 'Otomatis bila kosong'}>
                                <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} placeholder="A1" />
                            </FormField>
                            <FormField label="Pertanyaan / pernyataan" error={errors.label} required className="sm:col-span-4">
                                <Textarea rows={3} value={form.data.label} onChange={(e) => form.setData('label', e.target.value)} placeholder="Dosen menyampaikan RPS di awal perkuliahan." autoFocus={!question} />
                            </FormField>
                            <FormField label="Petunjuk tambahan" error={errors.description} className="sm:col-span-4">
                                <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Opsional, tampil di bawah pertanyaan" />
                            </FormField>
                            <FormField label="Tipe jawaban" error={errors.type} required className="sm:col-span-4">
                                <SelectField value={form.data.type} onChange={changeType} options={questionTypes} disabled={!editable} />
                            </FormField>
                        </div>

                        {type?.has_options && (
                            <div className="mt-5 rounded-xl border bg-muted/30 p-4">
                                <div className="mb-3 flex items-center justify-between">
                                    <div>
                                        <div className="text-sm font-bold">Opsi jawaban</div>
                                        <div className="text-xs text-muted-foreground">Skor kosong = tidak dihitung (mis. "Tidak berlaku").</div>
                                    </div>
                                    {editable && (
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button type="button" variant="outline" size="sm">
                                                    <Sparkles /> Preset skala
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuLabel>Terapkan preset</DropdownMenuLabel>
                                                {answerScales.map((scale) => (
                                                    <DropdownMenuItem key={scale.id} onSelect={() => applyScale(scale)}>
                                                        {scale.name}
                                                    </DropdownMenuItem>
                                                ))}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    )}
                                </div>
                                <div className="grid grid-cols-[16px_1fr_72px_72px_32px] items-center gap-2 px-1 pb-1 text-[11px] font-semibold text-muted-foreground uppercase">
                                    <span />
                                    <span>Label</span>
                                    <span>Nilai</span>
                                    <span>Skor</span>
                                    <span />
                                </div>
                                <div className="flex flex-col gap-2">
                                    {form.data.options.map((option, index) => (
                                        <div key={index} className="grid grid-cols-[16px_1fr_72px_72px_32px] items-center gap-2">
                                            <GripVertical className="size-4 text-muted-foreground/40" />
                                            <Input value={option.label} onChange={(e) => setOption(index, 'label', e.target.value)} className="h-8 bg-card" aria-invalid={!!errors[`options.${index}.label`]} />
                                            <Input value={option.value} onChange={(e) => setOption(index, 'value', e.target.value)} className="h-8 bg-card font-mono text-xs" />
                                            <Input type="number" step="0.01" value={option.score ?? ''} onChange={(e) => setOption(index, 'score', e.target.value)} className="h-8 bg-card tabular" />
                                            {editable && (
                                                <Button type="button" variant="ghost" size="icon-sm" onClick={() => form.setData('options', form.data.options.filter((_, i) => i !== index))} aria-label="Hapus opsi">
                                                    <Trash2 className="text-muted-foreground" />
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                {editable && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="mt-2 text-primary"
                                        onClick={() => form.setData('options', [...form.data.options, { label: '', value: String(form.data.options.length + 1), score: '' }])}
                                    >
                                        <Plus /> Tambah opsi
                                    </Button>
                                )}
                            </div>
                        )}

                        {form.data.type === 'rating' && (
                            <FormField label="Jumlah bintang" className="mt-5 max-w-40">
                                <Input type="number" min={3} max={10} value={Number(form.data.settings.max_rating ?? 5)} onChange={(e) => form.setData('settings', { ...form.data.settings, max_rating: Number(e.target.value) })} />
                            </FormField>
                        )}
                        {form.data.type === 'numeric' && (
                            <div className="mt-5 grid grid-cols-2 gap-4">
                                <FormField label="Nilai minimum">
                                    <Input type="number" value={String(form.data.settings.min ?? '')} onChange={(e) => form.setData('settings', { ...form.data.settings, min: e.target.value === '' ? null : Number(e.target.value) })} />
                                </FormField>
                                <FormField label="Nilai maksimum">
                                    <Input type="number" value={String(form.data.settings.max ?? '')} onChange={(e) => form.setData('settings', { ...form.data.settings, max: e.target.value === '' ? null : Number(e.target.value) })} />
                                </FormField>
                            </div>
                        )}

                        <Separator className="my-6" />

                        <div className="text-sm font-bold">Penilaian</div>
                        <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <label className="flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5 sm:col-span-3">
                                <div>
                                    <div className="text-[13px] font-semibold">Dihitung dalam skor</div>
                                    <div className="text-xs text-muted-foreground">{type?.scorable ? `Skor dinormalisasi ke skala instrumen (maks. ${scaleMax}).` : 'Tipe ini tidak menghasilkan skor.'}</div>
                                </div>
                                <Switch checked={form.data.is_scored} onCheckedChange={(v) => form.setData('is_scored', v)} disabled={!editable || !type?.scorable} />
                            </label>
                            <FormField label="Bobot" error={errors.weight} hint="Untuk skor berbobot">
                                <Input type="number" step="0.1" min={0} value={form.data.weight} onChange={(e) => form.setData('weight', Number(e.target.value))} disabled={!form.data.is_scored} />
                            </FormField>
                            <FormField label="Skor min." error={errors.min_score}>
                                <Input type="number" step="0.01" value={form.data.min_score ?? ''} onChange={(e) => form.setData('min_score', e.target.value)} disabled={!form.data.is_scored} />
                            </FormField>
                            <FormField label="Skor maks." error={errors.max_score}>
                                <Input type="number" step="0.01" value={form.data.max_score ?? ''} onChange={(e) => form.setData('max_score', e.target.value)} disabled={!form.data.is_scored} />
                            </FormField>
                        </div>

                        <Separator className="my-6" />

                        <div className="text-sm font-bold">Metadata & visibilitas</div>
                        <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <FormField label="Kategori" error={errors.category}>
                                <Input value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} placeholder="Perencanaan" />
                            </FormField>
                            <FormField label="Indikator" error={errors.indicator}>
                                <Input value={form.data.indicator} onChange={(e) => form.setData('indicator', e.target.value)} placeholder="Ketersediaan RPS" />
                            </FormField>
                        </div>
                        <div className="mt-4 grid gap-2">
                            <ToggleRow label="Wajib diisi" checked={form.data.is_required} onChange={(v) => form.setData('is_required', v)} />
                            <ToggleRow
                                label="Tampilkan ke pihak yang dievaluasi"
                                description="Untuk komentar terbuka: apakah dosen boleh membaca jawaban (tetap anonim)."
                                checked={form.data.visible_to_evaluatee}
                                onChange={(v) => form.setData('visible_to_evaluatee', v)}
                            />
                            <ToggleRow label="Memerlukan bukti dokumen" description="Dipakai terutama pada instrumen AMI." checked={form.data.requires_evidence} onChange={(v) => form.setData('requires_evidence', v)} />
                            <ToggleRow label="Butir aktif" description="Butir nonaktif tidak ditampilkan kepada responden." checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} />
                        </div>
                    </fieldset>

                    {editable && (
                        <div className="flex justify-end gap-2 border-t bg-muted/40 px-6 py-4">
                            <Button type="button" variant="outline" onClick={onClose}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? <LoaderCircle className="animate-spin" /> : <Save />} Simpan butir
                            </Button>
                        </div>
                    )}
                </form>
            </SheetContent>
        </Sheet>
    );
}

function ToggleRow({ label, description, checked, onChange }: { label: string; description?: string; checked: boolean; onChange: (v: boolean) => void }) {
    return (
        <label className="flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5">
            <div>
                <div className="text-[13px] font-semibold">{label}</div>
                {description && <div className="text-xs text-muted-foreground">{description}</div>}
            </div>
            <Switch checked={checked} onCheckedChange={onChange} />
        </label>
    );
}
