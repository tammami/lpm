import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
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
import { formatDate } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Program {
    id: number;
    code: string;
    name: string;
    degree: string;
    full_name: string;
    faculty_id: number;
    faculty: string | null;
    accreditation_body_id: number | null;
    accreditation_body: string | null;
    accreditation_status: string | null;
    accreditation_valid_until: string | null;
    accreditation_sk_number: string | null;
    head_name: string | null;
    is_active: boolean;
    lecturers_count: number;
    students_count: number;
    courses_count: number;
}

interface Props {
    programs: Paginated<Program>;
    filters: { search?: string; sort?: string; faculty_id?: string; accreditation_body_id?: string };
    faculties: Option[];
    accreditationBodies: Option[];
    canManage: boolean;
}

const degrees = ['D3', 'D4', 'S1', 'S2', 'S3', 'Profesi'].map((d) => ({ value: d, label: d }));
const statuses = ['Unggul', 'Baik Sekali', 'Baik', 'A', 'B', 'C', 'Terakreditasi Sementara', 'Belum Terakreditasi'].map((s) => ({ value: s, label: s }));

function expiryTone(date: string | null) {
    if (!date) return 'neutral' as const;
    const days = (new Date(date).getTime() - Date.now()) / 86_400_000;
    if (days < 0) return 'danger' as const;
    if (days < 180) return 'warning' as const;
    return 'success' as const;
}

export default function StudyPrograms({ programs, filters, faculties, accreditationBodies, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        faculty_id: filters.faculty_id ?? 'all',
        accreditation_body_id: filters.accreditation_body_id ?? 'all',
    });
    const [editing, setEditing] = useState<Program | 'new' | null>(null);

    const columns: Column<Program>[] = [
        {
            key: 'name',
            header: 'Program studi',
            sortable: true,
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.full_name}</div>
                    <div className="text-xs text-muted-foreground">
                        <span className="font-mono">{row.code}</span> · {row.faculty}
                    </div>
                </div>
            ),
        },
        { key: 'body', header: 'Lembaga', cell: (row) => (row.accreditation_body ? <StatusBadge tone="gold" dot={false}>{row.accreditation_body}</StatusBadge> : '—') },
        {
            key: 'accreditation_valid_until',
            header: 'Akreditasi',
            sortable: true,
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.accreditation_status ?? '—'}</div>
                    {row.accreditation_valid_until && (
                        <StatusBadge tone={expiryTone(row.accreditation_valid_until)} className="mt-1">
                            s.d. {formatDate(row.accreditation_valid_until)}
                        </StatusBadge>
                    )}
                </div>
            ),
        },
        {
            key: 'counts',
            header: 'Data',
            cell: (row) => (
                <div className="text-xs leading-5 text-muted-foreground tabular">
                    <div>{row.lecturers_count} dosen · {row.students_count} mhs</div>
                    <div>{row.courses_count} mata kuliah</div>
                </div>
            ),
        },
        { key: 'head', header: 'Kaprodi', cell: (row) => <span className="text-muted-foreground">{row.head_name ?? '—'}</span> },
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
                            title={`Hapus ${row.full_name}?`}
                            description="Prodi yang sudah memiliki dosen, mahasiswa, atau mata kuliah tidak dapat dihapus."
                            href={route('study-programs.destroy', row.id)}
                            confirmLabel="Hapus"
                        />
                    </RowActions>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Program Studi"
                description="Setiap prodi terhubung ke fakultas dan lembaga akreditasinya (LAMDIK, LAMGAMA, dll.) — dapat diubah kapan saja tanpa mengubah kode."
                breadcrumbs={[{ label: 'Organisasi' }, { label: 'Program Studi' }]}
                actions={
                    canManage && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Tambah prodi
                        </Button>
                    )
                }
            />
            <DataTable
                columns={columns}
                data={programs.data}
                pagination={programs}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari prodi…">
                        <SelectField value={query.faculty_id} onChange={(v) => setFilter('faculty_id', v)} options={faculties} allLabel="Semua fakultas" />
                        <SelectField value={query.accreditation_body_id} onChange={(v) => setFilter('accreditation_body_id', v)} options={accreditationBodies} allLabel="Semua lembaga" />
                    </TableToolbar>
                }
                emptyTitle="Program studi tidak ditemukan"
            />
            {editing !== null && (
                <ProgramForm program={editing === 'new' ? null : editing} faculties={faculties} bodies={accreditationBodies} onClose={() => setEditing(null)} />
            )}
        </>
    );
}

function ProgramForm({ program, faculties, bodies, onClose }: { program: Program | null; faculties: Option[]; bodies: Option[]; onClose: () => void }) {
    const form = useForm({
        faculty_id: program?.faculty_id ? String(program.faculty_id) : '',
        accreditation_body_id: program?.accreditation_body_id ? String(program.accreditation_body_id) : '',
        code: program?.code ?? '',
        name: program?.name ?? '',
        degree: program?.degree ?? 'S1',
        accreditation_status: program?.accreditation_status ?? '',
        accreditation_valid_until: program?.accreditation_valid_until ?? '',
        accreditation_sk_number: program?.accreditation_sk_number ?? '',
        head_name: program?.head_name ?? '',
        is_active: program?.is_active ?? true,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (program) form.put(route('study-programs.update', program.id), options);
        else form.post(route('study-programs.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={program ? 'Ubah program studi' : 'Tambah program studi'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-6">
                <FormField label="Kode" error={form.errors.code} required className="sm:col-span-2">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Jenjang" error={form.errors.degree} required className="sm:col-span-1">
                    <SelectField value={form.data.degree} onChange={(v) => form.setData('degree', v)} options={degrees} />
                </FormField>
                <FormField label="Nama program studi" error={form.errors.name} required className="sm:col-span-3">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Fakultas" error={form.errors.faculty_id} required className="sm:col-span-3">
                    <SelectField value={form.data.faculty_id} onChange={(v) => form.setData('faculty_id', v)} options={faculties} placeholder="Pilih fakultas" />
                </FormField>
                <FormField label="Lembaga akreditasi" error={form.errors.accreditation_body_id} className="sm:col-span-3">
                    <SelectField value={form.data.accreditation_body_id} onChange={(v) => form.setData('accreditation_body_id', v)} options={bodies} placeholder="Pilih LAM" />
                </FormField>
                <FormField label="Status akreditasi" error={form.errors.accreditation_status} className="sm:col-span-2">
                    <SelectField value={form.data.accreditation_status} onChange={(v) => form.setData('accreditation_status', v)} options={statuses} placeholder="Pilih status" />
                </FormField>
                <FormField label="Berlaku sampai" error={form.errors.accreditation_valid_until} className="sm:col-span-2">
                    <Input type="date" value={form.data.accreditation_valid_until} onChange={(e) => form.setData('accreditation_valid_until', e.target.value)} />
                </FormField>
                <FormField label="Nomor SK" error={form.errors.accreditation_sk_number} className="sm:col-span-2">
                    <Input value={form.data.accreditation_sk_number} onChange={(e) => form.setData('accreditation_sk_number', e.target.value)} />
                </FormField>
                <FormField label="Ketua program studi" error={form.errors.head_name} className="sm:col-span-6">
                    <Input value={form.data.head_name} onChange={(e) => form.setData('head_name', e.target.value)} />
                </FormField>
                <div className="sm:col-span-6">
                    <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Prodi aktif" />
                </div>
            </div>
        </FormDialog>
    );
}
