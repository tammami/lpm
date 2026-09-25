import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, EyeOff, FileText, Lightbulb, MessageSquareQuote, Quote, ShieldAlert, TrendingUp } from 'lucide-react';
import { QuestionTable } from '@/components/analytics/question-table';
import type { AnalyticsSurvey, QuestionStat, Scheme, SectionStat, Summary, TrendPoint } from '@/components/analytics/types';
import { ClassBadge, type Classification, InsufficientBadge } from '@/components/charts/score-badge';
import { SectionRadar } from '@/components/charts/section-radar';
import { TrendChart } from '@/components/charts/trend-chart';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatScore } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

interface Props {
    lecturer: { id: number; name: string; nidn: string | null; academic_rank: string | null; study_program: string | null };
    survey: AnalyticsSurvey | null;
    surveys: Option[];
    scheme?: Scheme | null;
    threshold?: number;
    minimum?: number;
    summary?: Summary;
    benchmarks?: { label: string; score: number | null }[];
    bySection?: SectionStat[];
    sectionBenchmark?: SectionStat[];
    byQuestion?: QuestionStat[];
    byClass?: { id: number; course: string; course_code: string; class_code: string; responses: number; sufficient: boolean; score: number | null; classification: Classification | null }[];
    trend?: TrendPoint[];
    comments?: { question: string; text: string }[];
    selfView?: boolean;
    backUrl?: string;
}

