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
import type { Option, Paginated } from '@/types';

interface Unit {
    id: number;
    code: string;
    name: string;
    type: string;
    faculty_id: number | null;
    faculty: string | null;
    head_name: string | null;
    is_active: boolean;
}

interface Props {
    units: Paginated<Unit>;
    filters: { search?: string; sort?: string; type?: string };
    types: Option[];
    faculties: Option[];
    canManage: boolean;
}

export default function Units({ units, filters, types, faculties, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({ search: filters.search ?? '', sort: filters.sort ?? '', type: filters.type ?? 'all' });
    const [editing, setEditing] = useState<Unit | 'new' | null>(null);
    const typeLabel = (value: string) => types.find((t) => t.value === value)?.label ?? value;

    const columns: Column<Unit>[] = [
        { key: 'code', header: 'Kode', sortable: true, className: 'w-28', cell: (row) => <span className="font-mono text-xs font-semibold">{row.code}</span> },
        {
            key: 'name',
            header: 'Unit kerja',
            sortable: true,
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.name}</div>
                    <div className="text-xs text-muted-foreground">Pimpinan: {row.head_name ?? '—'}</div>
                </div>
            ),
        },
        { key: 'type', header: 'Jenis', sortable: true, cell: (row) => <StatusBadge tone="primary" dot={false}>{typeLabel(row.type)}</StatusBadge> },
        { key: 'faculty', header: 'Fakultas', cell: (row) => <span className="text-muted-foreground">{row.faculty ?? 'Tingkat institusi'}</span> },
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
                            href={route('units.destroy', row.id)}
                            confirmLabel="Hapus"
                        />
                    </RowActions>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Unit Kerja"
                description="Lembaga, biro, dan UPT yang dapat menjadi auditee AMI maupun pemilik dokumen bukti."
                breadcrumbs={[{ label: 'Organisasi' }, { label: 'Unit Kerja' }]}
                actions={
                    canManage && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Tambah unit
                        </Button>
                    )
                }
            />
            <DataTable
                columns={columns}
                data={units.data}
                pagination={units}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari unit…">
                        <SelectField value={query.type} onChange={(v) => setFilter('type', v)} options={types} allLabel="Semua jenis" />
                    </TableToolbar>
                }
            />
            {editing !== null && <UnitForm unit={editing === 'new' ? null : editing} types={types} faculties={faculties} onClose={() => setEditing(null)} />}
        </>
    );
}

function UnitForm({ unit, types, faculties, onClose }: { unit: Unit | null; types: Option[]; faculties: Option[]; onClose: () => void }) {
    const form = useForm({
        code: unit?.code ?? '',
        name: unit?.name ?? '',
        type: unit?.type ?? 'unit',
        faculty_id: unit?.faculty_id ? String(unit.faculty_id) : '',
        head_name: unit?.head_name ?? '',
        is_active: unit?.is_active ?? true,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        form.transform((data) => ({ ...data, faculty_id: data.faculty_id === 'none' ? '' : data.faculty_id }));
        if (unit) form.put(route('units.update', unit.id), options);
        else form.post(route('units.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={unit ? 'Ubah unit kerja' : 'Tambah unit kerja'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Nama unit" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Jenis" error={form.errors.type} required>
                    <SelectField value={form.data.type} onChange={(v) => form.setData('type', v)} options={types} />
                </FormField>
                <FormField label="Fakultas (opsional)" error={form.errors.faculty_id} className="sm:col-span-2">
                    <SelectField value={form.data.faculty_id} onChange={(v) => form.setData('faculty_id', v)} options={[{ value: 'none', label: 'Tingkat institusi' }, ...faculties]} />
                </FormField>
                <FormField label="Nama pimpinan unit" error={form.errors.head_name} className="sm:col-span-3">
                    <Input value={form.data.head_name} onChange={(e) => form.setData('head_name', e.target.value)} />
                </FormField>
                <div className="sm:col-span-3">
                    <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Unit aktif" />
                </div>
            </div>
        </FormDialog>
    );
}
