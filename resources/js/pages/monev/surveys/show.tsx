import { Link } from '@inertiajs/react';
import { Archive, BarChart3, CalendarClock, CheckCircle2, EyeOff, Lock, Pencil, PlayCircle, RotateCcw, ShieldCheck, Trash2, Users, UserSearch } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatDateTime, formatNumber, formatPercent } from '@/lib/format';
import { daysLeft, surveyTone } from '@/lib/survey';
import type { SurveySummary } from './index';

interface Props {
    survey: SurveySummary & { description: string | null; creator: string | null; opened_at: string | null; closed_at: string | null; reopened_count: number };
    progress: { eligible: number; submitted: number; rate: number | null };
    byStudyProgram: { study_program_id: number; name: string; eligible: number; submitted: number; rate: number | null }[];
    byAssignment: {
        teaching_assignment_id: number;
        lecturer: string;
        course: string;
        course_code: string;
        class_code: string;
        study_program: string;
        eligible: number;
        submitted: number;
        rate: number | null;
        sufficient: boolean;
    }[];
    can: { manage: boolean; reopen: boolean; analytics: boolean };
}

export default function SurveyShow({ survey, progress, byStudyProgram, byAssignment, can }: Props) {
    const [search, setSearch] = useState('');
    const [filter, setFilter] = useState<'all' | 'insufficient'>('all');
    const left = daysLeft(survey.ends_at);

    const rows = useMemo(() => {
        const term = search.toLowerCase();
        return byAssignment.filter(
            (row) =>
                (filter === 'all' || !row.sufficient) &&
                (!term || row.lecturer.toLowerCase().includes(term) || row.course.toLowerCase().includes(term) || row.course_code.toLowerCase().includes(term)),
        );
    }, [byAssignment, search, filter]);

    const insufficient = byAssignment.filter((row) => !row.sufficient).length;

    return (
        <>
            <PageHeader
                title={survey.title}
                breadcrumbs={[{ label: 'e-Monev' }, { label: 'Kegiatan Monev', href: route('surveys.index') }, { label: survey.code }]}
                meta={
                    <>
                        <StatusBadge tone={surveyTone[survey.status]}>{survey.status_label}</StatusBadge>
                        <StatusBadge tone="primary" dot={false}>
                            {survey.instrument} v{survey.instrument_version}
                        </StatusBadge>
                        {survey.period && <StatusBadge tone="gold" dot={false}>{survey.period}</StatusBadge>}
                        {survey.is_anonymous && (
                            <StatusBadge tone="neutral" dot={false}>
                                <EyeOff className="size-3" /> Anonim · min. {survey.min_responses} respons
                            </StatusBadge>
                        )}
                    </>
                }
                actions={
                    <>
                        {can.analytics && survey.status !== 'draft' && (
                            <Button variant="outline" asChild>
                                <Link href={route('analytics.index', { survey: survey.id })}>
                                    <BarChart3 /> Lihat hasil
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={route('surveys.participations', survey.id)}>
                                <UserSearch /> Status responden
                            </Link>
                        </Button>
                        {can.manage && (
                            <>
                                <Button variant="outline" asChild>
                                    <Link href={route('surveys.edit', survey.id)}>
                                        <Pencil /> Ubah
                                    </Link>
                                </Button>
                                {survey.status === 'draft' && (
                                    <>
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost" size="icon" aria-label="Hapus">
                                                    <Trash2 className="text-destructive" />
                                                </Button>
                                            }
                                            title="Hapus kegiatan draf ini?"
                                            href={route('surveys.destroy', survey.id)}
                                            confirmLabel="Hapus"
                                        />
                                        <Transition surveyId={survey.id} status="active" label="Buka pengisian" icon={PlayCircle} description="Responden yang eligible akan melihat Monev ini di portal dan menerima notifikasi." />
                                    </>
                                )}
                                {survey.status === 'active' && (
                                    <Transition surveyId={survey.id} status="closed" label="Tutup pengisian" icon={Lock} description="Responden tidak dapat mengisi lagi. Hasil siap dianalisis." variant="outline" />
                                )}
                                {survey.status === 'closed' && (
                                    <>
                                        <Transition surveyId={survey.id} status="active" label="Buka lagi" icon={RotateCcw} description="Perpanjang pengisian (pastikan tanggal berakhir masih di masa depan)." variant="outline" />
                                        <Transition surveyId={survey.id} status="archived" label="Arsipkan" icon={Archive} description="Kegiatan diarsipkan. Data tetap tersedia untuk analisis tren." variant="outline" />
                                    </>
                                )}
                            </>
                        )}
                    </>
                }
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Responden eligible" value={formatNumber(progress.eligible)} icon={Users} hint={survey.mode === 'teaching_evaluation' ? 'Pasangan mahasiswa × dosen pengampu' : survey.respondent_label} />
                <StatTile label="Sudah mengisi" value={formatNumber(progress.submitted)} icon={CheckCircle2} tone="gold" hint={`${formatNumber(progress.eligible - progress.submitted)} belum mengisi`} />
                <StatTile label="Response rate" value={formatPercent(progress.rate)} icon={BarChart3} tone="info">
                    <Meter value={progress.rate} />
                </StatTile>
                <StatTile
                    label={survey.status === 'active' ? 'Sisa waktu' : 'Jadwal'}
                    value={survey.status === 'active' ? (left > 0 ? `${left} hari` : 'Hari terakhir') : survey.status_label}
                    icon={CalendarClock}
                    tone={survey.status === 'active' && left <= 3 ? 'danger' : 'neutral'}
                    hint={`${formatDateTime(survey.starts_at)} – ${formatDateTime(survey.ends_at)}`}
                />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 2xl:grid-cols-[360px_minmax(0,1fr)]">
                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Per program studi</CardTitle>
                        <CardDescription>Kelengkapan pengisian di setiap prodi.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2 2xl:grid-cols-1">
                        {byStudyProgram.length === 0 && <p className="text-sm text-muted-foreground">Belum ada responden eligible pada cakupan ini.</p>}
                        {byStudyProgram.map((row) => (
                            <div key={row.study_program_id}>
                                <div className="mb-1.5 flex items-baseline justify-between gap-2 text-sm">
                                    <span className="truncate font-medium">{row.name}</span>
                                    <span className="shrink-0 text-xs text-muted-foreground tabular">
                                        <span className="font-bold text-foreground">{formatPercent(row.rate)}</span> · {row.submitted}/{row.eligible}
                                    </span>
                                </div>
                                <Meter value={row.rate} size="sm" />
                            </div>
                        ))}
                        <div className="rounded-xl bg-muted/50 p-3 text-xs leading-relaxed text-muted-foreground md:col-span-2 2xl:col-span-1">
                            <ShieldCheck className="mb-1 size-4 text-primary" />
                            Monitoring ini hanya menampilkan <b>status pengisian</b>. Isi evaluasi tersimpan terpisah dan tidak dapat ditelusuri ke mahasiswa.
                        </div>
                    </CardContent>
                </Card>

                {survey.mode === 'teaching_evaluation' ? (
                    <Card className="gap-0 overflow-hidden py-0">
                        <div className="flex flex-col gap-3 border-b p-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h3 className="font-bold">Per dosen & kelas</h3>
                                <p className="text-xs text-muted-foreground">
                                    {insufficient > 0 ? `${insufficient} penugasan belum mencapai minimum ${survey.min_responses} respons — hasilnya akan disembunyikan.` : 'Semua penugasan telah mencapai ambang minimum.'}
                                </p>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <ToggleGroup type="single" value={filter} onValueChange={(v) => v && setFilter(v as 'all' | 'insufficient')} variant="outline" size="sm">
                                    <ToggleGroupItem value="all" className="px-3">Semua</ToggleGroupItem>
                                    <ToggleGroupItem value="insufficient" className="px-3">Di bawah ambang</ToggleGroupItem>
                                </ToggleGroup>
                                <SearchInput value={search} onChange={setSearch} placeholder="Cari dosen/MK…" />
                            </div>
                        </div>
                        <div className="max-h-[620px] overflow-auto">
                            <Table>
                                <TableHeader className="sticky top-0 z-10 bg-muted/90 backdrop-blur">
                                    <TableRow>
                                        <TableHead className="px-4">Mata kuliah</TableHead>
                                        <TableHead>Dosen</TableHead>
                                        <TableHead className="w-48">Pengisian</TableHead>
                                        <TableHead>Ambang</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.map((row) => (
                                        <TableRow key={row.teaching_assignment_id}>
                                            <TableCell className="px-4">
                                                <div className="font-medium">{row.course}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {row.course_code} · Kelas {row.class_code} · {row.study_program}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-[13px]">{row.lecturer}</TableCell>
                                            <TableCell>
                                                <div className="mb-1 flex justify-between text-xs tabular">
                                                    <span className="font-semibold">{formatPercent(row.rate, 0)}</span>
                                                    <span className="text-muted-foreground">
                                                        {row.submitted}/{row.eligible}
                                                    </span>
                                                </div>
                                                <Meter value={row.rate} size="sm" />
                                            </TableCell>
                                            <TableCell>{row.sufficient ? <StatusBadge tone="success">Cukup</StatusBadge> : <StatusBadge tone="warning">Kurang</StatusBadge>}</TableCell>
                                        </TableRow>
                                    ))}
                                    {rows.length === 0 && (
                                        <TableRow>
                                            <TableCell colSpan={4} className="py-10 text-center text-sm text-muted-foreground">
                                                Tidak ada data yang cocok.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </Card>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi kegiatan</CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-line text-muted-foreground">{survey.description ?? 'Tidak ada deskripsi.'}</CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function Transition({
    surveyId,
    status,
    label,
    description,
    icon: Icon,
    variant = 'default',
}: {
    surveyId: number;
    status: string;
    label: string;
    description: string;
    icon: typeof Lock;
    variant?: 'default' | 'outline';
}) {
    return (
        <ConfirmDialog
            trigger={
                <Button variant={variant}>
                    <Icon /> {label}
                </Button>
            }
            title={`${label}?`}
            description={description}
            href={route('surveys.transition', surveyId)}
            method="post"
            data={{ status }}
            destructive={false}
            confirmLabel={label}
        />
    );
}
