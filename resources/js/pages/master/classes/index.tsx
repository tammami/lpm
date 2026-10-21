import { router, useForm } from '@inertiajs/react';
import { ArrowRight, Plus, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Combobox, MultiCombobox } from '@/components/combobox';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useQueryFilters } from '@/hooks/use-query-filters';
import type { Option, Paginated } from '@/types';

interface ClassRow {
    id: number;
    code: string;
    capacity: number | null;
    course_code: string;
    course_name: string;
    credits: number;
    semester: number;
    study_program: string | null;
    students_count: number;
    lecturers: { id: number; name: string; role: string }[];
}

interface Props {
    classes: Paginated<ClassRow>;
    filters: { search?: string; study_program_id?: string; period_id?: number };
    periods: (Option & { is_active: boolean })[];
    studyPrograms: Option[];
    courses: (Option & { study_program_id: number })[];
    lecturers: LecturerOption[];
    cohorts: Cohort[];
    canManage: boolean;
}

export default function ClassesIndex({ classes, filters, periods, studyPrograms, courses, lecturers, cohorts, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        study_program_id: filters.study_program_id ?? 'all',
        period_id: filters.period_id ? String(filters.period_id) : '',
    });
    const [creating, setCreating] = useState(false);

    const columns: Column<ClassRow>[] = [
        {
            key: 'course',
            header: 'Mata kuliah & kelas',
            cell: (row) => (
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-semibold">{row.course_name}</span>
                        <StatusBadge tone="gold" dot={false}>
                            Kelas {row.code}
                        </StatusBadge>
                    </div>
                    <div className="text-xs text-muted-foreground">
                        <span className="font-mono">{row.course_code}</span> · {row.credits} SKS · Smt {row.semester} · {row.study_program}
                    </div>
                </div>
            ),
        },
        {
            key: 'lecturers',
            header: 'Dosen pengampu',
            cell: (row) =>
                row.lecturers.length ? (
                    <div className="flex flex-col gap-0.5">
                        {row.lecturers.map((lecturer) => (
                            <span key={lecturer.id} className="text-[13px]">
                                {lecturer.name}
                                {row.lecturers.length > 1 && lecturer.role === 'koordinator' && <span className="ml-1 text-[11px] text-muted-foreground">(koord.)</span>}
                            </span>
                        ))}
                    </div>
                ) : (
                    <StatusBadge tone="warning">Belum ada pengampu</StatusBadge>
                ),
        },
        {
            key: 'students',
            header: 'Peserta',
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5 tabular">
                    <Users className="size-3.5 text-muted-foreground" />
                    {row.students_count}
                    {row.capacity ? <span className="text-muted-foreground">/ {row.capacity}</span> : null}
                </span>
            ),
        },
        {
            key: 'go',
            header: '',
            className: 'w-10',
            cell: () => <ArrowRight className="size-4 text-muted-foreground" />,
        },
    ];

    return (
        <>
            <PageHeader
                reset={['classes']}
                title="Kelas & Pengampu"
                description="Penugasan dosen dan peserta kelas menentukan siapa yang wajib mengisi Monev (eligibility) — mahasiswa tidak memilih dosen sendiri."
                breadcrumbs={[{ label: 'Master Akademik' }, { label: 'Kelas & Pengampu' }]}
                actions={
                    canManage && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus /> Buka kelas
                        </Button>
                    )
                }
            />
            <DataTable
                columns={columns}
                data={classes.data}
                pagination={classes}
                rowKey={(row) => row.id}
                onRowClick={(row) => router.visit(route('classes.show', row.id))}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari mata kuliah atau dosen…">
                        <SelectField label="Periode akademik" value={query.period_id} onChange={(v) => setFilter('period_id', v)} options={periods} />
                        <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" />
                    </TableToolbar>
                }
                emptyTitle="Belum ada kelas pada periode ini"
                emptyDescription="Buka kelas baru atau impor penugasan mengajar dari Excel."
            />
            {creating && (
                <ClassForm
                    periodId={query.period_id}
                    studyProgramId={query.study_program_id}
                    periods={periods}
                    studyPrograms={studyPrograms}
                    courses={courses}
                    lecturers={lecturers}
                    cohorts={cohorts}
                    onClose={() => setCreating(false)}
                />
            )}
        </>
    );
}

type LecturerOption = Option & { study_program_id: number; study_program: string | null };
type Cohort = { study_program_id: number; entry_year: number; students_count: number };

const NO_COHORT = 'none';

