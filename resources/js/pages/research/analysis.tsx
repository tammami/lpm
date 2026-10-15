import { Link, router } from '@inertiajs/react';
import { BookOpenText, FileSpreadsheet, GraduationCap, List, UserRoundCheck, UserRoundX } from 'lucide-react';
import { useState } from 'react';
import { StackedYearChart, type StackGroup } from '@/components/charts/stacked-year-chart';
import { EmptyState } from '@/components/empty-state';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

interface Filters {
    from: number;
    to: number;
    study_program_id: number | null;
    employment_status: string | null;
    verified_only: boolean;
}

interface Share {
    key: string;
    label: string;
    count: number;
    percent: number;
}

/** Kolom rekap sesuai template LPM (lihat WorkRecap::COLUMNS). */
type Recap = Record<'total' | 'journal' | 'proceeding' | 'book' | 'other' | 'sinta' | 'scopus' | 'wos' | 'ip' | 'valid' | 'with_students' | 'students', number>;

interface IndexationCount {
    key: string;
    label: string;
    family: 'sinta' | 'scopus' | 'wos';
    count: number;
}

interface LecturerRow {
    id: number;
    name: string;
    nidn: string | null;
    nuptk: string | null;
    study_program: string | null;
    study_program_code: string | null;
    academic_rank: string | null;
    employment_status: string;
    works: number;
    groups: Record<string, number>;
    recap: Recap;
    last_year: number | null;
    all_time: number;
}

interface Props {
    summary: {
        lecturers: number;
        productive: number;
        without: number;
        rate: number | null;
        works: number;
        per_lecturer: number | null;
        sinta: number;
        scopus: number;
        wos: number;
        verified: number;
        with_students: number;
        student_rate: number | null;
        students: number;
        student_target: number;
    };
    groups: StackGroup[];
    byYear: Record<string, number | string>[];
    byType: Share[];
    byIndexation: IndexationCount[];
    byFunding: Share[];
    bySource: Share[];
    byProgram: { id: number; name: string; lecturers: number; productive: number; rate: number; works: number; per_lecturer: number; recap: Recap; student_rate: number | null }[];
    lecturers: LecturerRow[];
    filters: Filters;
    studyPrograms: Option[];
    yearOptions: Option[];
}

const programColumns: { key: keyof Recap; label: string }[] = [
    { key: 'total', label: 'Total' },
    { key: 'journal', label: 'Artikel jurnal' },
    { key: 'proceeding', label: 'Prosiding' },
    { key: 'book', label: 'Buku' },
    { key: 'other', label: 'Lainnya' },
    { key: 'ip', label: 'HKI' },
    { key: 'sinta', label: 'Sinta' },
    { key: 'scopus', label: 'Scopus' },
    { key: 'wos', label: 'WoS' },
    { key: 'valid', label: 'Valid LPM' },
];

const employmentOptions = [
    { value: 'tetap', label: 'Dosen tetap' },
    { value: 'tidak_tetap', label: 'Dosen tidak tetap' },
];

