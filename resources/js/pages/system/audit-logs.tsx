import { Eye } from 'lucide-react';
import { useState } from 'react';
import { type Column, DataTable } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatusBadge, type Tone } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDateTime } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Log {
    id: number;
    event: string;
    event_label: string;
    module: string | null;
    description: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    user: string | null;
    username: string | null;
    subject: string | null;
    created_at: string;
}

const tones: Record<string, Tone> = {
    login: 'info', logout: 'neutral', login_failed: 'danger', created: 'success', updated: 'primary', deleted: 'danger',
    published: 'gold', approved: 'gold', permission_changed: 'warning', exported: 'info', imported: 'info', reopened: 'warning',
};

export default function AuditLogs({ logs, filters, events, modules }: { logs: Paginated<Log>; filters: Record<string, string | undefined>; events: Option[]; modules: Option[] }) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        event: filters.event ?? 'all',
        module: filters.module ?? 'all',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });
    const [detail, setDetail] = useState<Log | null>(null);

    const columns: Column<Log>[] = [
        { key: 'time', header: 'Waktu', className: 'whitespace-nowrap', cell: (row) => <span className="text-xs text-muted-foreground tabular">{formatDateTime(row.created_at)}</span> },
        {
            key: 'user',
            header: 'Pengguna',
            cell: (row) => (
                <div>
                    <div className="font-medium">{row.user ?? 'Sistem / tamu'}</div>
                    <div className="text-[11px] text-muted-foreground">{row.ip_address}</div>
                </div>
            ),
        },
        { key: 'event', header: 'Aksi', cell: (row) => <StatusBadge tone={tones[row.event] ?? 'neutral'}>{row.event_label}</StatusBadge> },
        { key: 'module', header: 'Modul', cell: (row) => <span className="text-xs capitalize">{row.module ?? '—'}</span> },
        {
            key: 'description',
            header: 'Keterangan',
            cell: (row) => (
                <div className="max-w-md">
                    <div className="text-[13px]">{row.description ?? '—'}</div>
                    {row.subject && <div className="font-mono text-[11px] text-muted-foreground">{row.subject}</div>}
                </div>
            ),
        },
        {
            key: 'detail',
            header: '',
            className: 'w-12',
            cell: (row) =>
                (row.old_values || row.new_values) && (
                    <Button variant="ghost" size="icon-sm" onClick={() => setDetail(row)} aria-label="Detail perubahan">
                        <Eye />
                    </Button>
                ),
        },
    ];

    const keys = detail ? Array.from(new Set([...Object.keys(detail.old_values ?? {}), ...Object.keys(detail.new_values ?? {})])) : [];

    return (
        <>
            <PageHeader title="Log Audit" description="Jejak lengkap aktivitas: login, perubahan data (sebelum/sesudah), publikasi, impor/ekspor, dan perubahan hak akses." breadcrumbs={[{ label: 'Sistem' }, { label: 'Log Audit' }]} />
            <DataTable
                columns={columns}
                data={logs.data}
                pagination={logs}
                rowKey={(row) => row.id}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari keterangan atau pengguna…">
                        <SelectField value={query.event} onChange={(v) => setFilter('event', v)} options={events} allLabel="Semua aksi" />
                        <SelectField value={query.module} onChange={(v) => setFilter('module', v)} options={modules} allLabel="Semua modul" />
                        <Input type="date" value={query.from} onChange={(e) => setFilter('from', e.target.value)} className="h-9" aria-label="Dari tanggal" />
                        <Input type="date" value={query.to} onChange={(e) => setFilter('to', e.target.value)} className="h-9" aria-label="Sampai tanggal" />
                    </TableToolbar>
                }
                emptyTitle="Tidak ada aktivitas yang cocok"
            />
            <Dialog open={detail !== null} onOpenChange={(open) => !open && setDetail(null)}>
                <DialogContent className="sm:max-w-3xl">
                    <DialogHeader>
                        <DialogTitle>{detail?.description}</DialogTitle>
                        <DialogDescription>
                            {detail?.user} · {detail && formatDateTime(detail.created_at)} · {detail?.subject}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-auto rounded-xl border">
                        <table className="w-full text-xs">
                            <thead className="sticky top-0 bg-muted">
                                <tr>
                                    <th className="px-3 py-2 text-left">Atribut</th>
                                    <th className="px-3 py-2 text-left">Sebelum</th>
                                    <th className="px-3 py-2 text-left">Sesudah</th>
                                </tr>
                            </thead>
                            <tbody>
                                {keys.map((key) => (
                                    <tr key={key} className="border-t align-top">
                                        <td className="px-3 py-2 font-mono font-semibold">{key}</td>
                                        <td className="px-3 py-2 font-mono break-all text-destructive/80">{JSON.stringify(detail?.old_values?.[key] ?? null)}</td>
                                        <td className="px-3 py-2 font-mono break-all text-primary">{JSON.stringify(detail?.new_values?.[key] ?? null)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
