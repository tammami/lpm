import { useForm } from '@inertiajs/react';
import { Download, KeyRound, Pencil, Plus, Trash2 } from 'lucide-react';
import { cleanQuery } from '@/lib/utils';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { SelectField } from '@/components/select-field';
import { EnumBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { fromNow } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Student {
    id: number;
    nim: string;
    name: string;
    email: string | null;
    phone: string | null;
    gender: string | null;
    entry_year: number;
    semester: number;
    status: string;
    status_label: string;
    study_program_id: number;
    study_program: string | null;
    classes_count: number;
    has_account: boolean;
    last_login_at: string | null;
}

interface Props {
    students: Paginated<Student>;
    filters: Record<string, string | undefined>;
    studyPrograms: Option[];
    statuses: Option[];
    entryYears: Option[];
    canManage: boolean;
}

export default function Students({ students, filters, studyPrograms, statuses, entryYears, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        study_program_id: filters.study_program_id ?? 'all',
        entry_year: filters.entry_year ?? 'all',
        status: filters.status ?? 'all',
    });
    const [editing, setEditing] = useState<Student | 'new' | null>(null);

    const columns: Column<Student>[] = [
        { key: 'nim', header: 'NIM', sortable: true, className: 'w-32', cell: (row) => <span className="font-mono text-xs font-semibold">{row.nim}</span> },
        {
            key: 'name',
            header: 'Nama',
            sortable: true,
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.name}</div>
                    <div className="text-xs text-muted-foreground">{row.email ?? '—'}</div>
                </div>
            ),
        },
        { key: 'program', header: 'Prodi', cell: (row) => <span className="text-muted-foreground">{row.study_program}</span> },
        { key: 'entry_year', header: 'Angkatan', sortable: true, cell: (row) => <span className="tabular">{row.entry_year}</span> },
        { key: 'semester', header: 'Smt', sortable: true, cell: (row) => <span className="tabular">{row.semester}</span> },
        { key: 'status', header: 'Status', cell: (row) => <EnumBadge value={row.status} label={row.status_label} /> },
        {
            key: 'login',
            header: 'Login terakhir',
            cell: (row) => <span className="text-xs text-muted-foreground">{row.has_account ? (row.last_login_at ? fromNow(row.last_login_at) : 'Belum pernah') : 'Tanpa akun'}</span>,
        },
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
                        {row.has_account && (
                            <ConfirmDialog
                                trigger={
                                    <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
                                        <KeyRound /> Reset kata sandi
                                    </DropdownMenuItem>
                                }
                                title="Reset kata sandi mahasiswa?"
                                description={`Kata sandi ${row.name} akan direset menjadi NIM dan wajib diganti saat login berikutnya.`}
                                href={route('students.reset-password', row.id)}
                                method="post"
                                destructive={false}
                                confirmLabel="Reset"
                            />
                        )}
                        <DropdownMenuSeparator />
                        <ConfirmDialog
                            trigger={
                                <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                    <Trash2 /> Hapus
                                </DropdownMenuItem>
                            }
                            title={`Hapus ${row.name}?`}
                            href={route('students.destroy', row.id)}
                            confirmLabel="Hapus"
                        />
                    </RowActions>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Mahasiswa"
                description="Data mahasiswa sebagai responden e-Monev. Akun login menggunakan NIM."
                breadcrumbs={[{ label: 'Master Akademik' }, { label: 'Mahasiswa' }]}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <a href={route('master.export', { type: 'mahasiswa', ...cleanQuery(query) })}>
                                <Download /> Ekspor
                            </a>
                        </Button>
                        {canManage && (
                            <Button onClick={() => setEditing('new')}>
                                <Plus /> Tambah mahasiswa
                            </Button>
                        )}
                    </>
                }
            />
            <DataTable
                columns={columns}
                data={students.data}
                pagination={students}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari NIM, nama, email…">
                        <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" />
                        <SelectField value={query.entry_year} onChange={(v) => setFilter('entry_year', v)} options={entryYears} allLabel="Semua angkatan" />
                        <SelectField value={query.status} onChange={(v) => setFilter('status', v)} options={statuses} allLabel="Semua status" />
                    </TableToolbar>
                }
                emptyTitle="Mahasiswa tidak ditemukan"
            />
            {editing !== null && <StudentForm student={editing === 'new' ? null : editing} studyPrograms={studyPrograms} statuses={statuses} onClose={() => setEditing(null)} />}
        </>
    );
}

function StudentForm({ student, studyPrograms, statuses, onClose }: { student: Student | null; studyPrograms: Option[]; statuses: Option[]; onClose: () => void }) {
    const form = useForm({
        study_program_id: student ? String(student.study_program_id) : studyPrograms.length === 1 ? String(studyPrograms[0].value) : '',
        nim: student?.nim ?? '',
        name: student?.name ?? '',
        email: student?.email ?? '',
        phone: student?.phone ?? '',
        gender: student?.gender ?? '',
        entry_year: student?.entry_year ?? new Date().getFullYear(),
        semester: student?.semester ?? 1,
        status: student?.status ?? 'aktif',
        create_account: !student || !student.has_account,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (student) form.put(route('students.update', student.id), options);
        else form.post(route('students.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={student ? 'Ubah data mahasiswa' : 'Tambah mahasiswa'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-6">
                <FormField label="NIM" error={form.errors.nim} required className="sm:col-span-2">
                    <Input value={form.data.nim} onChange={(e) => form.setData('nim', e.target.value)} inputMode="numeric" />
                </FormField>
                <FormField label="Nama lengkap" error={form.errors.name} required className="sm:col-span-4">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Program studi" error={form.errors.study_program_id} required className="sm:col-span-4">
                    <SelectField value={form.data.study_program_id} onChange={(v) => form.setData('study_program_id', v)} options={studyPrograms} placeholder="Pilih prodi" />
                </FormField>
                <FormField label="Jenis kelamin" error={form.errors.gender} className="sm:col-span-2">
                    <SelectField value={form.data.gender} onChange={(v) => form.setData('gender', v)} options={[{ value: 'L', label: 'Laki-laki' }, { value: 'P', label: 'Perempuan' }]} />
                </FormField>
                <FormField label="Angkatan" error={form.errors.entry_year} required className="sm:col-span-2">
                    <Input type="number" value={form.data.entry_year} onChange={(e) => form.setData('entry_year', Number(e.target.value))} />
                </FormField>
                <FormField label="Semester" error={form.errors.semester} required className="sm:col-span-2">
                    <Input type="number" min={1} max={14} value={form.data.semester} onChange={(e) => form.setData('semester', Number(e.target.value))} />
                </FormField>
                <FormField label="Status" error={form.errors.status} required className="sm:col-span-2">
                    <SelectField value={form.data.status} onChange={(v) => form.setData('status', v)} options={statuses} />
                </FormField>
                <FormField label="Email" error={form.errors.email} className="sm:col-span-3">
                    <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                </FormField>
                <FormField label="No. HP" error={form.errors.phone} className="sm:col-span-3">
                    <Input value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                </FormField>
                {!student?.has_account && (
                    <label className="flex items-center gap-2 text-sm text-muted-foreground sm:col-span-6">
                        <input type="checkbox" checked={form.data.create_account} onChange={(e) => form.setData('create_account', e.target.checked)} className="size-4 accent-[var(--primary)]" />
                        Buatkan akun login (username & kata sandi awal = NIM)
                    </label>
                )}
            </div>
        </FormDialog>
    );
}
