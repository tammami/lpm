import { useForm } from '@inertiajs/react';
import { RotateCcw, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { type Column, DataTable } from '@/components/data-table';
import { FormDialog } from '@/components/form-dialog';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { EnumBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDateTime } from '@/lib/format';
import type { Paginated } from '@/types';
import type { SurveySummary } from './index';

interface Participation {
    id: number;
    name: string;
    username: string | null;
    target: string;
    status: string;
    status_label: string;
    submitted_at: string | null;
    reopened_at: string | null;
    reopen_reason: string | null;
    reopener: string | null;
    submission_count: number;
}

interface Props {
    survey: SurveySummary;
    participations: Paginated<Participation>;
    filters: { search?: string };
    canReopen: boolean;
}

export default function Participations({ survey, participations, filters, canReopen }: Props) {
    const { filters: query, setFilter } = useQueryFilters({ search: filters.search ?? '' });
    const [reopening, setReopening] = useState<Participation | null>(null);

    const columns: Column<Participation>[] = [
        {
            key: 'name',
            header: 'Responden',
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.name}</div>
                    <div className="font-mono text-xs text-muted-foreground">{row.username}</div>
                </div>
            ),
        },
        { key: 'target', header: 'Yang dievaluasi', cell: (row) => <span className="text-[13px] text-muted-foreground">{row.target}</span> },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <div>
                    <EnumBadge value={row.status} label={row.status_label} />
                    {row.submission_count > 1 && <div className="mt-1 text-[11px] text-muted-foreground">Dikirim {row.submission_count}×</div>}
                </div>
            ),
        },
        {
            key: 'time',
            header: 'Waktu',
            cell: (row) =>
                row.status === 'reopened' ? (
                    <div className="text-xs">
                        <div>Dibuka kembali {formatDateTime(row.reopened_at)}</div>
                        <div className="text-muted-foreground">
                            oleh {row.reopener}: “{row.reopen_reason}”
                        </div>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">{formatDateTime(row.submitted_at)}</span>
                ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-40 text-right',
            cell: (row) =>
                canReopen &&
                row.status === 'submitted' && (
                    <Button variant="outline" size="sm" onClick={() => setReopening(row)}>
                        <RotateCcw /> Buka kembali
                    </Button>
                ),
        },
    ];

    return (
        <>
            <PageHeader
                title="Status Responden"
                description={survey.title}
                breadcrumbs={[{ label: 'Kegiatan Monev', href: route('surveys.index') }, { label: survey.code, href: route('surveys.show', survey.id) }, { label: 'Status responden' }]}
            />
            <Alert className="mb-5 border-primary/20 bg-secondary/50">
                <ShieldCheck className="text-primary" />
                <AlertDescription>
                    Halaman ini hanya menampilkan <b>siapa yang sudah mengisi</b>, bukan isi jawabannya. Membuka kembali pengisian akan membatalkan respons sebelumnya
                    (tanpa membukanya) dan tercatat di log audit.
                </AlertDescription>
            </Alert>
            <DataTable
                columns={columns}
                data={participations.data}
                pagination={participations}
                rowKey={(row) => row.id}
                toolbar={<TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder="Cari nama atau NIM/NIDN…" />}
                emptyTitle="Belum ada responden yang mengisi"
            />
            {reopening && <ReopenDialog surveyId={survey.id} participation={reopening} onClose={() => setReopening(null)} />}
        </>
    );
}

function ReopenDialog({ surveyId, participation, onClose }: { surveyId: number; participation: Participation; onClose: () => void }) {
    const form = useForm({ reason: '' });

    return (
        <FormDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title="Buka kembali pengisian?"
            description={`${participation.name} — ${participation.target}`}
            submitLabel="Buka kembali"
            processing={form.processing}
            onSubmit={() => form.post(route('surveys.participations.reopen', [surveyId, participation.id]), { preserveScroll: true, onSuccess: onClose })}
        >
            <FormField label="Alasan resmi" error={form.errors.reason} required hint="Mis. salah memilih dosen karena kendala perangkat, berdasarkan permohonan tertulis.">
                <Textarea rows={4} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
            </FormField>
        </FormDialog>
    );
}
