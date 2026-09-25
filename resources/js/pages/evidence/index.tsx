import { Link } from '@inertiajs/react';
import { CheckCircle2, Clock, FolderArchive, Paperclip, Plus, Tags, XCircle } from 'lucide-react';
import { useState } from 'react';
import { type Column, DataTable } from '@/components/data-table';
import { EvidenceUploadDialog } from '@/components/evidence/evidence-upload-dialog';
import { FileIcon } from '@/components/evidence/file-icon';
import { evidenceTone, type EvidenceRow } from '@/components/evidence/types';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatBytes, formatNumber, fromNow } from '@/lib/format';
import type { Option, Paginated } from '@/types';

interface Props {
    evidence: Paginated<EvidenceRow>;
    filters: Record<string, string | undefined>;
    stats: { total: number; verified: number; pending: number; rejected: number };
    categories: Option[];
    units: Option[];
    periods: Option[];
    studyPrograms: Option[];
    years: Option[];
    maxUploadMb: number;
    allowedExtensions: string[];
    canManage: boolean;
}

const statuses = [
    { value: 'verified', label: 'Terverifikasi' },
    { value: 'pending', label: 'Menunggu verifikasi' },
    { value: 'rejected', label: 'Ditolak' },
    { value: 'expired', label: 'Kedaluwarsa' },
];

export default function EvidenceIndex(props: Props) {
    const { evidence, filters, stats, categories, studyPrograms, years, canManage } = props;
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        sort: filters.sort ?? '',
        evidence_category_id: filters.evidence_category_id ?? 'all',
        year: filters.year ?? 'all',
        status: filters.status ?? 'all',
        study_program_id: filters.study_program_id ?? 'all',
    });
    const [uploading, setUploading] = useState(false);

    const columns: Column<EvidenceRow>[] = [
        {
            key: 'title',
            header: 'Dokumen',
            sortable: true,
            cell: (row) => (
                <Link href={row.show_url} className="group flex items-center gap-3">
                    <FileIcon mime={row.mime_type} link={row.document_type === 'link'} />
                    <div className="min-w-0">
                        <div className="truncate font-semibold group-hover:text-primary">{row.title}</div>
                        <div className="truncate text-xs text-muted-foreground">
                            <span className="font-mono">{row.code}</span> · v{row.version} {row.size ? `· ${formatBytes(row.size)}` : row.document_type === 'link' ? '· tautan' : ''}
                        </div>
                    </div>
                </Link>
            ),
        },
        { key: 'category', header: 'Kategori', cell: (row) => <span className="text-xs">{row.category ?? '—'}</span> },
        {
            key: 'unit',
            header: 'Unit · Tahun',
            cell: (row) => (
                <div className="text-xs">
                    <div>{row.unit_name ?? '—'}</div>
                    <div className="text-muted-foreground">{row.year ?? '—'}</div>
                </div>
            ),
        },
        {
            key: 'used',
            header: 'Dipakai',
            cell: (row) => (
                <span className="inline-flex items-center gap-1 text-xs text-muted-foreground tabular">
                    <Paperclip className="size-3" /> {row.mappings_count ?? 0}×
                </span>
            ),
        },
        { key: 'status', header: 'Status', cell: (row) => <StatusBadge tone={evidenceTone[row.status] ?? 'neutral'}>{row.status_label}</StatusBadge> },
        { key: 'updated_at', header: 'Diperbarui', sortable: true, cell: (row) => <span className="text-xs text-muted-foreground">{fromNow(row.updated_at)}</span> },
    ];

    return (
        <>
            <PageHeader
                title="Dokumen Bukti"
                description="Satu repositori untuk seluruh bukti mutu — unggah sekali, pakai berkali-kali untuk Monev, AMI, rencana aksi, dan akreditasi. File hanya dapat diunduh melalui pemeriksaan hak akses."
                breadcrumbs={[{ label: 'Penjaminan Mutu' }, { label: 'Dokumen Bukti' }]}
                actions={
                    <>
                        {canManage && route().has('evidence-categories.index') && (
                            <Button variant="outline" asChild>
                                <Link href={route('evidence-categories.index')}>
                                    <Tags /> Kategori
                                </Link>
                            </Button>
                        )}
                        {canManage && (
                            <Button onClick={() => setUploading(true)}>
                                <Plus /> Unggah dokumen
                            </Button>
                        )}
                    </>
                }
            />
            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Total dokumen" value={formatNumber(stats.total)} icon={FolderArchive} tone="neutral" />
                <StatTile label="Terverifikasi" value={formatNumber(stats.verified)} icon={CheckCircle2} />
                <StatTile label="Menunggu verifikasi" value={formatNumber(stats.pending)} icon={Clock} tone="gold" />
                <StatTile label="Ditolak" value={formatNumber(stats.rejected)} icon={XCircle} tone={stats.rejected ? 'danger' : 'neutral'} />
            </div>
            <DataTable
                columns={columns}
                data={evidence.data}
                pagination={evidence}
                rowKey={(row) => row.id}
                sort={query.sort}
                onSort={(sort) => setFilter('sort', sort)}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari judul, kode, unit…">
                        <SelectField value={query.evidence_category_id} onChange={(v) => setFilter('evidence_category_id', v)} options={categories} allLabel="Semua kategori" />
                        <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={studyPrograms} allLabel="Semua prodi" />
                        <SelectField value={query.year} onChange={(v) => setFilter('year', v)} options={years} allLabel="Semua tahun" />
                        <SelectField value={query.status} onChange={(v) => setFilter('status', v)} options={statuses} allLabel="Semua status" />
                    </TableToolbar>
                }
                emptyTitle="Belum ada dokumen"
                emptyDescription="Unggah RPS, SK, laporan, notulen, atau bukti lainnya."
            />
            {uploading && <EvidenceUploadDialog {...props} onClose={() => setUploading(false)} />}
        </>
    );
}
