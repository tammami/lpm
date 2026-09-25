import { useForm } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import { useQueryFilters } from '@/hooks/use-query-filters';
import type { Paginated } from '@/types';

interface Body {
    id: number;
    code: string;
    name: string;
    description: string | null;
    website: string | null;
    is_active: boolean;
    study_programs_count: number;
}

export default function AccreditationBodies({ bodies, filters }: { bodies: Paginated<Body>; filters: { search?: string; sort?: string } }) {
    const { filters: query, setFilter } = useQueryFilters({ search: filters.search ?? '', sort: filters.sort ?? '' });
    const [editing, setEditing] = useState<Body | 'new' | null>(null);

    const columns: Column<Body>[] = [
        { key: 'code', header: 'Kode', sortable: true, className: 'w-32', cell: (row) => <StatusBadge tone="gold" dot={false}>{row.code}</StatusBadge> },
        {
            key: 'name',
            header: 'Lembaga',
            sortable: true,
            cell: (row) => (
                <div className="max-w-xl">
                    <div className="font-semibold">{row.name}</div>
                    {row.description && <div className="line-clamp-2 text-xs text-muted-foreground">{row.description}</div>}
                </div>
            ),
        },
        { key: 'programs', header: 'Prodi', cell: (row) => <span className="tabular">{row.study_programs_count}</span> },
        {
            key: 'website',
            header: 'Situs',
            cell: (row) =>
                row.website ? (
                    <a href={row.website} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-primary hover:underline">
                        Kunjungi <ExternalLink className="size-3" />
                    </a>
                ) : (
                    '—'
                ),
        },
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
                        title={`Hapus ${row.code}?`}
                        href={route('accreditation-bodies.destroy', row.id)}
                        confirmLabel="Hapus"
                    />
                </RowActions>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Lembaga Akreditasi"
                description="Master lembaga akreditasi (LAM/BAN-PT). Tidak ditanam di kode — tambahkan lembaga baru kapan pun regulasi berubah."
                breadcrumbs={[{ label: 'Organisasi' }, { label: 'Lembaga Akreditasi' }]}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> Tambah lembaga
                    </Button>
                }
            />
            <DataTable
                columns={columns}
                data={bodies.data}
                pagination={bodies}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={<TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari lembaga…" />}
            />
            {editing !== null && <BodyForm body={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
        </>
    );
}

function BodyForm({ body, onClose }: { body: Body | null; onClose: () => void }) {
    const form = useForm({
        code: body?.code ?? '',
        name: body?.name ?? '',
        description: body?.description ?? '',
        website: body?.website ?? '',
        is_active: body?.is_active ?? true,
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (body) form.put(route('accreditation-bodies.update', body.id), options);
        else form.post(route('accreditation-bodies.store'), options);
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={body ? 'Ubah lembaga akreditasi' : 'Tambah lembaga akreditasi'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <FormField label="Kode" error={form.errors.code} required>
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase())} placeholder="LAMDIK" />
                </FormField>
                <FormField label="Nama lembaga" error={form.errors.name} required className="sm:col-span-2">
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Keterangan" error={form.errors.description} className="sm:col-span-3">
                    <Textarea rows={3} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
                <FormField label="Situs web" error={form.errors.website} className="sm:col-span-3">
                    <Input value={form.data.website} onChange={(e) => form.setData('website', e.target.value)} placeholder="https://" />
                </FormField>
                <div className="sm:col-span-3">
                    <SwitchField checked={form.data.is_active} onChange={(v) => form.setData('is_active', v)} label="Aktif" />
                </div>
            </div>
        </FormDialog>
    );
}
