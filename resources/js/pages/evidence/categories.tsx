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
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

interface Category {
    id: number;
    code: string;
    name: string;
    description: string | null;
    is_active: boolean;
    evidence_count: number;
}

export default function EvidenceCategories({ categories }: { categories: Category[] }) {
    const [editing, setEditing] = useState<Category | 'new' | null>(null);
    const columns: Column<Category>[] = [
        { key: 'code', header: 'Kode', className: 'w-28', cell: (row) => <span className="font-mono text-xs font-semibold">{row.code}</span> },
        {
            key: 'name',
            header: 'Kategori',
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.name}</div>
                    {row.description && <div className="text-xs text-muted-foreground">{row.description}</div>}
                </div>
            ),
        },
        { key: 'count', header: 'Dokumen', cell: (row) => <span className="tabular">{row.evidence_count}</span> },
        { key: 'status', header: 'Status', cell: (row) => <StatusBadge tone={row.is_active ? 'success' : 'neutral'}>{row.is_active ? 'Aktif' : 'Nonaktif'}</StatusBadge> },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
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
                        title={`Hapus kategori ${row.name}?`}
                        href={route('evidence-categories.destroy', row.id)}
                        confirmLabel="Hapus"
                    />
                </RowActions>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Kategori Dokumen"
                breadcrumbs={[{ label: 'Dokumen Bukti', href: route('evidence.index') }, { label: 'Kategori' }]}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Tambah kategori
                    </Button>
                }
            />
            <DataTable columns={columns} data={categories} rowKey={(row) => row.id} />
            {editing && <CategoryForm category={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </>
    );
}

function CategoryForm({ category, onClose }: { category: Category | null; onClose: () => void }) {
    const form = useForm({ code: category?.code ?? '', name: category?.name ?? '', description: category?.description ?? '', is_active: category?.is_active ?? true });
    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (category) form.put(route('evidence-categories.update', category.id), options);
        else form.post(route('evidence-categories.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={category ? 'Ubah kategori' : 'Tambah kategori'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} />
                </FormField>
                <FormField label="Nama" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Keterangan" className="sm:col-span-3">
                    <Textarea rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
                <div className="sm:col-span-3">
                    <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Aktif" />
                </div>
            </div>
        </FormDialog>
    );
}
