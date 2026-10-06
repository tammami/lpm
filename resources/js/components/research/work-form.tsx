import { useForm } from '@inertiajs/react';
import { Combobox } from '@/components/combobox';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { type ScientificWork, venueLabels, type WorkFormOptions } from '@/lib/research';
import type { Option } from '@/types';

interface Props extends WorkFormOptions {
    work: ScientificWork | null;
    /** Diisi pada halaman pengelola: pilih dosen pemilik karya. Kosong = karya milik pengguna sendiri. */
    lecturers?: Option[];
    defaultLecturerId?: string;
    storeUrl: string;
    updateUrl: (work: ScientificWork) => string;
    onClose: () => void;
}

/**
 * Formulir isian karya ilmiah (tanpa unggah berkas). Kolom menyesuaikan jenis karya.
 */
export function WorkForm({ work, lecturers, defaultLecturerId, storeUrl, updateUrl, onClose, types, authorRoles, sources, fundings }: Props) {
    const form = useForm({
        lecturer_id: work ? String(work.lecturer_id) : (defaultLecturerId ?? ''),
        type: work?.type ?? 'journal_accredited',
        rank: work?.rank ?? '',
        title: work?.title ?? '',
        year: work ? String(work.year) : String(new Date().getFullYear()),
        venue: work?.venue ?? '',
        volume: work?.volume ?? '',
        identifier: work?.identifier ?? '',
        url: work?.url ?? '',
        author_role: work?.author_role ?? 'first',
        co_authors: work?.co_authors ?? '',
        source: work?.source ?? 'research',
        funding: work?.funding ?? 'self',
    });

    const type = types.find((t) => t.value === form.data.type);
    const labels = venueLabels(form.data.type);

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (work) form.put(updateUrl(work), options);
        else form.post(storeUrl, options);
    };

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={work ? 'Ubah karya ilmiah' : 'Tambah karya ilmiah'}
            description={work?.status === 'verified' && !lecturers ? 'Karya ini sudah terverifikasi. Perubahan akan diperiksa ulang oleh LPM.' : 'Cukup isi datanya — tidak perlu mengunggah berkas.'}
            onSubmit={submit}
            processing={form.processing}
            size="lg"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-6">
                {lecturers && (
                    <FormField label="Dosen" error={form.errors.lecturer_id} required className="sm:col-span-6">
                        <Combobox value={form.data.lecturer_id} onChange={(v) => form.setData('lecturer_id', v)} options={lecturers} placeholder="Pilih dosen" searchPlaceholder="Cari nama dosen…" invalid={!!form.errors.lecturer_id} />
                    </FormField>
                )}
                <FormField label="Jenis karya" error={form.errors.type} required className={type?.ranks.length ? 'sm:col-span-4' : 'sm:col-span-6'}>
                    <SelectField value={form.data.type} onChange={(v) => form.setData((data) => ({ ...data, type: v, rank: '' }))} options={types} />
                </FormField>
                {!!type?.ranks.length && (
                    <FormField label="Peringkat / indeksasi" error={form.errors.rank} required className="sm:col-span-2">
                        <SelectField value={form.data.rank} onChange={(v) => form.setData('rank', v)} options={type.ranks} placeholder="Pilih" invalid={!!form.errors.rank} />
                    </FormField>
                )}
                <FormField label="Judul" error={form.errors.title} required className="sm:col-span-6">
                    <Textarea value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} rows={2} />
                </FormField>
                <FormField label={labels.venue} error={form.errors.venue} required className="sm:col-span-4">
                    <Input value={form.data.venue} onChange={(e) => form.setData('venue', e.target.value)} />
                </FormField>
                <FormField label="Tahun terbit" error={form.errors.year} required className="sm:col-span-2">
                    <Input type="number" inputMode="numeric" min={1980} max={new Date().getFullYear() + 1} value={form.data.year} onChange={(e) => form.setData('year', e.target.value)} />
                </FormField>
                <FormField label="Volume / nomor / halaman" error={form.errors.volume} className="sm:col-span-3">
                    <Input value={form.data.volume} onChange={(e) => form.setData('volume', e.target.value)} placeholder="Vol. 5 No. 2, hlm. 101–115" />
                </FormField>
                <FormField label={labels.identifier} error={form.errors.identifier} className="sm:col-span-3">
                    <Input value={form.data.identifier} onChange={(e) => form.setData('identifier', e.target.value)} />
                </FormField>
                <FormField label="DOI atau tautan" error={form.errors.url} hint="Membantu LPM memverifikasi tanpa perlu berkas." className="sm:col-span-6">
                    <Input value={form.data.url} onChange={(e) => form.setData('url', e.target.value)} placeholder="10.1234/abcd.2026.01 atau https://…" />
                </FormField>
                <FormField label="Peran penulis" error={form.errors.author_role} required className="sm:col-span-2">
                    <SelectField value={form.data.author_role} onChange={(v) => form.setData('author_role', v)} options={authorRoles} />
                </FormField>
                <FormField label="Penulis lain" error={form.errors.co_authors} className="sm:col-span-4">
                    <Input value={form.data.co_authors} onChange={(e) => form.setData('co_authors', e.target.value)} placeholder="Pisahkan dengan koma" />
                </FormField>
                <FormField label="Asal kegiatan" error={form.errors.source} required className="sm:col-span-3">
                    <SelectField value={form.data.source} onChange={(v) => form.setData('source', v)} options={sources} />
                </FormField>
                <FormField label="Sumber dana" error={form.errors.funding} required className="sm:col-span-3">
                    <SelectField value={form.data.funding} onChange={(v) => form.setData('funding', v)} options={fundings} />
                </FormField>
            </div>
        </FormDialog>
    );
}
