import { useForm } from '@inertiajs/react';
import { Combobox } from '@/components/combobox';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Option } from '@/types';

export interface FindingFormOptions {
    severityOptions: (Option & { days: number; requires_action: boolean; color: string })[];
    standardOptions: Option[];
    questionOptions: Option[];
    picOptions: Option[];
}

export interface FindingDraft {
    id?: number;
    title?: string;
    description?: string;
    criteria?: string | null;
    effect?: string | null;
    recommendation?: string | null;
    finding_severity_id?: number | string | null;
    quality_standard_id?: number | string | null;
    instrument_question_id?: number | string | null;
    pic_user_id?: number | string | null;
    due_date?: string | null;
}

const addDays = (days: number) => {
    const date = new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
};

export function FindingForm({ auditId, draft, options, onClose }: { auditId: number; draft: FindingDraft; options: FindingFormOptions; onClose: () => void }) {
    const firstSeverity = options.severityOptions[1] ?? options.severityOptions[0];
    const form = useForm({
        title: draft.title ?? '',
        description: draft.description ?? '',
        criteria: draft.criteria ?? '',
        effect: draft.effect ?? '',
        recommendation: draft.recommendation ?? '',
        finding_severity_id: draft.finding_severity_id ? String(draft.finding_severity_id) : firstSeverity ? String(firstSeverity.value) : '',
        quality_standard_id: draft.quality_standard_id ? String(draft.quality_standard_id) : '',
        instrument_question_id: draft.instrument_question_id ? String(draft.instrument_question_id) : '',
        pic_user_id: draft.pic_user_id ? String(draft.pic_user_id) : '',
        due_date: draft.due_date ?? (firstSeverity ? addDays(firstSeverity.days) : ''),
    });

    const changeSeverity = (value: string) => {
        const severity = options.severityOptions.find((s) => String(s.value) === value);
        form.setData((d) => ({ ...d, finding_severity_id: value, due_date: draft.id ? d.due_date : severity ? addDays(severity.days) : d.due_date }));
    };

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (draft.id) form.put(route('ami.findings.update', draft.id), opts);
        else form.post(route('ami.findings.store', auditId), opts);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={draft.id ? 'Ubah temuan' : 'Catat temuan'} description="Tulis temuan dengan pola Kondisi – Kriteria – Akibat – Rekomendasi." onSubmit={submit} processing={form.processing} size="xl">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-6">
                <FormField label="Judul temuan" error={form.errors.title} required className="md:col-span-4">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="RPS belum tersedia untuk sebagian mata kuliah" autoFocus />
                </FormField>
                <FormField label="Kategori" error={form.errors.finding_severity_id} required className="md:col-span-2">
                    <SelectField value={form.data.finding_severity_id} onChange={changeSeverity} options={options.severityOptions} />
                </FormField>
                <FormField label="Kondisi (apa yang ditemukan)" error={form.errors.description} required className="md:col-span-3">
                    <Textarea rows={4} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Dari 11 mata kuliah semester ganjil, 3 belum memiliki RPS yang disahkan." />
                </FormField>
                <FormField label="Kriteria (acuan standar)" error={form.errors.criteria} className="md:col-span-3">
                    <Textarea rows={4} value={form.data.criteria} onChange={(e) => form.setData('criteria', e.target.value)} placeholder="Standar Proses: seluruh mata kuliah wajib memiliki RPS." />
                </FormField>
                <FormField label="Akibat / risiko" error={form.errors.effect} className="md:col-span-3">
                    <Textarea rows={3} value={form.data.effect} onChange={(e) => form.setData('effect', e.target.value)} />
                </FormField>
                <FormField label="Rekomendasi auditor" error={form.errors.recommendation} className="md:col-span-3">
                    <Textarea rows={3} value={form.data.recommendation} onChange={(e) => form.setData('recommendation', e.target.value)} />
                </FormField>
                <FormField label="Standar terkait" error={form.errors.quality_standard_id} className="md:col-span-3">
                    <Combobox value={form.data.quality_standard_id} onChange={(v) => form.setData('quality_standard_id', v)} options={options.standardOptions} placeholder="Pilih standar" />
                </FormField>
                <FormField label="Butir instrumen" error={form.errors.instrument_question_id} className="md:col-span-3">
                    <Combobox value={form.data.instrument_question_id} onChange={(v) => form.setData('instrument_question_id', v)} options={options.questionOptions} placeholder="Opsional" />
                </FormField>
                <FormField label="PIC auditee" error={form.errors.pic_user_id} hint="Default: PIC auditee pada jadwal audit." className="md:col-span-4">
                    <Combobox value={form.data.pic_user_id} onChange={(v) => form.setData('pic_user_id', v)} options={options.picOptions} placeholder="Pilih PIC" />
                </FormField>
                <FormField label="Tenggat tindak lanjut" error={form.errors.due_date} className="md:col-span-2">
                    <Input type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
