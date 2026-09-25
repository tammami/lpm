import { useForm } from '@inertiajs/react';
import { Download, Pencil, Plus, Trash2 } from 'lucide-react';
import { cleanQuery } from '@/lib/utils';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { SwitchField } from '@/components/switch-field';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useQueryFilters } from '@/hooks/use-query-filters';
import type { Option, Paginated } from '@/types';

interface Course {
    id: number;
    code: string;
    name: string;
    credits: number;
    semester: number;
    type: string;
    study_program_id: number;
    study_program: string | null;
    is_active: boolean;
    classes_count: number;
}

interface Props {
    courses: Paginated<Course>;
    filters: Record<string, string | undefined>;
    studyPrograms: Option[];
    canManage: boolean;
}

const semesters = Array.from({ length: 8 }, (_, i) => ({ value: String(i + 1), label: `Semester ${i + 1}` }));
const types = [
    { value: 'wajib', label: 'Wajib' },
    { value: 'pilihan', label: 'Pilihan' },
];

export default function Courses({ courses, filters, studyPrograms, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        study_program_id: filters.study_program_id ?? 'all',
        semester: filters.semester ?? 'all',
        type: filters.type ?? 'all',
    });
    const [editing, setEditing] = useState<Course | 'new' | null>(null);

    const columns: Column<Course>[] = [
        { key: 'code', header: 'Kode', sortable: true, className: 'w-32', cell: (row) => <span className="font-mono text-xs font-semibold">{row.code}</span> },
        {
            key: 'name',
            header: 'Mata kuliah',
            sortable: true,
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.name}</div>
                    <div className="text-xs text-muted-foreground">{row.study_program}</div>
                </div>
            ),
        },
        { key: 'credits', header: 'SKS', sortable: true, cell: (row) => <span className="tabular">{row.credits}</span> },
        { key: 'semester', header: 'Smt', sortable: true, cell: (row) => <span className="tabular">{row.semester}</span> },
        { key: 'type', header: 'Sifat', cell: (row) => <StatusBadge tone={row.type === 'wajib' ? 'primary' : 'info'} dot={false}>{row.type === 'wajib' ? 'Wajib' : 'Pilihan'}</StatusBadge> },
        { key: 'classes', header: 'Kelas dibuka', cell: (row) => <span className="tabular">{row.classes_count}</span> },
        { key: 'status', header: 'Status', cell: (row) => <StatusBadge tone={row.is_active ? 'success' : 'neutral'}>{row.is_active ? 'Aktif' : 'Nonaktif'}</StatusBadge> },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) =>
                canManage && (
                    <RowActions>
                        <DropdownMenuItem onSelect={() => setEditing(row)}>
                            <Pencil /> Ubah
                        </DropdownMenuItem>
                        <ConfirmDialog
                            trigger={
                                <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                    <Trash2 /> Hapus
                                </DropdownMenuItem>
                            }
                            title={`Hapus ${row.name}?`}
                            description="Mata kuliah yang sudah memiliki kelas tidak dapat dihapus."
                            href={route('courses.destroy', row.id)}
                            confirmLabel="Hapus"
                        />
                    </RowActions>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Mata Kuliah"
                description="Kurikulum per program studi. Kelas dibuka per periode melalui menu Kelas & Pengampu."
                breadcrumbs={[{ label: 'Master Akademik' }, { label: 'Mata Kuliah' }]}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <a href={route('master.export', { type: 'mata-kuliah', ...cleanQuery(query) })}>
                                <Download /> Ekspor
                            </a>
                        </Button>
                        {canManage && (
                            <Button onClick={() => setEditing('new')}>
                                <Plus /> Tambah mata kuliah
                            </Button>
                        )}
                    </>
                }
            />
            <DataTable
                columns={columns}
                data={courses.data}
                pagination={courses}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari kode atau nama…">
                        <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" />
                        <SelectField value={query.semester} onChange={(v) => setFilter('semester', v)} options={semesters} allLabel="Semua semester" />
                        <SelectField value={query.type} onChange={(v) => setFilter('type', v)} options={types} allLabel="Wajib & pilihan" />
                    </TableToolbar>
                }
                emptyTitle="Mata kuliah tidak ditemukan"
            />
            {editing !== null && <CourseForm course={editing === 'new' ? null : editing} studyPrograms={studyPrograms} onClose={() => setEditing(null)} />}
        </>
    );
}

function CourseForm({ course, studyPrograms, onClose }: { course: Course | null; studyPrograms: Option[]; onClose: () => void }) {
    const form = useForm({
        study_program_id: course ? String(course.study_program_id) : studyPrograms.length === 1 ? String(studyPrograms[0].value) : '',
        code: course?.code ?? '',
        name: course?.name ?? '',
        credits: course?.credits ?? 2,
        semester: course?.semester ?? 1,
        type: course?.type ?? 'wajib',
        is_active: course?.is_active ?? true,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (course) form.put(route('courses.update', course.id), options);
        else form.post(route('courses.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={course ? 'Ubah mata kuliah' : 'Tambah mata kuliah'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <FormField label="Program studi" error={form.errors.study_program_id} required className="sm:col-span-4">
                    <SelectField value={form.data.study_program_id} onChange={(v) => form.setData('study_program_id', v)} options={studyPrograms} placeholder="Pilih prodi" />
                </FormField>
                <FormField label="Kode" error={form.errors.code} required className="sm:col-span-1">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Nama mata kuliah" error={form.errors.name} required className="sm:col-span-3">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="SKS" error={form.errors.credits} required>
                    <Input type="number" min={1} max={24} value={form.data.credits} onChange={(e) => form.setData('credits', Number(e.target.value))} />
                </FormField>
                <FormField label="Semester" error={form.errors.semester} required>
                    <Input type="number" min={1} max={14} value={form.data.semester} onChange={(e) => form.setData('semester', Number(e.target.value))} />
                </FormField>
                <FormField label="Sifat" error={form.errors.type} required className="sm:col-span-2">
                    <SelectField value={form.data.type} onChange={(v) => form.setData('type', v)} options={types} />
                </FormField>
                <div className="sm:col-span-4">
                    <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Mata kuliah aktif" />
                </div>
            </div>
        </FormDialog>
    );
}
