import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, EyeOff, Lightbulb, MessageSquareQuote, Quote, TrendingUp, UserRound, UsersRound } from 'lucide-react';
import { SectionCell, SectionHeader, SectionLegend } from '@/components/analytics/course-cells';
import { QuestionTable } from '@/components/analytics/question-table';
import type { AnalyticsSurvey, CourseLecturerStat, QuestionStat, Scheme, SectionStat, Summary, TrendPoint } from '@/components/analytics/types';
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
    course: { id: number; code: string; name: string; semester: number; credits: number; study_program: string | null };
    survey: AnalyticsSurvey | null;
    surveys: Option[];
    backUrl: string;
    lecturer?: number | null;
    scheme?: Scheme | null;
    threshold?: number;
    minimum?: number;
    summary?: Summary;
    benchmarks?: { label: string; score: number | null }[];
    sections?: { code: string; title: string }[];
    lecturers?: CourseLecturerStat[];
    bySection?: SectionStat[];
    sectionBenchmark?: SectionStat[];
    byQuestion?: QuestionStat[];
    byClass?: { id: number; course: string; course_code: string; class_code: string; responses: number; sufficient: boolean; score: number | null; classification: Classification | null }[];
    trend?: TrendPoint[];
    comments?: { question: string; text: string }[];
}

export default function CourseDetail(props: Props) {
    const { course, survey, surveys, backUrl, lecturer = null, lecturers = [] } = props;
    const selected = lecturers.find((l) => l.id === lecturer);
    const visit = (changes: Record<string, string | number | undefined>) =>
        router.get(route('analytics.courses.show', course.id), { survey: survey?.id, lecturer: lecturer ?? undefined, ...changes }, { preserveScroll: true });

    return (
        <>
            <Head title={`${course.name} · Dosen × MK`} />
            <PageHeader
                title={course.name}
                description={`${course.code} · Semester ${course.semester} · ${course.credits} SKS · ${course.study_program ?? '—'}`}
                breadcrumbs={[{ label: 'Analitik', href: route('analytics.index') }, { label: 'Dosen × MK', href: backUrl }, { label: course.code }]}
                actions={
                    <Button variant="outline" asChild>
                        <Link href={backUrl}>
                            <ArrowLeft /> Kembali
                        </Link>
                    </Button>
                }
            />
            {survey && (
                <div className="mb-6 flex flex-col gap-2 rounded-2xl border bg-card p-3 sm:flex-row sm:items-center">
                    <span className="shrink-0 px-1 text-xs font-bold text-muted-foreground">Filter</span>
                    <SelectField label="Kegiatan Monev" value={String(survey.id)} onChange={(v) => visit({ survey: v, lecturer: undefined })} options={surveys} className="sm:w-96" />
                    <SelectField
                        label="Dosen pengampu"
                        value={lecturer ? String(lecturer) : 'all'}
                        onChange={(v) => visit({ lecturer: v === 'all' ? undefined : v })}
                        options={lecturers.map((l) => ({ value: l.id, label: l.name }))}
                        allLabel="Semua dosen pengampu"
                        className="sm:w-80"
                    />
                </div>
            )}
            {!survey ? (
                <Card>
                    <EmptyState title="Belum ada hasil Monev untuk mata kuliah ini" description="Hasil muncul setelah mahasiswa mengisi Monev pembelajaran pada kelas mata kuliah ini." />
                </Card>
            ) : (
                <CourseBody {...props} survey={survey} selected={selected} visit={visit} />
            )}
        </>
    );
}

