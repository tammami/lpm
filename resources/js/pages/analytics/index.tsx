import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, ChevronRight, ClipboardCheck, FileSpreadsheet, FileText, Info, MessageSquareQuote, Quote, TrendingUp, TriangleAlert, Users } from 'lucide-react';
import { useMemo } from 'react';
import { QuestionTable } from '@/components/analytics/question-table';
import type { AnalyticsSurvey, QuestionStat, Scheme, SectionStat, Summary, TrendPoint } from '@/components/analytics/types';
import { Heatmap } from '@/components/charts/heatmap';
import { ClassBadge } from '@/components/charts/score-badge';
import { ScoreBars } from '@/components/charts/score-bars';
import { TrendChart } from '@/components/charts/trend-chart';
import type { Classification } from '@/components/charts/score-badge';
import { EmptyState } from '@/components/empty-state';
import { Meter } from '@/components/meter';
import { PageHeader } from '@/components/page-header';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { formatNumber, formatPercent, formatScore } from '@/lib/format';
import type { Option } from '@/types';

interface Props {
    surveys: (Option & { mode: string })[];
    survey: AnalyticsSurvey | null;
    filters: { survey: number; study_program_id?: string };
    studyPrograms: Option[];
    scheme: Scheme | null;
    threshold: number;
    summary: Summary;
    benchmark: Summary | null;
    progress: { eligible: number; submitted: number; rate: number | null };
    byStudyProgram: { id: number; name: string; short: string; responses: number; score: number; classification: Classification | null }[];
    byLecturer: { id: number; name: string; study_program: string | null; responses: number; classes: number; sufficient: boolean; score: number | null; classification: Classification | null }[] | null;
    bySection: SectionStat[];
    byQuestion: QuestionStat[];
    heatmap: { sections: { code: string; title: string }[]; rows: { id: number; name: string; cells: Record<string, number | null> }[] };
    trend: TrendPoint[];
    comments: { question: string; text: string }[];
    minimum: number;
    can: { lecturer: boolean; export: boolean };
}

export default function AnalyticsIndex(props: Props) {
    const { survey, surveys } = props;

    if (!survey) {
        return (
            <>
                <PageHeader title="Analitik Mutu" breadcrumbs={[{ label: 'Analitik' }]} />
                <Card>
                    <EmptyState icon={BarChart3} title="Belum ada data untuk dianalisis" description="Hasil akan tersedia setelah kegiatan Monev dibuka dan responden mulai mengisi." />
                </Card>
            </>
        );
    }

    return <AnalyticsView {...props} survey={survey} surveys={surveys} />;
}

