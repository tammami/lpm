import { Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, CircleDot, Hourglass, Plus, Sparkles, AlarmClock } from 'lucide-react';
import { useState } from 'react';
import { Combobox } from '@/components/combobox';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDate } from '@/lib/format';
import { originTone, priorityTone, recommendationTone } from '@/lib/improvement';
import type { Option, Paginated } from '@/types';

export interface RecommendationRow {
    id: number;
    code: string;
    title: string;
    origin: string;
    origin_label: string;
    target_name: string | null;
    indicator: string | null;
    priority: string;
    priority_label: string;
    status: string;
    status_label: string;
    pic: string | null;
    pic_user_id: number | null;
    due_date: string | null;
    overdue: boolean;
    plans_count: number | null;
    verified_plans_count: number | null;
    progress: number | null;
    created_at: string;
}

interface Props {
    recommendations: Paginated<RecommendationRow>;
    filters: Record<string, string | boolean | undefined>;
    statuses: Option[];
    priorities: Option[];
    origins: Option[];
    stats: { open: number; in_progress: number; verified: number; overdue_plans: number };
    targets: Option[];
    picOptions: Option[];
    priorityOptions: Option[];
    canManage: boolean;
}

export default function RecommendationsIndex({ recommendations, filters, statuses, priorities, origins, stats, targets, picOptions, priorityOptions, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: String(filters.search ?? ''),
        sort: String(filters.sort ?? ''),
        status: String(filters.status ?? 'all'),
        priority: String(filters.priority ?? 'all'),
        origin: String(filters.origin ?? 'all'),
        mine: filters.mine ? '1' : '',
    });
    const [creating, setCreating] = useState(false);

    const columns: Column<RecommendationRow>[] = [
        {
            key: 'code',
            header: 'Rekomendasi',
            sortable: true,
            cell: (row) => (
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        <StatusBadge tone={priorityTone[row.priority]} dot={false}>
                            {row.priority_label}
                        </StatusBadge>
                        <span className="truncate font-semibold">{row.title}</span>
                    </div>
                    <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                        <span className="font-mono">{row.code}</span>
                        <span>·</span>
                        <span>{row.target_name ?? '—'}</span>
                        {row.indicator && <span>· {row.indicator}</span>}
                        <StatusBadge tone={originTone[row.origin]} dot={false} className="h-5 text-[11px]">
                            {row.origin_label}
                        </StatusBadge>
                    </div>
                </div>
            ),
        },
        { key: 'status', header: 'Status', cell: (row) => <StatusBadge tone={recommendationTone[row.status]}>{row.status_label}</StatusBadge> },
        {
            key: 'progress',
            header: 'Rencana aksi',
            cell: (row) => (
                <div className="w-32">
                    <div className="mb-1 flex justify-between text-[11px] text-muted-foreground">
                        <span>{row.plans_count ?? 0} rencana</span>
                        <span className="tabular">{row.progress ?? 0}%</span>
                    </div>
                    <Meter value={row.progress ?? 0} severity="good" size="sm" />
                </div>
            ),
        },
        {
            key: 'due_date',
            header: 'PIC & tenggat',
            sortable: true,
            cell: (row) => (
                <div className="text-xs">
                    <div className="max-w-44 truncate font-medium">{row.pic ?? '—'}</div>
                    <div className={row.overdue ? 'font-semibold text-destructive' : 'text-muted-foreground'}>{formatDate(row.due_date)}</div>
                </div>
            ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Rekomendasi Peningkatan"
                description="Dari data menjadi tindakan: setiap rekomendasi punya PIC, tenggat, rencana aksi, bukti, dan verifikasi hingga ditutup."
                breadcrumbs={[{ label: 'Peningkatan Mutu' }, { label: 'Rekomendasi' }]}
                actions={
                    canManage && (
                        <>
                            <Button variant="outline" asChild>
                                <Link href={route('improvement.recommendations.generate')}>
                                    <Sparkles /> Usulan otomatis
                                </Link>
                            </Button>
                            <Button onClick={() => setCreating(true)}>
                                <Plus /> Rekomendasi manual
                            </Button>
                        </>
                    )
                }
            />
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Belum ditindaklanjuti" value={stats.open} icon={CircleDot} tone="danger" />
                <StatTile label="Dalam pelaksanaan" value={stats.in_progress} icon={Hourglass} tone="info" />
                <StatTile label="Terverifikasi / selesai" value={stats.verified} icon={CheckCircle2} />
                <StatTile label="Rencana aksi lewat tenggat" value={stats.overdue_plans} icon={AlarmClock} tone={stats.overdue_plans ? 'danger' : 'neutral'} />
            </div>
            <DataTable
                columns={columns}
                data={recommendations.data}
                pagination={recommendations}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                onRowClick={(row) => router.visit(route('improvement.recommendations.show', row.id))}
                toolbar={
                    <TableToolbar
                        search={query.search}
                        onSearch={(v) => setFilter('search', v)}
                        placeholder="Cari rekomendasi…"
                        end={
                            <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Switch checked={query.mine === '1'} onCheckedChange={(v) => setFilter('mine', v ? '1' : '')} /> Tugas saya
                            </label>
                        }
                    >
                        <SelectField value={query.status} onChange={(v) => setFilter('status', v)} options={statuses} allLabel="Semua status" />
                        <SelectField value={query.priority} onChange={(v) => setFilter('priority', v)} options={priorities} allLabel="Semua prioritas" />
                        <SelectField value={query.origin} onChange={(v) => setFilter('origin', v)} options={origins} allLabel="Semua sumber" />
                    </TableToolbar>
                }
                emptyTitle="Belum ada rekomendasi"
                emptyDescription="Gunakan “Usulan otomatis” untuk mengubah hasil Monev, temuan AMI, dan tenggat akreditasi menjadi rekomendasi."
            />
            {creating && <RecommendationForm targets={targets} picOptions={picOptions} priorityOptions={priorityOptions} onClose={() => setCreating(false)} />}
        </>
    );
}

export function RecommendationForm({
    recommendation,
    targets,
    picOptions,
    priorityOptions,
    onClose,
}: {
    recommendation?: { id: number; title: string; description: string; rationale: string | null; target: string | null; indicator: string | null; priority: string; pic_user_id: number | null; due_date: string | null };
    targets: Option[];
    picOptions: Option[];
    priorityOptions: Option[];
    onClose: () => void;
}) {
    const form = useForm({
        title: recommendation?.title ?? '',
        description: recommendation?.description ?? '',
        rationale: recommendation?.rationale ?? '',
        target: recommendation?.target ?? '',
        indicator: recommendation?.indicator ?? '',
        priority: recommendation?.priority ?? 'medium',
        pic_user_id: recommendation?.pic_user_id ? String(recommendation.pic_user_id) : '',
        due_date: recommendation?.due_date ?? '',
    });
    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: onClose };
        if (recommendation) form.put(route('improvement.recommendations.update', recommendation.id), opts);
        else form.post(route('improvement.recommendations.store'), opts);
    };

    return (
        <FormDialog open onOpenChange={(o) => !o && onClose()} title={recommendation ? 'Ubah rekomendasi' : 'Rekomendasi baru'} onSubmit={submit} processing={form.processing} size="lg">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Judul" error={form.errors.title} required className="sm:col-span-2">
                    <Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} />
                </FormField>
                <FormField label="Uraian rekomendasi" error={form.errors.description} required className="sm:col-span-2">
                    <Textarea rows={4} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                </FormField>
                <FormField label="Dasar / alasan" error={form.errors.rationale} className="sm:col-span-2">
                    <Input value={form.data.rationale} onChange={(e) => form.setData('rationale', e.target.value)} placeholder="Mis. skor indikator C3 = 2,61 < 3,00" />
                </FormField>
                <FormField label="Sasaran (unit)" error={form.errors.target}>
                    <Combobox value={form.data.target} onChange={(v) => form.setData('target', v)} options={targets} placeholder="Prodi / fakultas / unit" />
                </FormField>
                <FormField label="Indikator terkait" error={form.errors.indicator}>
                    <Input value={form.data.indicator} onChange={(e) => form.setData('indicator', e.target.value)} />
                </FormField>
                <FormField label="Prioritas" error={form.errors.priority} required>
                    <SelectField value={form.data.priority} onChange={(v) => form.setData('priority', v)} options={priorityOptions} />
                </FormField>
                <FormField label="PIC" error={form.errors.pic_user_id}>
                    <Combobox value={form.data.pic_user_id} onChange={(v) => form.setData('pic_user_id', v)} options={picOptions} placeholder="Pilih PIC" />
                </FormField>
                <FormField label="Tenggat" error={form.errors.due_date}>
                    <Input type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                </FormField>
            </div>
        </FormDialog>
    );
}
