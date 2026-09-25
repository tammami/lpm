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
    lecturers: Option[];
    canManage: boolean;
}

export default function ClassesIndex({ classes, filters, periods, studyPrograms, courses, lecturers, canManage }: Props) {
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
                        <SelectField value={query.period_id} onChange={(v) => setFilter('period_id', v)} options={periods} />
                        <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" />
                    </TableToolbar>
                }
                emptyTitle="Belum ada kelas pada periode ini"
                emptyDescription="Buka kelas baru atau impor penugasan mengajar dari Excel."
            />
            {creating && (
                <ClassForm
                    periodId={query.period_id}
                    periods={periods}
                    courses={courses}
                    lecturers={lecturers}
                    onClose={() => setCreating(false)}
                />
            )}
        </>
    );
}

function ClassForm({
    periodId,
    periods,
    courses,
    lecturers,
    onClose,
}: {
    periodId: string;
    periods: Option[];
    courses: Option[];
    lecturers: Option[];
    onClose: () => void;
}) {
    const form = useForm({ academic_period_id: periodId, course_id: '', code: 'A', capacity: 40, lecturer_ids: [] as string[] });
    const courseOptions = useMemo(() => courses, [courses]);

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title="Buka kelas baru"
            description="Dosen pertama yang dipilih menjadi koordinator."
            onSubmit={() => form.post(route('classes.store'), { onSuccess: onClose })}
            processing={form.processing}
            submitLabel="Buat kelas"
        >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <FormField label="Periode" error={form.errors.academic_period_id} required className="sm:col-span-4">
                    <SelectField value={form.data.academic_period_id} onChange={(v) => form.setData('academic_period_id', v)} options={periods} />
                </FormField>
                <FormField label="Mata kuliah" error={form.errors.course_id} required className="sm:col-span-4">
                    <Combobox value={form.data.course_id} onChange={(v) => form.setData('course_id', v)} options={courseOptions} placeholder="Cari mata kuliah…" invalid={!!form.errors.course_id} />
                </FormField>
                <FormField label="Kode kelas" error={form.errors.code} required className="sm:col-span-2">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Kapasitas" error={form.errors.capacity} className="sm:col-span-2">
                    <Input type="number" value={form.data.capacity} onChange={(e) => form.setData('capacity', Number(e.target.value))} />
                </FormField>
                <FormField label="Dosen pengampu" error={form.errors.lecturer_ids} className="sm:col-span-4">
                    <MultiCombobox values={form.data.lecturer_ids} onChange={(v) => form.setData('lecturer_ids', v)} options={lecturers} placeholder="Pilih dosen…" firstLabel="Koord." />
                </FormField>
            </div>
            <p className="mt-4 text-xs text-muted-foreground">
                Setelah kelas dibuat, Anda akan diarahkan ke halaman detail untuk menambahkan mahasiswa. Untuk input massal gunakan menu Impor Data.
            </p>
        </FormDialog>
    );
}
