import { Link } from '@inertiajs/react';
import { BarChart3, Download, Eye, GraduationCap, Pencil, Plus, Trash2, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { type Column, DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { WorkDetail } from '@/components/research/work-detail';
import { WorkForm } from '@/components/research/work-form';
import { RowActions } from '@/components/row-actions';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem, DropdownMenuSeparator } from '@/components/ui/dropdown-menu';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { type ScientificWork, type WorkFormOptions, type WorkStatus, workStatusTone } from '@/lib/research';
import { cleanQuery, cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';

interface Props extends WorkFormOptions {
    works: Paginated<ScientificWork>;
    filters: Record<string, string | undefined>;
    studyPrograms: Option[];
    lecturers: Option[];
    years: Option[];
    statusCounts: Record<WorkStatus, number>;
    can: { manage: boolean; verify: boolean; analysis: boolean };
}

export default function ResearchWorks({ works, filters, studyPrograms, lecturers, years, statusCounts, can, ...options }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        study_program_id: filters.study_program_id ?? 'all',
        lecturer_id: filters.lecturer_id ?? 'all',
        category: filters.category ?? 'all',
        type: filters.type ?? 'all',
        indexation_family: filters.indexation_family ?? 'all',
        year: filters.year ?? 'all',
        status: filters.status ?? 'all',
        with_students: filters.with_students ?? 'all',
    });
    const [editing, setEditing] = useState<ScientificWork | 'new' | null>(null);
    const [viewing, setViewing] = useState<ScientificWork | null>(null);

    const columns: Column<ScientificWork>[] = [
        {
            key: 'title',
            header: 'Karya',
            sortable: true,
            className: 'min-w-72 max-w-xl whitespace-normal',
            cell: (row) => (
                <div>
                    <div className="line-clamp-2 font-semibold">{row.title}</div>
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                        {row.venue && <span className="max-w-72 truncate">{row.venue}</span>}
                        {row.students.length > 0 && (
                            <span className="inline-flex items-center gap-1 font-semibold text-gold-foreground" title={row.students.map((s) => s.name).join(', ')}>
                                <GraduationCap className="size-3.5" /> {row.students.length} mahasiswa
                            </span>
                        )}
                        {row.lecturers.length > 0 && (
                            <span className="inline-flex items-center gap-1 font-semibold text-primary" title={row.lecturers.map((l) => l.name).join(', ')}>
                                <UsersRound className="size-3.5" /> {row.lecturers.length} dosen rekan
                            </span>
                        )}
                    </div>
                </div>
            ),
        },
        { key: 'year', header: 'Tahun', sortable: true, cell: (row) => <span className="tabular">{row.year}</span> },
        {
            key: 'lecturer',
            header: 'Dosen',
            cell: (row) => (
                <div className="min-w-0">
                    <div className="truncate font-medium">{row.lecturer}</div>
                    <div className="truncate text-xs text-muted-foreground">{row.study_program}</div>
                </div>
            ),
        },
        {
            key: 'type',
            header: 'Subjenis',
            cell: (row) => (
                <div className="min-w-0">
                    <div className="max-w-56 truncate" title={`${row.category_label} · ${row.type_label}`}>
                        {row.type_label}
                    </div>
                    {row.indexation && row.indexation !== 'not_applicable' && <div className="text-xs font-semibold text-primary">{row.indexation_label}</div>}
                </div>
            ),
        },
        { key: 'status', header: 'Verifikasi', cell: (row) => <StatusBadge tone={workStatusTone[row.status]}>{row.status_label}</StatusBadge> },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <RowActions>
                    <DropdownMenuItem onSelect={() => setViewing(row)}>
                        <Eye /> {can.verify ? 'Lihat & verifikasi' : 'Lihat rincian'}
                    </DropdownMenuItem>
                    {can.manage && (
                        <>
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
                                title="Hapus karya ilmiah ini?"
                                description={`"${row.title}" milik ${row.lecturer} akan dihapus dari rekap.`}
                                href={route('research.works.destroy', row.id)}
                                confirmLabel="Hapus"
                            />
                        </>
                    )}
                </RowActions>
            ),
        },
    ];

    const statusChips = [
        { value: 'all', label: 'Semua', count: Object.values(statusCounts).reduce((sum, count) => sum + count, 0) },
        ...options.statuses.map((status) => ({ value: status.value, label: status.label, count: statusCounts[status.value as WorkStatus] ?? 0 })),
    ];

    return (
        <>
            <PageHeader
                reset={['scientific-works']}
                title="Karya Ilmiah Dosen"
                description="Artikel, buku, HKI, dan karya lain dosen sesuai template LPM — termasuk mahasiswa yang ikut menulis. Bukti berupa tautan, tanpa unggah berkas."
                breadcrumbs={[{ label: 'Karya Ilmiah' }, { label: 'Data Karya' }]}
                actions={
                    <>
                        {can.analysis && (
                            <Button variant="outline" asChild>
                                <Link href={route('research.analysis')}>
                                    <BarChart3 /> Analisis
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <a href={route('research.works.export', cleanQuery(query))}>
                                <Download /> Ekspor Excel
                            </a>
                        </Button>
                        {can.manage && (
                            <Button onClick={() => setEditing('new')}>
                                <Plus /> Tambah karya
                            </Button>
                        )}
                    </>
                }
            />

            <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label="Status verifikasi">
                {statusChips.map((chip) => (
                    <button
                        key={chip.value}
                        type="button"
                        role="tab"
                        aria-selected={query.status === chip.value}
                        onClick={() => setFilter('status', chip.value)}
                        className={cn(
                            'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-semibold transition',
                            query.status === chip.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:border-primary/40',
                        )}
                    >
                        {chip.label}
                        <span className={cn('rounded-full px-1.5 text-xs tabular', query.status === chip.value ? 'bg-white/20' : 'bg-muted text-muted-foreground')}>{chip.count}</span>
                    </button>
                ))}
            </div>

            <DataTable
                columns={columns}
                data={works.data}
                pagination={works}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                onRowClick={setViewing}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari judul, jurnal, DOI, dosen, mahasiswa…">
                        <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" />
                        <Combobox value={query.lecturer_id} onChange={(v) => setFilter('lecturer_id', v)} options={[{ value: 'all', label: 'Semua dosen' }, ...lecturers]} searchPlaceholder="Cari dosen…" className="h-9" />
                        <SelectField value={query.category} onChange={(v) => setFilter('category', v)} options={options.categories} allLabel="Semua jenis" />
                        <SelectField value={query.type} onChange={(v) => setFilter('type', v)} options={options.types} allLabel="Semua subjenis" />
                        <SelectField value={query.indexation_family} onChange={(v) => setFilter('indexation_family', v)} options={options.indexationFamilies} allLabel="Semua indeksasi" />
                        <SelectField value={query.year} onChange={(v) => setFilter('year', v)} options={years} allLabel="Semua tahun" />
                        <SelectField value={query.with_students} onChange={(v) => setFilter('with_students', v)} options={[{ value: '1', label: 'Melibatkan mahasiswa' }]} allLabel="Semua karya" />
                    </TableToolbar>
                }
                emptyTitle="Belum ada karya ilmiah"
                emptyDescription={can.manage ? 'Tambahkan karya atas nama dosen, atau minta dosen mengisi lewat menu Karya Ilmiah Saya.' : 'Dosen dapat mengisi lewat menu Karya Ilmiah Saya.'}
            />

            {editing !== null && (
                <WorkForm
                    {...options}
                    work={editing === 'new' ? null : editing}
                    lecturers={lecturers}
                    defaultLecturerId={query.lecturer_id !== 'all' ? query.lecturer_id : undefined}
                    storeUrl={route('research.works.store')}
                    updateUrl={(work) => route('research.works.update', work.id)}
                    onClose={() => setEditing(null)}
                />
            )}
            {viewing && <WorkDetail work={viewing} canVerify={can.verify} onClose={() => setViewing(null)} />}
        </>
    );
}
