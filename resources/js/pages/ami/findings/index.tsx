import { router } from '@inertiajs/react';
import { AlarmClock, CheckCircle2, Hourglass, ShieldAlert } from 'lucide-react';
import type { FindingRow } from '@/components/ami/types';
import { type Column, DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Switch } from '@/components/ui/switch';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { findingTone, severityTone } from '@/lib/ami';
import { formatDate } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Props {
    findings: Paginated<FindingRow>;
    filters: Record<string, string | boolean | undefined>;
    statuses: Option[];
    severities: Option[];
    standards: Option[];
    stats: { total: number; unresolved: number; overdue: number; awaiting: number; closed: number };
}

export default function FindingsIndex({ findings, filters, statuses, severities, standards, stats }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: String(filters.search ?? ''),
        sort: String(filters.sort ?? ''),
        status: String(filters.status ?? 'all'),
        finding_severity_id: String(filters.finding_severity_id ?? 'all'),
        quality_standard_id: String(filters.quality_standard_id ?? 'all'),
        overdue: filters.overdue ? '1' : '',
        mine: filters.mine ? '1' : '',
    });

    const columns: Column<FindingRow>[] = [
        {
            key: 'code',
            header: 'Temuan',
            sortable: true,
            cell: (row) => (
                <div className="flex items-start gap-3">
                    <StatusBadge tone={severityTone[row.severity_color] ?? 'neutral'} dot={false} className="mt-0.5">
                        {row.severity_code}
                    </StatusBadge>
                    <div className="min-w-0">
                        <div className="font-semibold">{row.title}</div>
                        <div className="text-xs text-muted-foreground">
                            <span className="font-mono">{row.code}</span> · {row.standard ?? 'Tanpa standar'}
                        </div>
                    </div>
                </div>
            ),
        },
        { key: 'auditee', header: 'Auditee', cell: (row) => <span className="text-xs">{row.auditee_name}</span> },
        { key: 'pic', header: 'PIC', cell: (row) => <span className="text-xs">{row.pic ?? '—'}</span> },
        {
            key: 'due_date',
            header: 'Tenggat',
            sortable: true,
            cell: (row) => (
                <span className={row.overdue ? 'inline-flex items-center gap-1 text-xs font-semibold text-destructive' : 'text-xs text-muted-foreground'}>
                    {row.overdue && <AlarmClock className="size-3.5" />}
                    {formatDate(row.due_date)}
                </span>
            ),
        },
        { key: 'status', header: 'Status', cell: (row) => <StatusBadge tone={findingTone[row.status]}>{row.status_label}</StatusBadge> },
    ];

    return (
        <>
            <PageHeader title="Temuan & Tindak Lanjut" description="Temuan tidak berhenti di laporan: setiap temuan dilacak sampai tindakan koreksinya diverifikasi dan ditutup." breadcrumbs={[{ label: 'Audit Mutu Internal' }, { label: 'Temuan' }]} />
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Belum tuntas" value={stats.unresolved} icon={ShieldAlert} tone="danger" hint={`dari ${stats.total} temuan`} />
                <StatTile label="Melewati tenggat" value={stats.overdue} icon={AlarmClock} tone={stats.overdue ? 'danger' : 'neutral'} />
                <StatTile label="Menunggu verifikasi" value={stats.awaiting} icon={Hourglass} tone="info" />
                <StatTile label="Ditutup" value={stats.closed} icon={CheckCircle2} />
            </div>
            <DataTable
                columns={columns}
                data={findings.data}
                pagination={findings}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                onRowClick={(row) => router.visit(route('ami.findings.show', row.id))}
                toolbar={
                    <TableToolbar
                        search={query.search}
                        onSearch={(v) => setFilter('search', v)}
                        placeholder="Cari kode, judul, auditee…"
                        end={
                            <>
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Switch checked={query.overdue === '1'} onCheckedChange={(v) => setFilter('overdue', v ? '1' : '')} /> Lewat tenggat
                                </label>
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Switch checked={query.mine === '1'} onCheckedChange={(v) => setFilter('mine', v ? '1' : '')} /> Tugas saya
                                </label>
                            </>
                        }
                    >
                        <SelectField value={query.status} onChange={(v) => setFilter('status', v)} options={statuses} allLabel="Semua status" />
                        <SelectField value={query.finding_severity_id} onChange={(v) => setFilter('finding_severity_id', v)} options={severities} allLabel="Semua kategori" />
                        <SelectField value={query.quality_standard_id} onChange={(v) => setFilter('quality_standard_id', v)} options={standards} allLabel="Semua standar" />
                    </TableToolbar>
                }
                emptyTitle="Tidak ada temuan"
            />
        </>
    );
}