function ClassForm({
    periodId,
    studyProgramId,
    periods,
    studyPrograms,
    courses,
    lecturers,
    cohorts,
    onClose,
}: {
    periodId: string;
    studyProgramId: string;
    periods: Option[];
    studyPrograms: Option[];
    courses: (Option & { study_program_id: number })[];
    lecturers: LecturerOption[];
    cohorts: Cohort[];
    onClose: () => void;
}) {
    const form = useForm({
        academic_period_id: periodId,
        study_program_id: studyProgramId !== 'all' ? studyProgramId : studyPrograms.length === 1 ? String(studyPrograms[0].value) : '',
        course_id: '',
        code: 'A',
        capacity: 40,
        lecturer_ids: [] as string[],
        entry_year: NO_COHORT,
    });
    const programId = Number(form.data.study_program_id);
    const programLabel = studyPrograms.find((p) => String(p.value) === form.data.study_program_id)?.label;

    /** Mata kuliah hanya dari prodi yang dipilih, agar tidak salah pilih. */
    const courseOptions = useMemo(() => (programId ? courses.filter((c) => c.study_program_id === programId) : []), [courses, programId]);

    /** Dosen homebase prodi terpilih ditampilkan lebih dulu; dosen prodi lain tetap bisa dipilih (lintas prodi). */
    const lecturerOptions = useMemo(
        () =>
            [...lecturers]
                .sort((a, b) => Number(b.study_program_id === programId) - Number(a.study_program_id === programId))
                .map((l) => ({ ...l, group: programId ? (l.study_program_id === programId ? `Dosen ${programLabel ?? 'prodi ini'}` : 'Dosen prodi lain') : undefined })),
        [lecturers, programId, programLabel],
    );

    const cohortOptions = useMemo(
        () => [
            { value: NO_COHORT, label: 'Tambahkan peserta nanti di detail kelas' },
            ...cohorts
                .filter((c) => c.study_program_id === programId)
                .map((c) => ({ value: String(c.entry_year), label: `Angkatan ${c.entry_year} · ${c.students_count} mahasiswa aktif` })),
        ],
        [cohorts, programId],
    );
    const cohort = cohorts.find((c) => c.study_program_id === programId && String(c.entry_year) === form.data.entry_year);

    const changeProgram = (value: string) => {
        form.setData((data) => ({ ...data, study_program_id: value, course_id: '', entry_year: NO_COHORT }));
    };

    const submit = () => {
        form.transform((data) => ({ ...data, entry_year: data.entry_year === NO_COHORT ? null : Number(data.entry_year) }));
        form.post(route('classes.store'), { onSuccess: onClose });
    };

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title="Buka kelas baru"
            description="Pilih prodi dulu agar daftar mata kuliah, dosen, dan angkatan ikut tersaring. Dosen pertama yang dipilih menjadi koordinator."
            onSubmit={submit}
            processing={form.processing}
            submitLabel="Buat kelas"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <FormField label="Periode" error={form.errors.academic_period_id} required className="sm:col-span-2">
                    <SelectField value={form.data.academic_period_id} onChange={(v) => form.setData('academic_period_id', v)} options={periods} />
                </FormField>
                <FormField label="Program studi" error={form.errors.study_program_id} required className="sm:col-span-2">
                    <SelectField value={form.data.study_program_id} onChange={changeProgram} options={studyPrograms} placeholder="Pilih prodi…" />
                </FormField>
                <FormField label="Mata kuliah" error={form.errors.course_id} required className="sm:col-span-4">
                    <Combobox
                        value={form.data.course_id}
                        onChange={(v) => form.setData('course_id', v)}
                        options={courseOptions}
                        placeholder={programId ? 'Cari mata kuliah…' : 'Pilih prodi terlebih dahulu'}
                        emptyText={programId ? 'Tidak ada mata kuliah aktif pada prodi ini.' : 'Pilih prodi terlebih dahulu.'}
                        invalid={!!form.errors.course_id}
                    />
                </FormField>
                <FormField label="Kode kelas" error={form.errors.code} required className="sm:col-span-2" hint="Mis. A, B, atau PAI-1.">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Kapasitas" error={form.errors.capacity} className="sm:col-span-2">
                    <Input type="number" value={form.data.capacity} onChange={(e) => form.setData('capacity', Number(e.target.value))} />
                </FormField>
                <FormField label="Dosen pengampu" error={form.errors.lecturer_ids} className="sm:col-span-4" hint="Ketik nama, prodi, atau NIDN. Dosen bernama sama dibedakan lewat prodi & NIDN di bawah namanya.">
                    <MultiCombobox values={form.data.lecturer_ids} onChange={(v) => form.setData('lecturer_ids', v)} options={lecturerOptions} placeholder="Pilih dosen…" searchPlaceholder="Cari nama / prodi / NIDN…" firstLabel="Koord." />
                </FormField>
                <FormField
                    label="Peserta kelas (angkatan)"
                    error={form.errors.entry_year}
                    className="sm:col-span-4"
                    hint={
                        cohort
                            ? `${cohort.students_count} mahasiswa aktif ${programLabel ?? ''} angkatan ${cohort.entry_year} langsung menjadi peserta kelas.${form.data.capacity && cohort.students_count > form.data.capacity ? ' Jumlahnya melebihi kapasitas kelas.' : ''}`
                            : 'Opsional. Pilih angkatan untuk langsung mendaftarkan seluruh mahasiswa aktif prodi ini sebagai peserta.'
                    }
                >
                    <SelectField value={form.data.entry_year} onChange={(v) => form.setData('entry_year', v)} options={cohortOptions} disabled={!programId} />
                </FormField>
            </div>
            <p className="mt-4 text-xs text-muted-foreground">
                Setelah kelas dibuat, Anda akan diarahkan ke halaman detail untuk menyesuaikan peserta. Untuk input massal gunakan menu Impor Data.
            </p>
        </FormDialog>
    );
}
