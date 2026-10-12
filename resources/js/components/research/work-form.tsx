import { useForm } from '@inertiajs/react';
import { Link2 } from 'lucide-react';
import type { ReactNode } from 'react';
import { Combobox } from '@/components/combobox';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { LecturerTagInput, StudentTagInput } from '@/components/research/author-picker';
import { SelectField } from '@/components/select-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { type ScientificWork, type TaggedLecturer, type TaggedStudent, venueLabels, type WorkFormOptions } from '@/lib/research';
import type { Option } from '@/types';

interface Props extends WorkFormOptions {
    work: ScientificWork | null;
    /** Diisi pada halaman pengelola: pilih dosen pemilik karya. Kosong = karya milik pengguna sendiri. */
    lecturers?: Option[];
    defaultLecturerId?: string;
    /** Dosen pengguna sendiri (halaman dosen), agar tidak bisa menandai dirinya. */
    ownLecturerId?: number;
    storeUrl: string;
    updateUrl: (work: ScientificWork) => string;
    onClose: () => void;
}

const yesNoOptions = [
    { value: '1', label: 'Ya' },
    { value: '0', label: 'Tidak' },
];

const boolToField = (value: boolean | null | undefined) => (value === null || value === undefined ? '' : value ? '1' : '0');

/**
 * Formulir karya dosen sesuai template Data Karya Dosen LPM. Bukti cukup berupa tautan; kolom menyesuaikan subjenis.
 */