export default function ResearchAnalysis({ summary, groups, byYear, byType, byIndexation, byFunding, bySource, byProgram, lecturers, filters, studyPrograms, yearOptions }: Props) {
    const [showAllWithout, setShowAllWithout] = useState(false);
    const range = filters.from === filters.to ? String(filters.from) : `${filters.from}–${filters.to}`;
    const query = {
        from: filters.from,
        to: filters.to,
        study_program_id: filters.study_program_id ?? undefined,
        employment_status: filters.employment_status ?? undefined,
        verified_only: filters.verified_only ? 1 : undefined,
    };
    const navigate = (changes: Record<string, string | number | undefined>) =>
        router.get(route('research.analysis'), { ...query, ...changes }, { preserveScroll: true, preserveState: true, replace: true });

    const without = lecturers.filter((l) => l.works === 0).sort((a, b) => (a.study_program ?? '').localeCompare(b.study_program ?? '') || a.name.localeCompare(b.name));
    const top = lecturers.filter((l) => l.works > 0).slice(0, 10);
    const indexation = (family: IndexationCount['family']) => byIndexation.filter((item) => item.family === family);

    return (
        <>
            <PageHeader
                title="Analisis Karya Ilmiah"
                description={`Produktivitas karya dosen aktif ${range}, dihitung seperti rekap template LPM. Karya bersama dihitung untuk setiap dosen yang ditandai, tetapi sekali untuk prodi.`}
                breadcrumbs={[{ label: 'Karya Ilmiah', href: route('research.works.index') }, { label: 'Analisis' }]}
                actions={
                    <>
                        <Button variant="outline" asChild>
                            <Link href={route('research.works.index')}>
                                <List /> Data karya
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={route('research.analysis.export', query)}>
                                <FileSpreadsheet /> Ekspor Excel
                            </a>
                        </Button>
                    </>
                }
            />

            <div className="mb-6 flex flex-col gap-2 rounded-2xl border bg-card p-3 lg:flex-row lg:flex-wrap lg:items-center">
                <span className="px-1 text-xs font-bold text-muted-foreground">Filter</span>
                <div className="flex items-center gap-2">
                    <SelectField label="Dari tahun" value={filters.from} onChange={(v) => navigate({ from: v })} options={yearOptions} className="w-24" />
                    <span className="text-sm text-muted-foreground">s.d.</span>
                    <SelectField label="Sampai tahun" value={filters.to} onChange={(v) => navigate({ to: v })} options={yearOptions} className="w-24" />
                </div>
                <SelectField value={filters.study_program_id ?? 'all'} onChange={(v) => navigate({ study_program_id: v === 'all' ? undefined : v })} options={studyPrograms} allLabel="Semua prodi" className="lg:w-64" />
                <SelectField value={filters.employment_status ?? 'all'} onChange={(v) => navigate({ employment_status: v === 'all' ? undefined : v })} options={employmentOptions} allLabel="Semua status dosen" className="lg:w-48" />
                <label className="inline-flex cursor-pointer items-center gap-2 px-1 text-sm font-medium lg:ml-auto">
                    <Switch checked={filters.verified_only} onCheckedChange={(v) => navigate({ verified_only: v ? 1 : undefined })} />
                    Hanya karya valid
                </label>
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Dosen yang berkarya" value={formatPercent(summary.rate)} icon={UserRoundCheck} hint={`${summary.productive} dari ${summary.lecturers} dosen aktif`}>
                    <Meter label="Persentase dosen berkarya" value={summary.rate} />
                </StatTile>
                <StatTile label="Belum punya karya" value={formatNumber(summary.without)} icon={UserRoundX} tone={summary.without ? 'danger' : 'neutral'} hint={`dosen tanpa karya pada ${range}`} />
                <StatTile label="Jumlah karya" value={formatNumber(summary.works)} icon={BookOpenText} tone="info" hint={`Sinta ${summary.sinta} · Scopus ${summary.scopus} · WoS ${summary.wos} · ${summary.verified} valid`} />
                <StatTile
                    label="Melibatkan mahasiswa"
                    value={formatPercent(summary.student_rate)}
                    icon={GraduationCap}
                    tone={summary.student_rate !== null && summary.student_rate >= summary.student_target ? 'primary' : 'gold'}
                    hint={`${summary.with_students} karya · ${summary.students} mahasiswa · acuan ≥ ${summary.student_target}%`}
                >
                    <Meter label="Persentase karya melibatkan mahasiswa" value={summary.student_rate} />
                </StatTile>
            </div>

            {summary.lecturers === 0 ? (
                <Card className="mt-6">
                    <EmptyState icon={UserRoundX} title="Tidak ada dosen aktif pada filter ini" description="Ubah filter prodi atau status kepegawaian." />
                </Card>
            ) : (
                <>
                    <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-5">
                        <Card className="xl:col-span-3">
                            <CardHeader>
                                <CardTitle>Karya per tahun</CardTitle>
                                <CardDescription>Jumlah karya menurut kelompok jenis. Arahkan kursor untuk rincian dan jumlah dosen yang berkarya.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <StackedYearChart data={byYear} groups={groups} />
                            </CardContent>
                        </Card>
                        <Card className="xl:col-span-2">
                            <CardHeader>
                                <CardTitle>Sebaran jenis karya</CardTitle>
                                <CardDescription>{formatNumber(summary.works)} karya pada {range}.</CardDescription>
                            </CardHeader>
                            <CardContent>{byType.length ? <ShareBars items={byType} /> : <Muted>Belum ada karya pada rentang ini.</Muted>}</CardContent>
                        </Card>
                    </div>

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>Produktivitas per program studi</CardTitle>
                            <CardDescription>Kolom mengikuti sheet Rekap per Prodi pada template LPM. Diurutkan dari persentase dosen berkarya terendah — prodi teratas paling perlu pendampingan.</CardDescription>
                        </CardHeader>
                        <CardContent className="relative overflow-x-auto">
                            <table className="w-full min-w-[1040px] text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs font-semibold text-muted-foreground">
                                        <th className="py-2 pr-4">Program studi</th>
                                        <th className="w-44 py-2 pr-4">Dosen berkarya</th>
                                        {programColumns.map((column) => (
                                            <th key={column.key} className="py-2 pr-3 text-right whitespace-nowrap">
                                                {column.label}
                                            </th>
                                        ))}
                                        <th className="py-2 text-right whitespace-nowrap">Dgn mhs.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {byProgram.map((program) => (
                                        <tr key={program.id} className="border-b last:border-0">
                                            <td className="py-2.5 pr-4 font-medium">
                                                <button type="button" className="min-h-6 rounded text-left hover:text-primary" onClick={() => navigate({ study_program_id: program.id })}>
                                                    {program.name}
                                                </button>
                                            </td>
                                            <td className="py-2.5 pr-4">
                                                <div className="flex items-center gap-2">
                                                    <Meter label="Persentase dosen berkarya di prodi" value={program.rate} size="sm" className="flex-1" />
                                                    <span className="w-24 text-right text-xs tabular">
                                                        <b>{program.productive}</b>/{program.lecturers} · {formatPercent(program.rate, 0)}
                                                    </span>
                                                </div>
                                            </td>
                                            {programColumns.map((column) => (
                                                <td key={column.key} className={cn('py-2.5 pr-3 text-right tabular', column.key === 'total' && 'font-bold', !program.recap[column.key] && 'text-muted-foreground')}>
                                                    {program.recap[column.key] || '–'}
                                                </td>
                                            ))}
                                            <td className="py-2.5 text-right tabular">{program.student_rate === null ? '–' : formatPercent(program.student_rate, 0)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card className="mt-6">
                        <CardHeader className="flex flex-row items-start justify-between gap-4">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    Dosen belum punya karya <span className="rounded-full bg-danger-soft px-2 py-0.5 text-xs font-bold text-danger-foreground tabular">{without.length}</span>
                                </CardTitle>
                                <CardDescription>Dosen aktif tanpa karya tercatat pada {range}{filters.verified_only ? ' (yang terverifikasi)' : ''}. Daftar lengkap ada di ekspor Excel.</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent className="relative overflow-x-auto">
                            {without.length === 0 ? (
                                <Muted>Semua dosen pada filter ini sudah memiliki karya.</Muted>
                            ) : (
                                <>
                                    <table className="w-full min-w-[640px] text-sm">
                                        <thead>
                                            <tr className="border-b text-left text-xs font-semibold text-muted-foreground">
                                                <th className="py-2 pr-4">Dosen</th>
                                                <th className="py-2 pr-4">Program studi</th>
                                                <th className="py-2 pr-4">Jabatan</th>
                                                <th className="py-2 pr-4">Karya terakhir</th>
                                                <th className="relative py-2">
                                                    <span className="sr-only">Aksi</span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {(showAllWithout ? without : without.slice(0, 15)).map((lecturer) => (
                                                <tr key={lecturer.id} className="border-b last:border-0">
                                                    <td className="py-2.5 pr-4">
                                                        <div className="font-medium">{lecturer.name}</div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {lecturer.nidn ? `NIDN ${lecturer.nidn}` : lecturer.nuptk ? `NUPTK ${lecturer.nuptk}` : 'NIDN —'} · {lecturer.employment_status === 'tetap' ? 'Tetap' : 'Tidak tetap'}
                                                        </div>
                                                    </td>
                                                    <td className="py-2.5 pr-4 text-muted-foreground">{lecturer.study_program}</td>
                                                    <td className="py-2.5 pr-4">{lecturer.academic_rank ?? '—'}</td>
                                                    <td className="py-2.5 pr-4">
                                                        {lecturer.last_year ? (
                                                            <span className="tabular">{lecturer.last_year}</span>
                                                        ) : (
                                                            <span className="font-semibold text-danger-foreground">Belum pernah</span>
                                                        )}
                                                    </td>
                                                    <td className="py-2.5 text-right">
                                                        <Button variant="ghost" size="sm" asChild>
                                                            <Link href={route('research.works.index', { lecturer_id: lecturer.id })}>Lihat data</Link>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    {without.length > 15 && (
                                        <Button variant="ghost" size="sm" className="mt-2" onClick={() => setShowAllWithout((v) => !v)}>
                                            {showAllWithout ? 'Tampilkan lebih sedikit' : `Tampilkan semua (${without.length})`}
                                        </Button>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
                        <Card>
                            <CardHeader>
                                <CardTitle>Ringkasan akreditasi/indeksasi</CardTitle>
                                <CardDescription>Seperti pada Rekap LPM: Sinta 1–6, Scopus Q1–Q4, dan Web of Science.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-5">
                                <RankStrip title={`Sinta (${summary.sinta})`} items={indexation('sinta')} />
                                <RankStrip title={`Scopus (${summary.scopus})`} items={indexation('scopus')} />
                                <RankStrip title={`Web of Science (${summary.wos})`} items={indexation('wos')} />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Sumber dana</CardTitle>
                                <CardDescription>Bahan analisis kemandirian dan hibah riset.</CardDescription>
                            </CardHeader>
                            <CardContent>{byFunding.length ? <ShareBars items={byFunding} /> : <Muted>Belum ada data.</Muted>}</CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Asal kegiatan</CardTitle>
                                <CardDescription>Luaran penelitian dibanding pengabdian (PkM).</CardDescription>
                            </CardHeader>
                            <CardContent>{bySource.length ? <ShareBars items={bySource} /> : <Muted>Belum ada data.</Muted>}</CardContent>
                        </Card>
                    </div>

                    {top.length > 0 && (
                        <Card className="mt-6">
                            <CardHeader>
                                <CardTitle>Dosen paling produktif</CardTitle>
                                <CardDescription>10 dosen dengan karya terbanyak pada {range}.</CardDescription>
                            </CardHeader>
                            <CardContent className="relative overflow-x-auto">
                                <table className="w-full min-w-[640px] text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs font-semibold text-muted-foreground">
                                            <th className="py-2 pr-4">Dosen</th>
                                            {groups.map((group) => (
                                                <th key={group.key} className="py-2 pr-4 text-right">
                                                    {group.label}
                                                </th>
                                            ))}
                                            <th className="py-2 pr-4 text-right">Total</th>
                                            <th className="py-2 text-right whitespace-nowrap">Dgn mahasiswa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {top.map((lecturer) => (
                                            <tr key={lecturer.id} className="border-b last:border-0">
                                                <td className="py-2.5 pr-4">
                                                    <Link href={route('research.works.index', { lecturer_id: lecturer.id })} className="font-medium hover:text-primary">
                                                        {lecturer.name}
                                                    </Link>
                                                    <div className="text-xs text-muted-foreground">{lecturer.study_program}</div>
                                                </td>
                                                {groups.map((group) => (
                                                    <td key={group.key} className={cn('py-2.5 pr-4 text-right tabular', !lecturer.groups[group.key] && 'text-muted-foreground')}>
                                                        {lecturer.groups[group.key] || '–'}
                                                    </td>
                                                ))}
                                                <td className="py-2.5 pr-4 text-right font-bold tabular">{lecturer.works}</td>
                                                <td className={cn('py-2.5 text-right tabular', !lecturer.recap.with_students && 'text-muted-foreground')}>{lecturer.recap.with_students || '–'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </CardContent>
                        </Card>
                    )}
                </>
            )}
        </>
    );
}

/** Bar horizontal satu hue: label + jumlah di atas, panjang bar = porsi. */
function ShareBars({ items }: { items: Share[] }) {
    const max = Math.max(...items.map((i) => i.count), 1);

    return (
        <div className="flex flex-col gap-3">
            {items.map((item) => (
                <div key={item.key} title={`${item.label}: ${item.count} (${formatPercent(item.percent)})`}>
                    <div className="mb-1 flex items-baseline justify-between gap-3 text-[13px]">
                        <span className="truncate font-medium">{item.label}</span>
                        <span className="shrink-0 tabular">
                            <b>{item.count}</b> <span className="text-xs text-muted-foreground">{formatPercent(item.percent, 0)}</span>
                        </span>
                    </div>
                    <div className="h-2 rounded-full bg-primary/10">
                        <div className="h-full rounded-full bg-chart-1" style={{ width: `${(item.count / max) * 100}%` }} />
                    </div>
                </div>
            ))}
        </div>
    );
}

/** Deret peringkat (S1…S6 / Q1…Q4 / SCIE…ESCI) sebagai kolom kecil berlabel. */
function RankStrip({ title, items }: { title: string; items: IndexationCount[] }) {
    const max = Math.max(...items.map((i) => i.count), 1);

    return (
        <div>
            <div className="mb-2 text-xs font-bold text-muted-foreground">{title}</div>
            <div className="flex items-end gap-2">
                {items.map((item) => (
                    <div key={item.key} className="flex flex-1 flex-col items-center gap-1" title={`${item.label}: ${item.count}`}>
                        <span className="text-xs font-bold tabular">{item.count}</span>
                        <div className="flex h-16 w-full items-end rounded-md bg-primary/8">
                            <div className="w-full rounded-t-[4px] bg-chart-1" style={{ height: `${(item.count / max) * 100}%` }} />
                        </div>
                        <span className="text-[11px] text-muted-foreground">{item.label.replace('Sinta ', 'S').replace('Scopus ', '').replace('WoS ', '')}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function Muted({ children }: { children: React.ReactNode }) {
    return <p className="py-6 text-center text-sm text-muted-foreground">{children}</p>;
}
