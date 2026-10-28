import { useForm } from '@inertiajs/react';
import { FileCheck2, Upload } from 'lucide-react';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Achievement, AchievementFormOptions } from '@/lib/achievements';
import { formatBytes } from '@/lib/format';

interface Props extends AchievementFormOptions {
    achievement: Achievement | null;
    onClose: () => void;
}

/**
 * Formulir prestasi yang diisi mahasiswa sendiri. Bukti: sertifikat/piagam (PDF/JPG/PNG) atau tautan.
 */
export function AchievementForm({ achievement, categories, levels, participations, achievementSuggestions, maxUploadMb, onClose }: Props) {
    const form = useForm({
        title: achievement?.title ?? '',
        category: achievement?.category ?? 'academic',
        level: achievement?.level ?? '',
        achievement: achievement?.achievement ?? '',
        participation: achievement?.participation ?? 'individual',
        organizer: achievement?.organizer ?? '',
        achieved_on: achievement?.achieved_on ?? '',
        description: achievement?.description ?? '',
        evidence_url: achievement?.evidence_url ?? '',
        certificate: null as File | null,
    });

    const submit = () => {
        if (form.data.certificate && form.data.certificate.size > maxUploadMb * 1024 * 1024) {
            form.setError('certificate', `Ukuran sertifikat melebihi ${maxUploadMb} MB.`);
            return;
        }
        const options = { preserveScroll: true, forceFormData: true, onSuccess: onClose };
        if (achievement) {
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(route('achievements.mine.update', achievement.id), options);
        } else {
            form.post(route('achievements.mine.store'), options);
        }
    };

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={achievement ? 'Ubah prestasi' : 'Catat prestasi'}
            description="Data akan diperiksa bidang kemahasiswaan. Pastikan sertifikat atau tautan bukti memuat nama Anda."
            onSubmit={submit}
            processing={form.processing}
            size="lg"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Nama kegiatan / lomba" error={form.errors.title} required className="sm:col-span-2">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Mis. Lomba Karya Tulis Ilmiah Nasional 2026" autoFocus />
                </FormField>
                <FormField label="Jenis prestasi" error={form.errors.category} required>
                    <SelectField value={form.data.category} onChange={(v) => form.setData('category', v)} options={categories} />
                </FormField>
                <FormField label="Tingkat" error={form.errors.level} required>
                    <SelectField value={form.data.level} onChange={(v) => form.setData('level', v)} options={levels} placeholder="Pilih tingkat…" />
                </FormField>
                <FormField label="Prestasi yang dicapai" error={form.errors.achievement} required hint="Pilih dari saran atau ketik sendiri.">
                    <Input value={form.data.achievement} onChange={(e) => form.setData('achievement', e.target.value)} list="achievement-suggestions" placeholder="Mis. Juara 1" maxLength={100} />
                    <datalist id="achievement-suggestions">
                        {achievementSuggestions.map((suggestion) => (
                            <option key={suggestion} value={suggestion} />
                        ))}
                    </datalist>
                </FormField>
                <FormField label="Keikutsertaan" error={form.errors.participation} required>
                    <SelectField value={form.data.participation} onChange={(v) => form.setData('participation', v)} options={participations} />
                </FormField>
                <FormField label="Penyelenggara" error={form.errors.organizer} required>
                    <Input value={form.data.organizer} onChange={(e) => form.setData('organizer', e.target.value)} placeholder="Mis. Kementerian Agama RI" />
                </FormField>
                <FormField label="Tanggal diperoleh" error={form.errors.achieved_on} required>
                    <Input type="date" value={form.data.achieved_on} max={new Date().toISOString().slice(0, 10)} onChange={(e) => form.setData('achieved_on', e.target.value)} />
                </FormField>
                <FormField
                    label="Sertifikat / piagam"
                    error={form.errors.certificate}
                    hint={achievement?.certificate_name && !form.data.certificate ? `Tersimpan: ${achievement.certificate_name}. Pilih berkas baru untuk mengganti.` : `PDF, JPG, atau PNG — maks. ${maxUploadMb} MB.`}
                    className="sm:col-span-2"
                >
                    <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-dashed bg-muted/20 px-4 py-3 text-sm transition hover:border-primary/40">
                        {form.data.certificate ? <FileCheck2 className="size-5 shrink-0 text-primary" /> : <Upload className="size-5 shrink-0 text-muted-foreground" />}
                        <span className="min-w-0 flex-1 truncate">
                            {form.data.certificate ? `${form.data.certificate.name} · ${formatBytes(form.data.certificate.size)}` : 'Pilih berkas sertifikat…'}
                        </span>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="sr-only" onChange={(e) => form.setData('certificate', e.target.files?.[0] ?? null)} />
                    </label>
                </FormField>
                <FormField label="Tautan bukti" error={form.errors.evidence_url} hint="Opsional bila sertifikat diunggah — mis. berita, pengumuman pemenang, atau Google Drive." className="sm:col-span-2">
                    <Input type="url" value={form.data.evidence_url} onChange={(e) => form.setData('evidence_url', e.target.value)} placeholder="https://" />
                </FormField>
                <FormField label="Keterangan" error={form.errors.description} className="sm:col-span-2">
                    <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="Mis. nama tim, dosen pembimbing, atau cabang lomba" />
                </FormField>
            </div>
        </FormDialog>
    );
}
