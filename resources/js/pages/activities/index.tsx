import { Link } from '@inertiajs/react';
import { AlarmClock, CalendarCheck2, CalendarDays, CalendarRange, CircleDot, Download, FolderArchive, ListChecks, MapPin, Paperclip, Plus, UserRound } from 'lucide-react';
import { useState } from 'react';
import { ActivityForm } from '@/components/activities/activity-form';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { type ActivityFormOptions, activityStatusTone, dateRange, type LpmActivity } from '@/lib/activities';
import { formatDate } from '@/lib/format';
import { cleanQuery, cn } from '@/lib/utils';
import type { Option } from '@/types';

interface Props extends ActivityFormOptions {
    activities: LpmActivity[];
    filters: Record<string, string | undefined>;
    years: Option[];
    stats: { total: number; done: number; ongoing: number; planned: number; overdue: number; cancelled: number };
    canManage: boolean;
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

export default function ActivitiesIndex({ activities, filters, years, stats, canManage, ...options }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        year: filters.year ?? String(new Date().getFullYear()),
        search: filters.search ?? '',
        status: filters.status ?? 'all',
        category: filters.category ?? 'all',
    });
    const [editing, setEditing] = useState<LpmActivity | 'new' | null>(null);
    const today = new Date().toISOString().slice(0, 10);
    const percent = stats.total - stats.cancelled > 0 ? Math.round((stats.done / (stats.total - stats.cancelled)) * 100) : 0;

    // Kelompokkan per bulan mulai agar terbaca sebagai timeline.
    const byMonth = MONTHS.map((label, index) => ({
        index,
        label,
        items: activities.filter((activity) => Number(activity.start_date.slice(5, 7)) === index + 1),
    }));
    const currentMonth = query.year === today.slice(0, 4) ? Number(today.slice(5, 7)) - 1 : -1;

    const statusChips: { value: string; label: string; count?: number }[] = [
        { value: 'all', label: 'Semua', count: stats.total },
        { value: 'planned', label: 'Direncanakan', count: stats.planned },
        { value: 'ongoing', label: 'Berlangsung', count: stats.ongoing },
        { value: 'done', label: 'Terlaksana', count: stats.done },
        { value: 'overdue', label: 'Terlambat', count: stats.overdue },
        { value: 'postponed', label: 'Ditunda' },
        { value: 'cancelled', label: 'Dibatalkan' },
    ];

    return (
        <>
            <PageHeader
                reset={['lpm-activities']}
                title="Timeline Kegiatan LPM"
                description="Program kerja LPM di luar Monev dan AMI: rapat, pelatihan, sosialisasi, penyusunan dokumen, pendampingan akreditasi, dan lainnya — lengkap dengan bukti pelaksanaannya."
                breadcrumbs={[{ label: 'Program Kerja LPM' }, { label: 'Timeline Kegiatan' }]}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link href={route('activity-evidence.index', { year: query.year })}>
                                <FolderArchive /> Bukti program kerja
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={route('activities.export', cleanQuery(query))}>
                                <Download /> Ekspor Excel
                            </a>
                        </Button>
                        {canManage && (
                            <Button onClick={() => setEditing('new')}>
                                <Plus /> Tambah kegiatan
                            </Button>
                        )}
                    </>
                }
            />

            <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile label={`Kegiatan ${query.year}`} value={stats.total} icon={CalendarDays} hint={stats.cancelled ? `${stats.cancelled} ditunda/dibatalkan` : 'Seluruh program kerja'} />
                <StatTile label="Terlaksana" value={`${percent}%`} icon={CalendarCheck2} tone="info" hint={`${stats.done} kegiatan selesai`} />
                <StatTile label="Berlangsung" value={stats.ongoing} icon={CircleDot} tone="gold" hint={`${stats.planned} lainnya direncanakan`} />
                <StatTile label="Terlambat" value={stats.overdue} icon={AlarmClock} tone={stats.overdue ? 'danger' : 'neutral'} hint="Lewat tanggal selesai, belum terlaksana" />
            </div>

            <Card className="mb-5 gap-0 p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <SelectField value={query.year} onChange={(v) => setFilter('year', v)} options={years} label="Tahun program kerja" className="w-28" />
                    <SelectField value={query.category} onChange={(v) => setFilter('category', v)} options={options.categories} allLabel="Semua jenis" className="w-56" />
                    <SearchInput value={query.search} onChange={(v) => setFilter('search', v)} placeholder="Cari kegiatan, PJ, tempat…" className="min-w-56 flex-1" />
                </div>
                <div className="mt-3 flex flex-wrap gap-2" role="tablist" aria-label="Status kegiatan">
                    {statusChips.map((chip) => (
                        <button
                            key={chip.value}
                            type="button"
                            role="tab"
                            aria-selected={query.status === chip.value}
                            onClick={() => setFilter('status', chip.value)}
                            className={cn(
                                'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[13px] font-semibold transition',
                                query.status === chip.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-card hover:border-primary/40',
                                chip.value === 'overdue' && query.status !== chip.value && (chip.count ?? 0) > 0 && 'border-destructive/40 text-danger-foreground',
                            )}
                        >
                            {chip.label}
                            {chip.count !== undefined && (
                                <span className={cn('rounded-full px-1.5 text-xs tabular', query.status === chip.value ? 'bg-white/20' : 'bg-muted text-muted-foreground')}>{chip.count}</span>
                            )}
                        </button>
                    ))}
                </div>

                {/* Pita 12 bulan: kepadatan kegiatan per bulan, klik untuk melompat. */}
                <div className="mt-4 grid grid-cols-6 gap-1.5 sm:grid-cols-12">
                    {byMonth.map((month) => (
                        <a
                            key={month.label}
                            href={month.items.length ? `#bulan-${month.index + 1}` : undefined}
                            className={cn(
                                'flex flex-col items-center rounded-lg border px-1 py-1.5 text-center transition',
                                month.items.length ? 'bg-secondary/60 hover:border-primary/40' : 'border-dashed text-muted-foreground',
                                month.index === currentMonth && 'ring-2 ring-gold/60',
                            )}
                        >
                            <span className="text-[11px] font-semibold">{month.label}</span>
                            <span className={cn('text-sm font-extrabold tabular', month.items.length ? 'text-primary' : 'opacity-40')}>{month.items.length}</span>
                        </a>
                    ))}
                </div>
            </Card>

            {activities.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={CalendarRange}
                        title={stats.total === 0 ? `Belum ada kegiatan tahun ${query.year}` : 'Tidak ada kegiatan yang cocok'}
                        description={stats.total === 0 ? 'Susun program kerja LPM setahun di sini agar pelaksanaan dan buktinya mudah dipantau.' : 'Ubah filter status, jenis, atau kata kunci.'}
                        action={
                            canManage && stats.total === 0 ? (
                                <Button onClick={() => setEditing('new')}>
                                    <Plus /> Tambah kegiatan pertama
                                </Button>
                            ) : undefined
                        }
                    />
                </Card>
            ) : (
                <div className="flex flex-col gap-6">
                    {byMonth
                        .filter((month) => month.items.length > 0)
                        .map((month) => (
                            <section key={month.label} id={`bulan-${month.index + 1}`} className="scroll-mt-24">
                                <h2 className="mb-3 flex items-center gap-2 text-sm font-bold text-muted-foreground">
                                    <span className={cn('size-2.5 rounded-full', month.index === currentMonth ? 'bg-gold' : 'bg-primary/60')} />
                                    {formatDate(`${query.year}-${String(month.index + 1).padStart(2, '0')}-01`, 'MMMM yyyy')}
                                    <span className="font-medium">· {month.items.length} kegiatan</span>
                                </h2>
                                <ol className="relative ml-1 flex flex-col gap-3 border-l-2 border-primary/15 pl-5">
                                    {month.items.map((activity) => (
                                        <TimelineItem key={activity.id} activity={activity} today={today} />
                                    ))}
                                </ol>
                            </section>
                        ))}
                </div>
            )}

            {editing !== null && <ActivityForm {...options} activity={editing === 'new' ? null : editing} defaultYear={query.year} onClose={() => setEditing(null)} />}
        </>
    );
}