function AnalyticsView({ survey, surveys, filters, studyPrograms, scheme, threshold, summary, benchmark, progress, byStudyProgram, byLecturer, bySection, byQuestion, heatmap, trend, comments, minimum, can }: Props & { survey: AnalyticsSurvey }) {
    const canManageSettings = useCan()('settings.manage');
    const max = scheme?.scale_max ?? survey.scale_max;
    const min = scheme?.scale_min ?? 0;
    const lowQuestions = byQuestion.filter((q) => q.score !== null && q.score < threshold);
    const insufficient = byLecturer?.filter((l) => !l.sufficient).length ?? 0;
    const lowLecturers = byLecturer?.filter((l) => l.score !== null && l.score < threshold).length ?? 0;
    const scopeLabel = filters.study_program_id ? studyPrograms.find((p) => String(p.value) === String(filters.study_program_id))?.label : undefined;

    const navigate = (changes: Record<string, string | number | undefined>) =>
        router.get(route('analytics.index'), { survey: filters.survey, study_program_id: filters.study_program_id, ...changes }, { preserveScroll: true, preserveState: false });

    const lecturerItems = useMemo(
        () =>
            (byLecturer ?? []).map((l) => ({
                id: l.id,
                label: l.name,
                sublabel: `${l.classes} kelas · n=${l.responses}`,
                score: l.score,
                responses: l.responses,
                classification: l.classification,
                sufficient: l.sufficient,
            })),
        [byLecturer],
    );

    return (
        <>
            <Head title="Analitik Mutu" />
            <PageHeader
                title={scopeLabel ? `Analitik · ${scopeLabel}` : 'Analitik Mutu'}
                description={
                    <>
                        {survey.instrument} v{survey.instrument_version} · {survey.scoring_method}{' '}
                        <span className="font-mono text-[11px]">({survey.formula})</span>
                    </>
                }
                breadcrumbs={[{ label: 'Analitik', href: route('analytics.index') }, ...(scopeLabel ? [{ label: scopeLabel }] : [])]}
                actions={
                    can.export &&
                    route().has('reports.monev.pdf') && (
                        <>
                            <Button variant="outline" asChild>
                                <a href={route('reports.monev.excel', { survey: survey.id, study_program_id: filters.study_program_id })}>
                                    <FileSpreadsheet /> Excel
                                </a>
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={route('reports.monev.pdf', { survey: survey.id, study_program_id: filters.study_program_id })}>
                                    <FileText /> Laporan PDF
                                </a>
                            </Button>
                        </>
                    )
                }
            />

            <div className="mb-6 flex flex-col gap-2 rounded-2xl border bg-card p-3 sm:flex-row sm:items-center">
                <span className="px-1 text-xs font-bold tracking-wide text-muted-foreground uppercase">Filter</span>
                <SelectField value={String(filters.survey)} onChange={(v) => navigate({ survey: v, study_program_id: undefined })} options={surveys} className="sm:w-96" />
                <SelectField value={filters.study_program_id ?? 'all'} onChange={(v) => navigate({ study_program_id: v === 'all' ? undefined : v })} options={studyPrograms} allLabel="Seluruh cakupan saya" className="sm:w-72" />
                {survey.status === 'active' && (
                    <span className="inline-flex items-center gap-1.5 text-xs text-gold-foreground sm:ml-auto">
                        <Info className="size-3.5" /> Monev masih berjalan — angka dapat berubah.
                    </span>
                )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div className="flex flex-col gap-3 rounded-2xl border border-primary/20 bg-gradient-to-br from-secondary/80 to-card p-5">
                    <span className="text-[13px] font-semibold text-muted-foreground">Skor mutu rata-rata</span>
                    <div className="flex items-end gap-2">
                        <span className="text-5xl leading-none font-extrabold tracking-tight text-primary">{formatScore(summary.score)}</span>
                        <span className="mb-1 text-sm font-semibold text-muted-foreground">/ {max}</span>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <ClassBadge classification={summary.classification} />
                        {benchmark && <span className="text-xs text-muted-foreground">Institusi: {formatScore(benchmark.score)}</span>}
                    </div>
                </div>
                <StatTile label="Respons masuk" value={formatNumber(summary.responses)} icon={ClipboardCheck} tone="gold" hint={`dari ${formatNumber(progress.eligible)} responden eligible (seluruh cakupan)`} />
                <StatTile label="Response rate" value={formatPercent(progress.rate)} icon={Users} tone="info">
                    <Meter value={progress.rate} />
                </StatTile>
                <StatTile
                    label="Perlu perhatian"
                    value={formatNumber(lowQuestions.length)}
                    icon={TriangleAlert}
                    tone={lowQuestions.length ? 'danger' : 'neutral'}
                    hint={`butir di bawah ambang ${formatScore(threshold)}${byLecturer ? ` · ${lowLecturers} dosen` : ''}`}
                />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Skor per program studi</CardTitle>
                        <CardDescription>Klik prodi untuk drill-down.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {byStudyProgram.length ? (
                            <ScoreBars
                                items={byStudyProgram.map((p) => ({ id: p.id, label: p.name, sublabel: `n=${p.responses}`, score: p.score, responses: p.responses, classification: p.classification }))}
                                max={max}
                                min={min}
                                threshold={threshold}
                                onSelect={(item) => navigate({ study_program_id: String(item.id) })}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">Belum ada respons.</p>
                        )}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="size-4 text-primary" /> Tren antarperiode
                        </CardTitle>
                        <CardDescription>Skor rata-rata instrumen yang sama pada setiap periode (lintas versi).</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <TrendChart data={trend} min={min} max={max} threshold={threshold} />
                    </CardContent>
                </Card>
            </div>

            {heatmap.rows.length > 1 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Peta mutu: prodi × aspek</CardTitle>
                        <CardDescription>Warna makin gelap = skor makin tinggi. Temukan aspek lemah per prodi dalam sekali lihat.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Heatmap sections={heatmap.sections} rows={heatmap.rows} min={min || 1} max={max} onSelectRow={(id) => navigate({ study_program_id: String(id) })} />
                    </CardContent>
                </Card>
            )}

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Analisis per indikator</CardTitle>
                    <CardDescription>
                        Skor rata-rata dan sebaran jawaban setiap butir. {lowQuestions.length > 0 && <b className="text-destructive">{lowQuestions.length} butir di bawah ambang.</b>}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        {bySection.map((section) => (
                            <div key={section.id} className="rounded-xl border p-3">
                                <div className="flex items-center justify-between">
                                    <span className="flex size-7 items-center justify-center rounded-lg bg-primary text-xs font-extrabold text-gold">{section.code}</span>
                                    <span className="text-lg font-extrabold tabular">{formatScore(section.score)}</span>
                                </div>
                                <div className="mt-2 truncate text-xs font-semibold text-muted-foreground">{section.title}</div>
                            </div>
                        ))}
                    </div>
                    <QuestionTable questions={byQuestion} threshold={threshold} />
                </CardContent>
            </Card>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                {byLecturer && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Skor per dosen</CardTitle>
                            <CardDescription>
                                Hasil dosen dengan kurang dari {minimum} respons disembunyikan untuk menjaga anonimitas{insufficient ? ` (${insufficient} dosen)` : ''}.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="max-h-[640px] overflow-y-auto pr-2">
                            <ScoreBars items={lecturerItems} max={max} min={min} threshold={threshold} minimum={minimum} onSelect={(item) => router.visit(route('analytics.lecturers.show', { lecturer: item.id, survey: survey.id }))} />
                        </CardContent>
                    </Card>
                )}
                <Card className={byLecturer ? '' : 'xl:col-span-2'}>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <MessageSquareQuote className="size-4 text-primary" /> Suara mahasiswa
                        </CardTitle>
                        <CardDescription>Cuplikan acak komentar terbuka (anonim).</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2.5">
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
            </div>

            {byLecturer && can.lecturer && (
                <p className="mt-6 flex items-center gap-1 text-xs text-muted-foreground">
                    <ChevronRight className="size-3" /> Klik nama dosen untuk melihat detail per butir, per kelas, tren, dan komentar.
                </p>
            )}
            <p className="mt-2 text-xs text-muted-foreground">
                Klasifikasi: {scheme?.classes.map((c) => `${c.label} (${c.min_score}–${c.max_score})`).join(' · ')}. Ubah di{' '}
                {canManageSettings ? <Link href={route('settings.scales')} className="text-primary hover:underline">Klasifikasi & Skala</Link> : 'pengaturan'}.
            </p>
        </>
    );
}
