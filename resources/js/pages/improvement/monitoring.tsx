import { Link } from '@inertiajs/react';
import { AlarmClock, CheckCircle2, Gauge, ListChecks } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { PaginationBar } from '@/components/pagination-bar';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Card } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDate, formatPercent } from '@/lib/format';
import { planTone, priorityTone } from '@/lib/improvement';
import { cn } from '@/lib/utils';
import type { Option, Paginated } from '@/types';

interface PlanRow {
    id: number;
    title: string;
    progress: number;
    tasks_count: number;
    done_tasks_count: number;
    status: string;
    status_label: string;
    pic: string | null;
    due_date: string;
    overdue: boolean;
    recommendation: { id: number; code: string; title: string; target_name: string | null; priority: string; priority_label: string };
}

interface Props {
    plans: Paginated<PlanRow>;
    filters: { status: string; overdue: boolean; mine: boolean };
    statuses: Option[];
    summary: { total: number; overdue: number; completed: number; avg_progress: number };
}

export default function Monitoring({ plans, filters, statuses, summary }: Props) {
    const { filters: query, setFilter } = useQueryFilters({ status: filters.status, overdue: filters.overdue ? '1' : '', mine: filters.mine ? '1' : '' });

    return (
        <>
            <PageHeader title="Monitoring Rencana Aksi" description="Pantau pelaksanaan seluruh rencana aksi peningkatan mutu: progres, PIC, dan tenggat." breadcrumbs={[{ label: 'Peningkatan Mutu' }, { label: 'Monitoring' }]} />
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Rencana aksi" value={summary.total} icon={ListChecks} tone="neutral" />
                <StatTile label="Rata-rata progres" value={formatPercent(summary.avg_progress, 0)} icon={Gauge} tone="info">
                    <Meter value={summary.avg_progress} severity="good" />
                </StatTile>
                <StatTile label="Selesai / terverifikasi" value={summary.completed} icon={CheckCircle2} />
                <StatTile label="Lewat tenggat" value={summary.overdue} icon={AlarmClock} tone={summary.overdue ? 'danger' : 'neutral'} />
            </div>
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <SelectField value={query.status} onChange={(v) => setFilter('status', v)} options={statuses} allLabel="Semua status" className="w-52" />
                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Switch checked={query.overdue === '1'} onCheckedChange={(v) => setFilter('overdue', v ? '1' : '')} /> Lewat tenggat
                </label>
                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                    <Switch checked={query.mine === '1'} onCheckedChange={(v) => setFilter('mine', v ? '1' : '')} /> Tugas saya
                </label>
            </div>
            {plans.data.length === 0 ? (
                <Card>
                    <EmptyState title="Tidak ada rencana aksi" />
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    {plans.data.map((plan) => (
                        <Link
                            key={plan.id}
                            href={route('improvement.recommendations.show', plan.recommendation.id)}
                            className={cn('flex flex-col gap-3 rounded-2xl border bg-card p-4 transition hover:border-primary/30 hover:shadow-sm', plan.overdue && 'border-destructive/40')}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="truncate font-bold">{plan.title}</div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {plan.recommendation.code} · {plan.recommendation.target_name ?? '—'}
                                    </div>
                                </div>
                                <StatusBadge tone={planTone[plan.status]}>{plan.status_label}</StatusBadge>
                            </div>
                            <div className="flex items-center gap-3">
                                <Meter value={plan.progress} severity="good" className="flex-1" size="sm" />
                                <span className="text-xs font-bold tabular">{plan.progress}%</span>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span>PIC {plan.pic}</span>
                                <span className={cn(plan.overdue && 'font-semibold text-destructive')}>Tenggat {formatDate(plan.due_date)}</span>
                                {plan.tasks_count > 0 && (
                                    <span>
                                        {plan.done_tasks_count}/{plan.tasks_count} tugas
                                    </span>
                                )}
                                <StatusBadge tone={priorityTone[plan.recommendation.priority]} dot={false} className="ml-auto">
                                    {plan.recommendation.priority_label}
                                </StatusBadge>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
            {plans.last_page > 1 && (
                <div className="mt-4 rounded-2xl border bg-card">
                    <PaginationBar meta={plans} />
                </div>
            )}
        </>
    );
}
