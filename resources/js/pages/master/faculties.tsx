import { useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { StatusBadge } from '@/components/status-badge';
import { SwitchField } from '@/components/switch-field';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useQueryFilters } from '@/hooks/use-query-filters';
import type { Paginated } from '@/types';

interface Faculty {
    id: number;
    code: string;
    name: string;
    dean_name: string | null;
    is_active: boolean;
    study_programs_count: number;
}

interface Props {
    faculties: Paginated<Faculty>;
    filters: { search?: string; sort?: string };
    canManage: boolean;
}

export default function Faculties({ faculties, filters, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({ search: filters.search ?? '', sort: filters.sort ?? '' });
    const [editing, setEditing] = useState<Faculty | 'new' | null>(null);

    const columns: Column<Faculty>[] = [
        { key: 'code', header: 'Kode', sortable: true, cell: (row) => <span className="font-mono text-xs font-semibold">{row.code}</span>, className: 'w-28' },
        {
            key: 'name',
            header: 'Nama fakultas',
            sortable: true,
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.name}</div>
                    <div className="text-xs text-muted-foreground">Dekan: {row.dean_name ?? '—'}</div>
                </div>
            ),
        },
        { key: 'programs', header: 'Program studi', cell: (row) => <span className="tabular">{row.study_programs_count} prodi</span> },
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
                            description="Fakultas yang masih memiliki program studi tidak dapat dihapus."
                            href={route('faculties.destroy', row.id)}
                            confirmLabel="Hapus"
                        />
                    </RowActions>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Fakultas"
                description="Struktur fakultas di bawah institusi. Program studi dikelompokkan per fakultas."
                breadcrumbs={[{ label: 'Organisasi' }, { label: 'Fakultas' }]}
                actions={
                    canManage && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Tambah fakultas
                        </Button>
                    )
                }
            />
            <DataTable
                columns={columns}
                data={faculties.data}
                pagination={faculties}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={<TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari kode atau nama fakultas…" />}
                emptyTitle="Belum ada fakultas"
                emptyDescription="Tambahkan fakultas pertama untuk mulai menyusun struktur organisasi."
            />
            {editing !== null && <FacultyForm faculty={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </>
    );
}

function FacultyForm({ faculty, onClose }: { faculty: Faculty | null; onClose: () => void }) {
    const form = useForm({
        code: faculty?.code ?? '',
        name: faculty?.name ?? '',
        dean_name: faculty?.dean_name ?? '',
        is_active: faculty?.is_active ?? true,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (faculty) form.put(route('faculties.update', faculty.id), options);
        else form.post(route('faculties.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={faculty ? 'Ubah fakultas' : 'Tambah fakultas'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} placeholder="FTK" />
                </FormField>
                <FormField label="Nama fakultas" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Fakultas Tarbiyah dan Keguruan" />
                </FormField>
                <FormField label="Nama dekan" error={form.errors.dean_name} className="sm:col-span-3">
                    <Input value={form.data.dean_name} onChange={(e) => form.setData('dean_name', e.target.value)} />
                </FormField>
                <div className="sm:col-span-3">
                    <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Aktif" description="Fakultas nonaktif disembunyikan dari pilihan." />
                </div>
            </div>
        </FormDialog>
    );
}
