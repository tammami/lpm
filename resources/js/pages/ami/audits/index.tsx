import { router } from '@inertiajs/react';
import { Users } from 'lucide-react';
import type { AuditSummary } from '@/components/ami/types';
import { type Column, DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Switch } from '@/components/ui/switch';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { auditTone } from '@/lib/ami';
import { formatDate } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Props {
    audits: Paginated<AuditSummary & { program?: string }>;
    filters: Record<string, string | boolean | undefined>;
    statuses: Option[];
    programs: Option[];
    isAuditor: boolean;
}

export default function AuditsIndex({ audits, filters, statuses, programs, isAuditor }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: String(filters.search ?? ''),
        sort: String(filters.sort ?? ''),
        status: String(filters.status ?? 'all'),
        audit_program_id: String(filters.audit_program_id ?? 'all'),
        mine: filters.mine ? '1' : '',
    });

    const columns: Column<AuditSummary>[] = [
        { key: 'scheduled_on', header: 'Jadwal', sortable: true, cell: (row) => <span className="text-xs font-semibold tabular">{formatDate(row.scheduled_on)}</span> },
        {
            key: 'auditee_name',
            header: 'Auditee',
            sortable: true,
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.auditee_name}</div>
                    <div className="text-xs text-muted-foreground">
                        <span className="font-mono">{row.code}</span> · {row.auditee_type_label}
                    </div>
                </div>
            ),
        },
        {
            key: 'team',
            header: 'Tim auditor',
            cell: (row) => (
                <span className="inline-flex items-center gap-1.5 text-xs">
                    <Users className="size-3.5 text-muted-foreground" /> {row.lead_auditor ?? '—'}
                    {row.auditors.length > 1 && <span className="text-muted-foreground">+{row.auditors.length - 1}</span>}
                </span>
            ),
        },
        { key: 'status', header: 'Tahap', cell: (row) => <StatusBadge tone={auditTone[row.status]}>{row.status_label}</StatusBadge> },
        {
            key: 'findings',
            header: 'Temuan',
            cell: (row) => (
                <span className="text-xs">
                    <b>{row.findings_count}</b> {row.open_findings_count > 0 && <span className="text-destructive">({row.open_findings_count} terbuka)</span>}
                </span>
            ),
        },
    ];

    return (
        <>
            <PageHeader title="Jadwal Audit" description="Seluruh audit yang dapat Anda akses. Auditor mengisi daftar tilik dan mencatat temuan dari halaman detail audit." breadcrumbs={[{ label: 'Audit Mutu Internal' }, { label: 'Jadwal Audit' }]} />
            <DataTable
                columns={columns}
                data={audits.data}
                pagination={audits}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                onRowClick={(row) => router.visit(route('ami.audits.show', row.id))}
                toolbar={
                    <TableToolbar
                        search={query.search}
                        onSearch={(v) => setFilter('search', v)}
                        placeholder="Cari kode atau auditee…"
                        end={
                            isAuditor && (
                                <label className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Switch checked={query.mine === '1'} onCheckedChange={(v) => setFilter('mine', v ? '1' : '')} /> Tugas saya
                                </label>
                            )
                        }
                    >
                        <SelectField value={query.status} onChange={(v) => setFilter('status', v)} options={statuses} allLabel="Semua tahap" />
                        <SelectField value={query.audit_program_id} onChange={(v) => setFilter('audit_program_id', v)} options={programs} allLabel="Semua program" />
                    </TableToolbar>
                }
                emptyTitle="Tidak ada audit"
            />
        </>
    );
}