function CourseBody({
    survey,
    selected,
    visit,
    scheme,
    threshold = 3,
    minimum = 5,
    summary,
    benchmarks = [],
    sections = [],
    lecturers = [],
    bySection = [],
    sectionBenchmark = [],
    byQuestion = [],
    byClass = [],
    trend = [],
    comments = [],
}: Props & { survey: AnalyticsSurvey; selected?: CourseLecturerStat; visit: (changes: Record<string, string | number | undefined>) => void }) {
    const max = scheme?.scale_max ?? survey.scale_max;
    const min = scheme?.scale_min ?? 0;

    const radar = bySection.map((section) => ({
        code: section.code,
        title: section.title,
        score: section.score,
        benchmark: sectionBenchmark.find((b) => b.code === section.code)?.score ?? null,
    }));
    const improvements = byQuestion.filter((q) => q.score !== null && q.score < threshold).sort((a, b) => (a.score ?? 0) - (b.score ?? 0));
    const strengths = [...byQuestion].filter((q) => q.score !== null).sort((a, b) => (b.score ?? 0) - (a.score ?? 0)).slice(0, 3);

    const comparison = (
        <Card className="gap-0 pb-0">
            <CardHeader className="pb-4">
                <CardTitle className="flex items-center gap-2">
                    <UsersRound className="size-4 text-primary" /> Perbandingan dosen pengampu
                </CardTitle>
                <CardDescription>Skor setiap dosen pada mata kuliah ini, per aspek. Klik nama dosen untuk memfokuskan halaman pada dosen tersebut.</CardDescription>
                <div className="mt-2">
                    <SectionLegend sections={sections} />
                </div>
            </CardHeader>
            <div className="overflow-x-auto border-t" tabIndex={0} role="region" aria-label="Perbandingan dosen pengampu (geser ke samping bila perlu)">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-muted/50 text-left text-xs text-muted-foreground">
                            <th scope="col" className="px-6 py-2.5 font-semibold">Dosen</th>
                            <th scope="col" className="px-2 py-2.5 font-semibold">Kelas</th>
                            <th scope="col" className="px-2 py-2.5 text-right font-semibold" title="Jumlah mahasiswa yang mengisi Monev">Respons</th>
                            <th scope="col" className="px-3 py-2.5 text-right font-semibold">Skor</th>
                            {sections.map((section) => (
                                <SectionHeader key={section.code} section={section} />
                            ))}
                            <th scope="col" className="px-4 py-2.5">
                                <span className="sr-only">Profil dosen</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {lecturers.map((lecturer) => (
                            <tr key={lecturer.id} className={cn('border-t', selected?.id === lecturer.id && 'bg-primary/10 font-semibold')}>
                                <td className="px-6 py-2">
                                    <button type="button" onClick={() => visit({ lecturer: lecturer.id })} className="text-left font-medium hover:text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none">
                                        {lecturer.name}
                                    </button>
                                </td>
                                <td className="px-2 text-xs text-muted-foreground">{lecturer.class_codes ?? '—'}</td>
                                <td className="px-2 text-right tabular">{lecturer.responses}</td>
                                <td className="px-3 text-right">
                                    {lecturer.sufficient ? (
                                        <b className={cn('tabular', lecturer.score !== null && lecturer.score < threshold && 'text-danger-foreground')}>{formatScore(lecturer.score)}</b>
                                    ) : (
                                        <InsufficientBadge responses={lecturer.responses} minimum={minimum} />
                                    )}
                                </td>
                                {sections.map((section) => (
                                    <SectionCell key={section.code} value={lecturer.sections[section.code]} min={min || 1} max={max} title={`${lecturer.name} · ${section.title}`} />
                                ))}
                                <td className="px-4 text-right">
                                    <Link href={route('analytics.lecturers.show', { lecturer: lecturer.id, survey: survey.id })} className="inline-flex items-center gap-1 text-xs font-semibold whitespace-nowrap text-primary hover:underline">
                                        <UserRound className="size-3.5" /> Semua MK dosen ini
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );

    if (summary?.suppressed) {
        return (
            <>
                <Alert className="border-warning/40 bg-warning-soft">
                    <EyeOff className="text-gold-foreground" />
                    <AlertTitle>Respons belum mencukupi</AlertTitle>
                    <AlertDescription>
                        Baru {summary.responses} respons masuk (minimum {minimum}). Demi menjaga anonimitas mahasiswa, hasil rinci {selected ? 'dosen ini pada mata kuliah ini' : 'mata kuliah ini'} belum
                        ditampilkan.
                    </AlertDescription>
                </Alert>
                {lecturers.length > 0 && <div className="mt-6">{comparison}</div>}
            </>
        );
    }

    return (
        <>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                <div className="flex flex-col justify-between gap-5 rounded-2xl bg-hero p-6 text-white">
                    <div>
                        <div className="text-sm text-white/85">
                            {selected ? `${selected.name} · ` : 'Rata-rata MK · '}
                            {survey.period}
                        </div>
                        <div className="mt-2 flex items-end gap-2">
                            <span className="text-6xl leading-none font-extrabold tracking-tight text-hero-accent">{formatScore(summary?.score)}</span>
                            <span className="mb-1.5 text-sm text-white/85">/ {max}</span>
                        </div>
                        <div className="mt-3 flex items-center gap-2">
                            <ClassBadge classification={summary?.classification} className="bg-white/10 bg-none text-white ring-white/20" />
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
                        <CardDescription>Dibandingkan rata-rata prodi pengelola mata kuliah.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <SectionRadar data={radar} max={max} min={min} seriesLabel={selected ? 'Skor dosen di MK ini' : 'Skor MK'} benchmarkLabel="Rata-rata prodi" />
                    </CardContent>
                </Card>
            </div>

            {lecturers.length > 0 && <div className="mt-6">{comparison}</div>}

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Lightbulb className="size-4 text-gold-foreground" /> Indikator yang perlu ditingkatkan
                        </CardTitle>
                        <CardDescription>
                            Butir dengan skor di bawah ambang {formatScore(threshold)}
                            {selected ? ` — ${selected.name}` : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {improvements.length === 0 && <p className="text-sm text-muted-foreground">Tidak ada butir di bawah ambang. Pertahankan!</p>}
                        {improvements.map((q) => (
                            <div key={q.id} className="flex items-start gap-3 rounded-xl bg-danger-soft/60 p-3">
                                <span className="font-mono text-xs font-bold text-danger-foreground">{q.code}</span>
                                <span className="flex-1 text-[13px]">{q.indicator ?? q.label}</span>
                                <span className="font-extrabold text-danger-foreground tabular">{formatScore(q.score)}</span>
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
                                <TableHead className="px-6">Kelas</TableHead>
                                <TableHead title="Jumlah mahasiswa yang mengisi Monev">Respons</TableHead>
                                <TableHead className="pr-6 text-right">Skor</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {byClass.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="px-6 font-medium">Kelas {row.class_code}</TableCell>
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
                            <TrendingUp className="size-4 text-primary" /> Perkembangan antarperiode
                        </CardTitle>
                        <CardDescription>{selected ? `Skor ${selected.name} pada mata kuliah ini` : 'Skor rata-rata mata kuliah ini'} di setiap kegiatan dengan instrumen yang sama.</CardDescription>
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
                    <CardDescription>Komentar terbuka dari mahasiswa (anonim).</CardDescription>
                </CardHeader>
                <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    {comments.length === 0 && <p className="text-sm text-muted-foreground">Belum ada komentar.</p>}
                    {comments.map((comment, index) => (
                        <figure key={index} className="relative rounded-xl bg-muted/50 py-3 pr-4 pl-10">
                            <Quote className="absolute top-3.5 left-3.5 size-4 fill-gold/30 text-gold-foreground" aria-hidden="true" />
                            <blockquote className="text-[13px] leading-relaxed">{comment.text}</blockquote>
                            <figcaption className="mt-1 text-[11px] text-muted-foreground">{comment.question}</figcaption>
                        </figure>
                    ))}
                </CardContent>
            </Card>
        </>
    );
}