export function WorkForm({ work, lecturers, defaultLecturerId, ownLecturerId, storeUrl, updateUrl, onClose, types, indexations, authorRoles, sources, fundings, ipStatuses }: Props) {
    const form = useForm({
        lecturer_id: work ? String(work.lecturer_id) : (defaultLecturerId ?? ''),
        type: work?.type ?? 'journal_national',
        indexation: work?.indexation ?? '',
        title: work?.title ?? '',
        year: work ? String(work.year) : String(new Date().getFullYear()),
        venue: work?.venue ?? '',
        volume: work?.volume ?? '',
        identifier: work?.identifier ?? '',
        doi: work?.doi ?? '',
        url: work?.url ?? '',
        evidence_url: work?.evidence_url ?? '',
        ip_number: work?.ip_number ?? '',
        ip_status: work?.ip_status ?? '',
        author_role: work?.author_role ?? 'first',
        author_count: work?.author_count ? String(work.author_count) : '',
        co_authors: work?.co_authors ?? '',
        tagged_lecturers: (work?.lecturers ?? []) as TaggedLecturer[],
        tagged_students: (work?.students ?? []) as TaggedStudent[],
        source: work?.source ?? 'research',
        funding: work?.funding ?? 'self',
        relevant_to_program: boolToField(work?.relevant_to_program),
        relevant_to_roadmap: boolToField(work?.relevant_to_roadmap),
    });

    const type = types.find((t) => t.value === form.data.type);
    const labels = venueLabels(type);
    const errors = form.errors as Record<string, string | undefined>;
    const firstError = (prefix: string) => Object.entries(errors).find(([key]) => key === prefix || key.startsWith(`${prefix}.`))?.[1];
    const single = form.data.author_role === 'single';
    const ownerId = ownLecturerId ?? (form.data.lecturer_id ? Number(form.data.lecturer_id) : null);

    const submit = () => {
        form.transform(({ tagged_lecturers, tagged_students, relevant_to_program, relevant_to_roadmap, ...data }) => ({
            ...data,
            lecturers: single ? [] : tagged_lecturers.map((lecturer) => lecturer.id),
            students: single ? [] : tagged_students.map(({ student_id, name, nim }) => ({ student_id, name, nim })),
            relevant_to_program: relevant_to_program === '' ? null : relevant_to_program === '1',
            relevant_to_roadmap: relevant_to_roadmap === '' ? null : relevant_to_roadmap === '1',
        }));
        const options = { preserveScroll: true, onSuccess: onClose };
        if (work) form.put(updateUrl(work), options);
        else form.post(storeUrl, options);
    };

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={work ? 'Ubah karya' : 'Tambah karya'}
            description={work?.status === 'verified' && !lecturers ? 'Karya ini sudah valid. Perubahan akan diperiksa ulang oleh LPM.' : 'Tidak perlu mengunggah berkas — cukup data dan tautan buktinya.'}
            onSubmit={submit}
            processing={form.processing}
            size="lg"
        >
            <div className="flex flex-col gap-6">
                <Section title="Jenis karya">
                    {lecturers && (
                        <FormField label="Dosen pemilik karya" error={errors.lecturer_id} required className="sm:col-span-6">
                            <Combobox value={form.data.lecturer_id} onChange={(v) => form.setData('lecturer_id', v)} options={lecturers} placeholder="Pilih dosen" searchPlaceholder="Cari nama dosen…" invalid={!!errors.lecturer_id} />
                        </FormField>
                    )}
                    <FormField label="Subjenis karya" error={errors.type} required hint={type ? `Jenis: ${type.group}` : undefined} className={type?.indexed ? 'sm:col-span-3' : 'sm:col-span-6'}>
                        <SelectField value={form.data.type} onChange={(v) => form.setData((data) => ({ ...data, type: v, indexation: '' }))} options={types} />
                    </FormField>
                    {type?.indexed && (
                        <FormField label="Indeksasi / akreditasi" error={errors.indexation} required hint="Peringkat/kuartil terisi otomatis." className="sm:col-span-3">
                            <Combobox value={form.data.indexation} onChange={(v) => form.setData('indexation', v)} options={indexations} placeholder="Pilih indeksasi" searchPlaceholder="Cari: Sinta, Scopus, DOAJ…" invalid={!!errors.indexation} />
                        </FormField>
                    )}
                    {type?.ip && (
                        <>
                            <FormField label="Status HKI" error={errors.ip_status} required className="sm:col-span-3">
                                <SelectField value={form.data.ip_status} onChange={(v) => form.setData('ip_status', v)} options={ipStatuses} placeholder="Pilih status" invalid={!!errors.ip_status} />
                            </FormField>
                            <FormField label="Nomor HKI" error={errors.ip_number} className="sm:col-span-3">
                                <Input value={form.data.ip_number} onChange={(e) => form.setData('ip_number', e.target.value)} placeholder="EC00202612345" />
                            </FormField>
                        </>
                    )}
                </Section>

                <Section title="Identitas karya">
                    <FormField label="Judul karya" error={errors.title} required className="sm:col-span-6">
                        <Textarea value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} rows={2} />
                    </FormField>
                    <FormField label={labels.venue} error={errors.venue} required={type?.category !== 'other'} className="sm:col-span-4">
                        <Input value={form.data.venue} onChange={(e) => form.setData('venue', e.target.value)} />
                    </FormField>
                    <FormField label="Tahun" error={errors.year} required className="sm:col-span-2">
                        <Input type="number" inputMode="numeric" min={1980} max={new Date().getFullYear() + 1} value={form.data.year} onChange={(e) => form.setData('year', e.target.value)} />
                    </FormField>
                    <FormField label="Volume / no. / edisi" error={errors.volume} className="sm:col-span-2">
                        <Input value={form.data.volume} onChange={(e) => form.setData('volume', e.target.value)} placeholder="Vol. 5 No. 2" />
                    </FormField>
                    <FormField label={labels.identifier} error={errors.identifier} className="sm:col-span-2">
                        <Input value={form.data.identifier} onChange={(e) => form.setData('identifier', e.target.value)} />
                    </FormField>
                    <FormField label="DOI" error={errors.doi} className="sm:col-span-2">
                        <Input value={form.data.doi} onChange={(e) => form.setData('doi', e.target.value)} placeholder="10.1234/abc.v5i2" />
                    </FormField>
                </Section>

                <Section title="Penulis">
                    <FormField label="Peran dosen" error={errors.author_role} required className="sm:col-span-3">
                        <SelectField value={form.data.author_role} onChange={(v) => form.setData('author_role', v)} options={authorRoles} />
                    </FormField>
                    <FormField label="Jumlah penulis" error={errors.author_count} hint="Seluruh penulis, termasuk Anda." className="sm:col-span-3">
                        <Input type="number" inputMode="numeric" min={1} max={100} value={form.data.author_count} onChange={(e) => form.setData('author_count', e.target.value)} />
                    </FormField>
                    {single ? (
                        <p className="rounded-lg bg-muted/50 px-3 py-2 text-xs text-muted-foreground sm:col-span-6">Penulis tunggal — tidak ada dosen rekan atau mahasiswa yang ditandai.</p>
                    ) : (
                        <>
                            <FormField label="Mahasiswa yang ikut menulis" error={firstError('students')} hint="Tandai dari data mahasiswa; alumni/mahasiswa yang belum terdaftar cukup ditulis namanya." className="sm:col-span-6">
                                <StudentTagInput value={form.data.tagged_students} onChange={(v) => form.setData('tagged_students', v)} invalid={!!firstError('students')} />
                            </FormField>
                            <FormField label="Dosen rekan IAIA" error={firstError('lecturers')} hint="Karya dicatat sekali dan ikut muncul di daftar dosen yang ditandai — mereka tidak perlu mengisi ulang." className="sm:col-span-6">
                                <LecturerTagInput value={form.data.tagged_lecturers} onChange={(v) => form.setData('tagged_lecturers', v)} excludeId={ownerId} invalid={!!firstError('lecturers')} />
                            </FormField>
                        </>
                    )}
                    <FormField label="Penulis lain (luar IAIA)" error={errors.co_authors} className="sm:col-span-6">
                        <Input value={form.data.co_authors} onChange={(e) => form.setData('co_authors', e.target.value)} placeholder="Pisahkan dengan koma" />
                    </FormField>
                </Section>

                <Section title="Tautan bukti" description="Isi minimal satu. Folder/berkas Google Drive harus dapat dibuka oleh siapa saja yang memiliki link.">
                    <FormField label="Link publikasi" error={errors.url} className="sm:col-span-6">
                        <LinkInput value={form.data.url} onChange={(v) => form.setData('url', v)} placeholder="https://jurnal…/article/view/123" />
                    </FormField>
                    <FormField label="Link bukti" error={errors.evidence_url} hint="PDF, sertifikat, cover buku, sertifikat HKI, dan sejenisnya." className="sm:col-span-6">
                        <LinkInput value={form.data.evidence_url} onChange={(v) => form.setData('evidence_url', v)} placeholder="https://drive.google.com/…" />
                    </FormField>
                </Section>

                <Section title="Lainnya">
                    <FormField label="Sumber dana" error={errors.funding} required className="sm:col-span-3">
                        <SelectField value={form.data.funding} onChange={(v) => form.setData('funding', v)} options={fundings} />
                    </FormField>
                    <FormField label="Asal kegiatan" error={errors.source} required className="sm:col-span-3">
                        <SelectField value={form.data.source} onChange={(v) => form.setData('source', v)} options={sources} />
                    </FormField>
                    <FormField label="Relevan dengan prodi?" error={errors.relevant_to_program} className="sm:col-span-3">
                        <SelectField value={form.data.relevant_to_program} onChange={(v) => form.setData('relevant_to_program', v)} options={yesNoOptions} placeholder="Pilih" />
                    </FormField>
                    <FormField label="Sesuai roadmap penelitian?" error={errors.relevant_to_roadmap} className="sm:col-span-3">
                        <SelectField value={form.data.relevant_to_roadmap} onChange={(v) => form.setData('relevant_to_roadmap', v)} options={yesNoOptions} placeholder="Pilih" />
                    </FormField>
                </Section>
            </div>
        </FormDialog>
    );
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <section>
            <div className="mb-3 border-b pb-1.5">
                <h3 className="text-xs font-bold tracking-wide text-primary uppercase">{title}</h3>
                {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-6">{children}</div>
        </section>
    );
}

function LinkInput({ value, onChange, placeholder }: { value: string; onChange: (value: string) => void; placeholder: string }) {
    return (
        <div className="relative">
            <Link2 className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input inputMode="url" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className="pl-9" />
        </div>
    );
}
