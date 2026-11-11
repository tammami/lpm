import { CheckCircle2, CircleDashed, CircleSlash, Download, ShieldCheck, Users } from 'lucide-react';
import { type Column, DataTable } from '@/components/data-table';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { TableToolbar } from '@/components/table-toolbar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatNumber, formatPercent } from '@/lib/format';
import { cleanQuery } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { SurveySummary } from './index';

type State = 'complete' | 'partial' | 'none';

interface Respondent {
    user_id: number;
    name: string;
    identifier: string | null;
    study_program: string | null;
    targets: number;
    submitted: number;
    state: State;
}

interface ProgramRow {
    study_program_id: number | null;
    name: string;
    total: number;
    complete: number;
    partial: number;
    none: number;
    rate: number | null;
}

interface Props {
    survey: SurveySummary;
    byStudyProgram: ProgramRow[];
    totals: { total: number; complete: number; partial: number; none: number };
    respondents: Paginated<Respondent>;
    filters: Record<string, string | undefined>;
}

const stateBadge: Record<State, { tone: 'success' | 'warning' | 'danger'; label: string }> = {
    complete: { tone: 'success', label: 'Lengkap' },
    partial: { tone: 'warning', label: 'Sebagian' },
    none: { tone: 'danger', label: 'Belum mengisi' },
};

const stateOptions = [
    { value: 'pending', label: 'Belum lengkap' },
    { value: 'none', label: 'Belum mengisi sama sekali' },
    { value: 'partial', label: 'Baru sebagian' },
    { value: 'complete', label: 'Sudah lengkap' },
];