export default function LecturerAnalytics(props: Props) {
    const { lecturer, survey, surveys, selfView } = props;
    const title = selfView ? 'Hasil Evaluasi Saya' : lecturer.name;
    const switchSurvey = (value: string) =>
        router.get(selfView ? route('my-evaluation.index') : route('analytics.lecturers.show', lecturer.id), { survey: value }, { preserveScroll: true });

    return (
        <>
            <Head title={title} />
            <PageHeader
                title={title}
                description={selfView ? 'Umpan balik mahasiswa atas pembelajaran Anda. Seluruh jawaban anonim.' : `${lecturer.academic_rank ?? 'Dosen'} · ${lecturer.study_program ?? '—'} · NIDN ${lecturer.nidn ?? '—'}`}
                breadcrumbs={selfView ? [{ label: 'Hasil Evaluasi Saya' }] : [{ label: 'Analitik', href: props.backUrl }, { label: lecturer.name }]}
                actions={
                    <>
                        {survey && (
                            <Button variant="outline" asChild>
                                <a href={route('reports.lecturer.pdf', { lecturer: lecturer.id, survey: survey.id })}>
                                    <FileText /> Unduh PDF
                                </a>
                            </Button>
                        )}
                        {!selfView && (
                            <Button variant="outline" asChild>
                                <Link href={props.backUrl ?? route('analytics.index')}>
                                    <ArrowLeft /> Kembali
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />
            {surveys.length > 0 && (
                <div className="mb-6 flex items-center gap-2 rounded-2xl border bg-card p-3">
                    <span className="px-1 text-xs font-bold tracking-wide text-muted-foreground uppercase">Kegiatan</span>
                    <SelectField value={survey ? String(survey.id) : ''} onChange={switchSurvey} options={surveys} className="sm:w-96" />
                </div>
            )}
            {!survey ? (
                <Card>
                    <EmptyState title="Belum ada hasil evaluasi" description="Hasil muncul setelah mahasiswa mengisi Monev pada kelas yang Anda ampu." />
                </Card>
            ) : (
                <LecturerBody {...props} survey={survey} />
            )}
        </>
    );
}

function LecturerBody({ survey, scheme, threshold = 3, minimum = 5, summary, benchmarks = [], bySection = [], sectionBenchmark = [], byQuestion = [], byClass = [], trend = [], comments = [], selfView }: Props & { survey: AnalyticsSurvey }) {
    const max = scheme?.scale_max ?? survey.scale_max;
    const min = scheme?.scale_min ?? 0;

    if (summary?.suppressed) {
        return (
            <Alert className="border-warning/40 bg-warning-soft">
                <EyeOff className="text-gold-foreground" />
                <AlertTitle>Respons belum mencukupi</AlertTitle>
                <AlertDescription>
                    Baru {summary.responses} respons masuk (minimum {minimum}). Demi menjaga anonimitas mahasiswa, hasil rinci belum ditampilkan untuk kegiatan ini.
                </AlertDescription>
            </Alert>
        );
    }

    const radar = bySection.map((section) => ({
        code: section.code,
        title: section.title,
        score: section.score,
        benchmark: sectionBenchmark.find((b) => b.code === section.code)?.score ?? null,
    }));
    const improvements = byQuestion.filter((q) => q.score !== null && q.score < threshold).sort((a, b) => (a.score ?? 0) - (b.score ?? 0));
    const strengths = [...byQuestion].filter((q) => q.score !== null).sort((a, b) => (b.score ?? 0) - (a.score ?? 0)).slice(0, 3);

    return (
        <>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                <div className="flex flex-col justify-between gap-5 rounded-2xl bg-hero p-6 text-white">
                    <div>
                        <div className="text-sm text-white/85">Skor evaluasi · {survey.period}</div>
                        <div className="mt-2 flex items-end gap-2">
                            <span className="text-6xl leading-none font-extrabold tracking-tight text-hero-accent">{formatScore(summary?.score)}</span>
                            <span className="mb-1.5 text-sm text-white/85">/ {max}</span>
                        </div>
                        <div className="mt-3 flex items-center gap-2">
                            <ClassBadge classification={summary?.classification} className="bg-white/10 text-white ring-white/20" />
                            <span className="text-xs text-white/85">{summary?.responses} respons</span>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3 border-t border-white/10 pt-4">
                        {benchmarks.map((benchmark) => {
                            const diff = summary?.score !== null && summary?.score !== undefined && benchmark.score !== null ? summary.score - benchmark.score : null;
                            return (
                                <div key={benchmark.label}>
                                    <div className="text-[11px] text-white/85">{benchmark.label}</div>
                                    <div className="text-lg font-bold">{formatScore(benchmark.score)}</div>
                                    {diff !== null && (
                                        <div className={cn('text-[11px] font-semibold', diff >= 0 ? 'text-on-dark-up' : 'text-on-dark-down')}>
                                            {diff >= 0 ? '▲' : '▼'} {formatScore(Math.abs(diff))} {diff >= 0 ? 'di atas' : 'di bawah'}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Profil per aspek</CardTitle>
                        <CardDescription>Dibandingkan rata-rata prodi homebase.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SectionRadar data={radar} max={max} min={min} seriesLabel={selfView ? 'Skor Anda' : 'Skor dosen'} benchmarkLabel="Rata-rata prodi" />
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Lightbulb className="size-4 text-gold-foreground" /> Indikator yang perlu ditingkatkan
                        </CardTitle>
                        <CardDescription>Butir dengan skor di bawah ambang {formatScore(threshold)}.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {improvements.length === 0 && <p className="text-sm text-muted-foreground">Tidak ada butir di bawah ambang. Pertahankan!</p>}
                        {improvements.map((q) => (
                            <div key={q.id} className="flex items-start gap-3 rounded-xl bg-danger-soft/60 p-3">
                                <span className="font-mono text-xs font-bold text-destructive">{q.code}</span>
                                <span className="flex-1 text-[13px]">{q.indicator ?? q.label}</span>
                                <span className="font-extrabold text-destructive tabular">{formatScore(q.score)}</span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Kekuatan utama</CardTitle>
                        <CardDescription>Tiga butir dengan skor tertinggi.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {strengths.map((q) => (
                            <div key={q.id} className="flex items-start gap-3 rounded-xl bg-success-soft/70 p-3">
                                <span className="font-mono text-xs font-bold text-primary">{q.code}</span>
                                <span className="flex-1 text-[13px]">{q.indicator ?? q.label}</span>
                                <span className="font-extrabold text-primary tabular">{formatScore(q.score)}</span>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Rincian per butir</CardTitle>
                </CardHeader>
                <CardContent>
                    <QuestionTable questions={byQuestion} threshold={threshold} />
                </CardContent>
            </Card>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <Card className="gap-0 overflow-hidden pb-0">
                    <CardHeader className="pb-4">
                        <CardTitle>Per kelas</CardTitle>
                    </CardHeader>
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/50">
                                <TableHead className="px-6">Mata kuliah</TableHead>
                                <TableHead>n</TableHead>
                                <TableHead className="pr-6 text-right">Skor</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {byClass.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="px-6">
                                        <div className="font-medium">{row.course}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {row.course_code} · Kelas {row.class_code}
                                        </div>
                                    </TableCell>
                                    <TableCell className="tabular">{row.responses}</TableCell>
                                    <TableCell className="pr-6 text-right">
                                        {row.sufficient ? (
                                            <div className="flex items-center justify-end gap-2">
                                                <ClassBadge classification={row.classification} />
                                                <b className="tabular">{formatScore(row.score)}</b>
                                            </div>
                                        ) : (
                                            <InsufficientBadge responses={row.responses} minimum={minimum} />
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="size-4 text-primary" /> Perkembangan
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <TrendChart data={trend} min={min} max={max} threshold={threshold} height={220} />
                    </CardContent>
                </Card>
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MessageSquareQuote className="size-4 text-primary" /> Komentar mahasiswa
                    </CardTitle>
                    <CardDescription>
                        {selfView ? 'Hanya komentar yang diizinkan untuk dosen. Identitas penulis tidak tersimpan.' : 'Komentar terbuka dari mahasiswa (anonim).'}
                    </CardDescription>
                </CardHeader>
                <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    {comments.length === 0 && <p className="text-sm text-muted-foreground">Belum ada komentar.</p>}
                    {comments.map((comment, index) => (
                        <figure key={index} className="relative rounded-xl bg-muted/50 py-3 pr-4 pl-10">
                                <Quote className="absolute top-3.5 left-3.5 size-4 fill-gold/30 text-gold" aria-hidden="true" />
                            <blockquote className="text-[13px] leading-relaxed">{comment.text}</blockquote>
                            <figcaption className="mt-1 text-[11px] text-muted-foreground">{comment.question}</figcaption>
                        </figure>
                    ))}
                </CardContent>
            </Card>
            {selfView && (
                <p className="mt-6 flex items-center gap-2 text-xs text-muted-foreground">
                    <ShieldAlert className="size-3.5" /> Hasil kelas dengan respons kurang dari {minimum} tidak ditampilkan untuk menjaga kerahasiaan mahasiswa.
                </p>
            )}
        </>
    );
}
