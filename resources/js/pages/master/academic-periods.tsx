import { useForm } from '@inertiajs/react';
import { CalendarCheck2, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDate } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Period {
    id: number;
    code: string;
    name: string;
    academic_year: string;
    semester: string;
    semester_label: string;
    starts_on: string;
    ends_on: string;
    is_active: boolean;
    classes_count: number;
}

interface Props {
    periods: Paginated<Period>;
    filters: { search?: string; sort?: string };
    semesters: Option[];
    canManage: boolean;
}

export default function AcademicPeriods({ periods, filters, semesters, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({ search: filters.search ?? '', sort: filters.sort ?? '' });
    const [editing, setEditing] = useState<Period | 'new' | null>(null);

    const columns: Column<Period>[] = [
        { key: 'code', header: 'Kode', sortable: true, className: 'w-24', cell: (row) => <span className="font-mono text-xs font-semibold">{row.code}</span> },
        {
            key: 'name',
            header: 'Periode',
            sortable: true,
            cell: (row) => (
                <div className="flex items-center gap-2">
                    <span className="font-semibold">{row.name}</span>
                    {row.is_active && <StatusBadge tone="gold">Aktif</StatusBadge>}
                </div>
            ),
        },
        { key: 'semester', header: 'Semester', cell: (row) => row.semester_label },
        {
            key: 'starts_on',
            header: 'Rentang waktu',
            sortable: true,
            cell: (row) => (
                <span className="text-muted-foreground tabular">
                    {formatDate(row.starts_on)} – {formatDate(row.ends_on)}
                </span>
            ),
        },
        { key: 'classes', header: 'Kelas', cell: (row) => <span className="tabular">{row.classes_count}</span> },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) =>
                canManage && (
                    <RowActions>
                        {!row.is_active && (
                            <ConfirmDialog
                                trigger={
                                    <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
                                        <CalendarCheck2 /> Jadikan periode aktif
                                    </DropdownMenuItem>
                                }
                                title={`Aktifkan ${row.name}?`}
                                description="Periode aktif menjadi acuan default untuk kelas, Monev, dan dashboard."
                                href={route('academic-periods.activate', row.id)}
                                method="post"
                                destructive={false}
                                confirmLabel="Aktifkan"
                            />
                        )}
                        <DropdownMenuItem onSelect={() => setEditing(row)}>
                            <Pencil /> Ubah
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <ConfirmDialog
                            trigger={
                                <DropdownMenuItem variant="destructive" onSelect={(e) => e.preventDefault()}>
                                    <Trash2 /> Hapus
                                </DropdownMenuItem>
                            }
                            title={`Hapus ${row.name}?`}
                            description="Periode yang sudah memiliki kelas atau Monev tidak dapat dihapus (data historis wajib dipertahankan)."
                            href={route('academic-periods.destroy', row.id)}
                            confirmLabel="Hapus"
                        />
                    </RowActions>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Periode Akademik"
                description="Semester berjalan dan riwayatnya. Data historis tetap tersimpan untuk analisis tren."
                breadcrumbs={[{ label: 'Master Akademik' }, { label: 'Periode Akademik' }]}
                actions={
                    canManage && (
                        <Button onClick={() => setEditing('new')}>
                            <Plus /> Tambah periode
                        </Button>
                    )
                }
            />
            <DataTable
                columns={columns}
                data={periods.data}
                pagination={periods}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={<TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari periode…" />}
            />
            {editing !== null && <PeriodForm period={editing === 'new' ? null : editing} semesters={semesters} onClose={() => setEditing(null)} />}
        </>
    );
}

function PeriodForm({ period, semesters, onClose }: { period: Period | null; semesters: Option[]; onClose: () => void }) {
    const form = useForm({
        code: period?.code ?? '',
        name: period?.name ?? '',
        academic_year: period?.academic_year ?? '',
        semester: period?.semester ?? 'ganjil',
        starts_on: period?.starts_on ?? '',
        ends_on: period?.ends_on ?? '',
    });

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: onClose };
        if (period) form.put(route('academic-periods.update', period.id), options);
        else form.post(route('academic-periods.store'), options);
    };

    const suggestName = (semester: string, year: string) => {
        const label = semesters.find((s) => s.value === semester)?.label;
        if (label && /^\d{4}\/\d{4}$/.test(year) && !period) form.setData((d) => ({ ...d, semester, academic_year: year, name: `${label} ${year}` }));
        else form.setData((d) => ({ ...d, semester, academic_year: year }));
    };

    return (
        <FormDialog open onOpenChange={(open) => !open && onClose()} title={period ? 'Ubah periode' : 'Tambah periode akademik'} onSubmit={submit} processing={form.processing}>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Tahun akademik" error={form.errors.academic_year} required hint="Format: 2026/2027">
                    <Input value={form.data.academic_year} onChange={(e) => suggestName(form.data.semester, e.target.value)} placeholder="2026/2027" />
                </FormField>
                <FormField label="Semester" error={form.errors.semester} required>
                    <SelectField value={form.data.semester} onChange={(v) => suggestName(v, form.data.academic_year)} options={semesters} />
                </FormField>
                <FormField label="Kode" error={form.errors.code} required hint="Mis. 20261 (tahun + 1 ganjil / 2 genap)">
                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                </FormField>
                <FormField label="Nama periode" error={form.errors.name} required>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </FormField>
                <FormField label="Tanggal mulai" error={form.errors.starts_on} required>
                    <Input type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
                </FormField>
                <FormField label="Tanggal selesai" error={form.errors.ends_on} required>
                    <Input type="date" value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
