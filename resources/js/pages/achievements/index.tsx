import { Download, Eye, Medal } from 'lucide-react';
import { useState } from 'react';
import { AchievementDetail } from '@/components/achievements/achievement-detail';
import { type Column, DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { RowActions } from '@/components/row-actions';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { type Achievement, type AchievementFormOptions, type AchievementStatus, achievementStatusTone } from '@/lib/achievements';
import { formatDate } from '@/lib/format';
import { cleanQuery, cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';

interface Props extends AchievementFormOptions {
    achievements: Paginated<Achievement>;
    filters: Record<string, string | undefined>;
    studyPrograms: Option[];
    years: Option[];
    statusCounts: Record<AchievementStatus, number>;
    levelCounts: { value: string; label: string; total: number }[];
    canVerify: boolean;
}

export default function AchievementsIndex({ achievements, filters, studyPrograms, years, statusCounts, levelCounts, canVerify, ...options }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        status: filters.status ?? 'any',
        category: filters.category ?? 'all',
        level: filters.level ?? 'all',
        study_program_id: filters.study_program_id ?? 'all',
        year: filters.year ?? 'all',
    });
    const [viewing, setViewing] = useState<Achievement | null>(null);
    const totalVerified = levelCounts.reduce((sum, level) => sum + level.total, 0);

    const columns: Column<Achievement>[] = [
        {
            key: 'title',
            header: 'Prestasi',
            sortable: true,
            className: 'min-w-72 max-w-xl whitespace-normal',
            cell: (row) => (
                <div>
                    <div className="line-clamp-2 font-semibold">{row.title}</div>
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        <span className="font-bold text-gold-foreground">{row.achievement}</span> · {row.organizer}
                    </div>
                </div>
            ),
        },
        {
            key: 'student',
            header: 'Mahasiswa',
            cell: (row) => (
                <div className="min-w-0">
                    <div className="truncate font-medium">{row.student?.name}</div>
                    <div className="truncate text-xs text-muted-foreground">
                        {row.student?.nim} · {row.student?.study_program}
                    </div>
                </div>
            ),
        },
        {
            key: 'level',
            header: 'Tingkat',
            cell: (row) => (
                <div>
                    <div className="text-sm">{row.level_label}</div>
                    <div className="text-xs text-muted-foreground">{row.category_label}</div>
                </div>
            ),
        },
        { key: 'achieved_on', header: 'Tanggal', sortable: true, cell: (row) => <span className="text-sm whitespace-nowrap tabular">{formatDate(row.achieved_on)}</span> },
        { key: 'status', header: 'Verifikasi', cell: (row) => <StatusBadge tone={achievementStatusTone[row.status]}>{row.status_label}</StatusBadge> },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <RowActions>
                    <DropdownMenuItem onSelect={() => setViewing(row)}>
                        <Eye /> {canVerify ? 'Lihat & verifikasi' : 'Lihat rincian'}
                    </DropdownMenuItem>
                </RowActions>
            ),
        },
    ];

    const statusChips = [
        { value: 'any', label: 'Semua', count: Object.values(statusCounts).reduce((sum, count) => sum + count, 0) },
        ...options.statuses.map((status) => ({ value: String(status.value), label: status.label, count: statusCounts[status.value as AchievementStatus] ?? 0 })),
    ];

    return (
        <>
            <PageHeader
                reset={['student-achievements']}
                title="Prestasi Mahasiswa"
                description="Prestasi akademik dan nonakademik yang dicatat mahasiswa sendiri. Bidang kemahasiswaan memverifikasi; hanya prestasi terverifikasi yang masuk ekspor format LKPS."
                breadcrumbs={[{ label: 'Kemahasiswaan' }, { label: 'Prestasi Mahasiswa' }]}
                actions={
                    <Button variant="outline" asChild>
                        <a href={route('achievements.export', cleanQuery({ ...query, status: query.status === 'any' ? 'verified' : query.status }))} title="Ekspor dua sheet: akademik & nonakademik">
                            <Download /> Ekspor Excel (LKPS)
                        </a>
                    </Button>
                }
            />

            <div className="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div className="rounded-2xl border border-white/75 surface-glass px-4 py-3">
                    <div className="flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                        <Medal className="size-3.5" /> Terverifikasi
                    </div>
                    <div className="mt-0.5 text-2xl font-extrabold tabular">{totalVerified}</div>
                </div>
                {levelCounts.map((level) => (
                    <button
                        key={level.value}
                        type="button"
                        onClick={() => setFilter('level', query.level === level.value ? 'all' : level.value)}
                        className={cn('rounded-2xl border border-white/75 surface-glass px-4 py-3 text-left transition hover:border-primary/40', query.level === level.value && 'ring-2 ring-primary/30')}
                    >
                        <div className="truncate text-xs font-semibold text-muted-foreground">{level.label}</div>
                        <div className="mt-0.5 text-2xl font-extrabold tabular">{level.total}</div>
                    </button>
                ))}
            </div>

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
                data={achievements.data}
                pagination={achievements}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                onRowClick={setViewing}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari prestasi, penyelenggara, nama, NIM…">
                        <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" />
                        <SelectField value={query.category} onChange={(v) => setFilter('category', v)} options={options.categories} allLabel="Semua jenis" />
                        <SelectField value={query.level} onChange={(v) => setFilter('level', v)} options={options.levels} allLabel="Semua tingkat" />
                        <SelectField value={query.year} onChange={(v) => setFilter('year', v)} options={years} allLabel="Semua tahun" />
                    </TableToolbar>
                }
                emptyTitle="Belum ada prestasi"
                emptyDescription="Mahasiswa mencatat prestasinya lewat menu Prestasi Saya di portal."
            />

            {viewing && <AchievementDetail key={viewing.id} achievement={viewing} canVerify={canVerify} onClose={() => setViewing(null)} />}
        </>
    );
}