export default function SurveyRespondents({ survey, byStudyProgram, totals, respondents, filters }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        study_program_id: filters.study_program_id ?? 'all',
        state: filters.state ?? 'all',
    });
    const teaching = survey.mode === 'teaching_evaluation';
    const isLecturer = !teaching && survey.respondent_label.toLowerCase().includes('dosen');
    const programOptions = byStudyProgram
        .filter((row) => row.study_program_id !== null)
        .map((row) => ({
            value: String(row.study_program_id),
            label: row.name,
        }));

    const columns: Column<Respondent>[] = [
        {
            key: 'name',
            header: 'Responden',
            cell: (row) => (
                <div>
                    <div className="font-semibold">{row.name}</div>
                    <div className="font-mono text-xs text-muted-foreground">{row.identifier ?? '—'}</div>
                </div>
            ),
        },
        {
            key: 'program',
            header: 'Program studi',
            cell: (row) => <span className="text-[13px] text-muted-foreground">{row.study_program ?? '—'}</span>,
        },
        {
            key: 'progress',
            header: teaching ? 'Dosen dievaluasi' : 'Pengisian',
            className: 'w-40',
            cell: (row) => (
                <span className="text-sm tabular">
                    <b>{row.submitted}</b>
                    <span className="text-muted-foreground"> / {row.targets}</span>
                </span>
            ),
        },
        {
            key: 'state',
            header: 'Status',
            className: 'w-36',
            cell: (row) => <StatusBadge tone={stateBadge[row.state].tone}>{stateBadge[row.state].label}</StatusBadge>,
        },
    ];

    return (
        <>
            <PageHeader
                title="Rekap Responden per Prodi"
                description={survey.title}
                breadcrumbs={[
                    { label: 'Kegiatan Monev', href: route('surveys.index') },
                    {
                        label: survey.code,
                        href: route('surveys.show', survey.id),
                    },
                    { label: 'Rekap responden' },
                ]}
                actions={
                    <Button variant="outline" asChild>
                        <a href={route('surveys.respondents.export', [survey.id, cleanQuery(query)])}>
                            <Download /> Ekspor Excel
                        </a>
                    </Button>
                }
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label={isLecturer ? 'Dosen wajib mengisi' : 'Mahasiswa wajib mengisi'} value={formatNumber(totals.total)} icon={Users} hint="Dihitung per orang" />
                <StatTile
                    label="Sudah lengkap"
                    value={formatNumber(totals.complete)}
                    icon={CheckCircle2}
                    tone="gold"
                    hint={formatPercent(totals.total ? (totals.complete / totals.total) * 100 : null)}
                />
                <StatTile label="Baru sebagian" value={formatNumber(totals.partial)} icon={CircleDashed} tone="info" hint={teaching ? 'Belum semua dosen pengampu dievaluasi' : '—'} />
                <StatTile label="Belum mengisi" value={formatNumber(totals.none)} icon={CircleSlash} tone="danger" hint="Belum mengirim satu pun" />
            </div>

            <Card className="mt-6 gap-0 overflow-hidden py-0">
                <CardHeader className="border-b py-4">
                    <CardTitle>Per program studi</CardTitle>
                    <CardDescription>Dikelompokkan menurut prodi asal {isLecturer ? 'dosen' : 'mahasiswa'}. Klik nama prodi untuk melihat daftar yang belum lengkap.</CardDescription>
                </CardHeader>
                <CardContent className="overflow-x-auto px-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">Program studi</TableHead>
                                <TableHead className="text-right">Total</TableHead>
                                <TableHead className="text-right">Lengkap</TableHead>
                                <TableHead className="text-right">Sebagian</TableHead>
                                <TableHead className="text-right">Belum</TableHead>
                                <TableHead className="w-48 px-4">% lengkap</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {byStudyProgram.map((row) => (
                                <TableRow key={row.study_program_id ?? 0}>
                                    <TableCell className="px-4">
                                        {row.study_program_id ? (
                                            <button
                                                type="button"
                                                className="text-left font-medium hover:text-primary hover:underline"
                                                onClick={() => {
                                                    setFilter('study_program_id', String(row.study_program_id));
                                                    setFilter('state', 'pending');
                                                }}
                                            >
                                                {row.name}
                                            </button>
                                        ) : (
                                            <span className="font-medium">{row.name}</span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right tabular">{formatNumber(row.total)}</TableCell>
                                    <TableCell className="text-right tabular">{formatNumber(row.complete)}</TableCell>
                                    <TableCell className="text-right tabular">{formatNumber(row.partial)}</TableCell>
                                    <TableCell className="text-right font-semibold tabular">{formatNumber(row.none)}</TableCell>
                                    <TableCell className="px-4">
                                        <div className="mb-1 text-xs font-semibold tabular">{formatPercent(row.rate)}</div>
                                        <Meter label="Persentase lengkap" value={row.rate} size="sm" />
                                    </TableCell>
                                </TableRow>
                            ))}
                            {byStudyProgram.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="py-10 text-center text-sm text-muted-foreground">
                                        Belum ada responden yang wajib mengisi pada cakupan ini.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <div className="mt-6 mb-3 flex items-start gap-2 rounded-xl bg-muted/50 p-3 text-xs leading-relaxed text-muted-foreground">
                <ShieldCheck className="size-4 shrink-0 text-primary" />
                <span>
                    Hanya <b>status pengisian</b> yang ditampilkan — isi evaluasi tidak dapat ditelusuri ke responden.
                    {teaching && ' Satu mahasiswa wajib mengevaluasi setiap dosen pengampu di kelas yang diikutinya; "Sebagian" berarti masih ada dosen yang belum dievaluasi.'}
                </span>
            </div>

            <DataTable
                columns={columns}
                data={respondents.data}
                pagination={respondents}
                rowKey={(row) => row.user_id}
                toolbar={
                    <TableToolbar search={query.search} onSearch={(v) => setFilter('search', v)} placeholder={isLecturer ? 'Cari nama atau NIDN…' : 'Cari nama atau NIM…'}>
                        {programOptions.length > 1 && <SelectField value={query.study_program_id} onChange={(v) => setFilter('study_program_id', v)} options={programOptions} allLabel="Semua prodi" />}
                        <SelectField value={query.state} onChange={(v) => setFilter('state', v)} options={stateOptions} allLabel="Semua status" />
                    </TableToolbar>
                }
                emptyTitle="Tidak ada responden yang cocok"
            />
        </>
    );
}
