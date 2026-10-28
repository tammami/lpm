import { useForm } from '@inertiajs/react';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { ActivityFormOptions, LpmActivity } from '@/lib/activities';

interface Props extends ActivityFormOptions {
    activity: LpmActivity | null;
    defaultYear?: string;
    onClose: () => void;
}

/**
 * Tambah/ubah kegiatan program kerja LPM.
 */
export function ActivityForm({ activity, categories, statuses, defaultYear, onClose }: Props) {
    const today = new Date().toISOString().slice(0, 10);
    const defaultStart = defaultYear && defaultYear !== today.slice(0, 4) ? `${defaultYear}-01-01` : today;
    const form = useForm({
        title: activity?.title ?? '',
        category: activity?.category ?? 'meeting',
        description: activity?.description ?? '',
        target_output: activity?.target_output ?? '',
        start_date: activity?.start_date ?? defaultStart,
        end_date: activity?.end_date ?? defaultStart,
        location: activity?.location ?? '',
        pic: activity?.pic ?? '',
        participants: activity?.participants ?? '',
        budget: activity?.budget !== null && activity?.budget !== undefined ? String(activity.budget) : '',
        status: activity?.status ?? 'planned',
        realization_note: activity?.realization_note ?? '',
    });

    const submit = () => {
        form.transform((data) => ({ ...data, budget: data.budget === '' ? null : Number(data.budget.replace(/\D/g, '')) }));
        const options = { preserveScroll: true, onSuccess: onClose };
        if (activity) form.put(route('activities.update', activity.id), options);
        else form.post(route('activities.store'), options);
    };

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={activity ? 'Ubah kegiatan' : 'Tambah kegiatan program kerja'}
            description="Kegiatan LPM di luar Monev dan AMI. Bukti pelaksanaan diunggah setelah kegiatan tersimpan."
            onSubmit={submit}
            processing={form.processing}
            size="lg"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Nama kegiatan" error={form.errors.title} required className="sm:col-span-2">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Mis. Workshop penyusunan dokumen SPMI" autoFocus />
                </FormField>
                <FormField label="Jenis kegiatan" error={form.errors.category} required>
                    <SelectField value={form.data.category} onChange={(v) => form.setData('category', v)} options={categories} />
                </FormField>
                <FormField label="Status" error={form.errors.status} required>
                    <SelectField value={form.data.status} onChange={(v) => form.setData('status', v as typeof form.data.status)} options={statuses} />
                </FormField>
                <FormField label="Tanggal mulai" error={form.errors.start_date} required>
                    <Input
                        type="date"
                        value={form.data.start_date}
                        onChange={(e) => {
                            const value = e.target.value;
                            form.setData((data) => ({ ...data, start_date: value, end_date: !data.end_date || data.end_date < value ? value : data.end_date }));
                        }}
                    />
                </FormField>
                <FormField label="Tanggal selesai" error={form.errors.end_date} hint="Sama dengan tanggal mulai bila kegiatan sehari." required>
                    <Input type="date" value={form.data.end_date} min={form.data.start_date} onChange={(e) => form.setData('end_date', e.target.value)} />
                </FormField>
                <FormField label="Penanggung jawab" error={form.errors.pic}>
                    <Input value={form.data.pic} onChange={(e) => form.setData('pic', e.target.value)} placeholder="Mis. Sekretaris LPM" />
                </FormField>
                <FormField label="Tempat" error={form.errors.location}>
                    <Input value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} placeholder="Mis. Aula kampus / daring" />
                </FormField>
                <FormField label="Sasaran / peserta" error={form.errors.participants}>
                    <Input value={form.data.participants} onChange={(e) => form.setData('participants', e.target.value)} placeholder="Mis. Kaprodi dan GPM" />
                </FormField>
                <FormField label="Anggaran (Rp)" error={form.errors.budget} hint="Opsional.">
                    <Input inputMode="numeric" value={form.data.budget} onChange={(e) => form.setData('budget', e.target.value.replace(/\D/g, ''))} placeholder="0" />
                </FormField>
                <FormField label="Deskripsi" error={form.errors.description} className="sm:col-span-2">
                    <Textarea rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
                <FormField label="Target / luaran" error={form.errors.target_output} className="sm:col-span-2">
                    <Textarea rows={2} value={form.data.target_output} onChange={(e) => form.setData('target_output', e.target.value)} placeholder="Mis. Dokumen manual mutu revisi 2026 disahkan" />
                </FormField>
                <FormField label="Catatan realisasi / hasil" error={form.errors.realization_note} className="sm:col-span-2" hint="Isi setelah kegiatan berjalan atau selesai.">
                    <Textarea rows={2} value={form.data.realization_note} onChange={(e) => form.setData('realization_note', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