function TimelineItem({ activity, today }: { activity: LpmActivity; today: string }) {
    const happeningNow = activity.start_date <= today && activity.end_date >= today && activity.status !== 'cancelled';

    return (
        <li className="relative">
            <span
                className={cn(
                    'absolute top-5 -left-[27px] size-3 rounded-full ring-4 ring-background',
                    activity.status === 'done' ? 'bg-success' : activity.is_overdue ? 'bg-destructive' : happeningNow ? 'bg-gold' : 'bg-primary/40',
                )}
                aria-hidden="true"
            />
            <Link
                href={activity.show_url}
                className={cn(
                    'flex flex-col gap-3 rounded-2xl border bg-card p-4 transition hover:border-primary/40 hover:shadow-card sm:flex-row sm:items-start',
                    activity.is_overdue && 'border-destructive/35',
                    (activity.status === 'cancelled' || activity.status === 'postponed') && 'opacity-75',
                )}
            >
                <div className="w-full shrink-0 text-xs font-bold text-primary sm:w-36">
                    <div className="tabular">{dateRange(activity.start_date, activity.end_date)}</div>
                    <div className="mt-0.5 font-medium text-muted-foreground">{activity.code}</div>
                </div>
                <div className="min-w-0 flex-1">
                    <div className="font-semibold leading-snug">{activity.title}</div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1">
                            <ListChecks className="size-3.5" /> {activity.category_label}
                        </span>
                        {activity.pic && (
                            <span className="inline-flex items-center gap-1">
                                <UserRound className="size-3.5" /> {activity.pic}
                            </span>
                        )}
                        {activity.location && (
                            <span className="inline-flex items-center gap-1">
                                <MapPin className="size-3.5" /> {activity.location}
                            </span>
                        )}
                        <span className={cn('inline-flex items-center gap-1', activity.evidence_count === 0 && activity.status === 'done' && 'font-semibold text-gold-foreground')}>
                            <Paperclip className="size-3.5" /> {activity.evidence_count ? `${activity.evidence_count} bukti` : 'Belum ada bukti'}
                        </span>
                    </div>
                </div>
                <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                    {happeningNow && activity.status !== 'done' && (
                        <StatusBadge tone="gold" dot={false}>
                            Hari ini
                        </StatusBadge>
                    )}
                    {activity.is_overdue && <StatusBadge tone="danger">Terlambat</StatusBadge>}
                    <StatusBadge tone={activityStatusTone[activity.status]}>{activity.status_label}</StatusBadge>
                </div>
            </Link>
        </li>
    );
}
