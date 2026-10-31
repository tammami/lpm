import { Head, Link, router } from '@inertiajs/react';
import { BookOpenCheck, ChevronRight, EyeOff, FileSpreadsheet, Info, Sparkles, TriangleAlert, UsersRound } from 'lucide-react';
import { Fragment, useMemo, useState } from 'react';
import { ScoreDelta, SectionCell, SectionHeader, SectionLegend } from '@/components/analytics/course-cells';
import type { AnalyticsSurvey, CourseLecturerStat, CourseStat, Scheme, Summary } from '@/components/analytics/types';
import { ClassBadge, InsufficientBadge } from '@/components/charts/score-badge';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { SearchInput } from '@/components/search-input';
import { SelectField } from '@/components/select-field';
import { StatTile } from '@/components/stat-tile';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatNumber, formatScore } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Option } from '@/types';

interface Props {
    surveys: Option[];
    survey: AnalyticsSurvey | null;
    filters: { survey: number; study_program_id?: string };
    studyPrograms: Option[];
    scheme: Scheme | null;
    threshold: number;
    minimum: number;
    summary: Summary;
    sections: { code: string; title: string }[];
    courses: CourseStat[];
    can: { export: boolean };
}

type View = 'all' | 'attention' | 'insufficient';
type Sort = 'course' | 'low' | 'high' | 'gap';

const sortOptions: Option[] = [
    { value: 'course', label: 'Semester & kode MK' },
    { value: 'low', label: 'Skor terendah dulu' },
    { value: 'high', label: 'Skor tertinggi dulu' },
    { value: 'gap', label: 'Kesenjangan antardosen' },
];

/** Selisih skor tertinggi dan terendah antardosen pengampu satu MK (hanya yang respons-nya cukup). */
function gapOf(course: CourseStat) {
    const scores = course.lecturers.filter((l) => l.score !== null).map((l) => l.score as number);
    return scores.length > 1 ? Math.max(...scores) - Math.min(...scores) : null;
}

export default function CourseAnalytics(props: Props) {
    if (!props.survey) {
        return (
            <>
                <Head title="Analisis Dosen × MK" />
                <PageHeader title="Analisis Dosen × Mata Kuliah" breadcrumbs={[{ label: 'Analitik', href: route('analytics.index') }, { label: 'Dosen × MK' }]} />
                <Card>
                    <EmptyState icon={BookOpenCheck} title="Belum ada Monev pembelajaran untuk dianalisis" description="Hasil muncul setelah kegiatan Monev pembelajaran dibuka dan mahasiswa mulai mengisi." />
                </Card>
            </>
        );
    }

    return <CourseAnalyticsView {...props} survey={props.survey} />;
}

function CourseAnalyticsView({ surveys, survey, filters, studyPrograms, scheme, threshold, minimum, summary, sections, courses, can }: Props & { survey: AnalyticsSurvey }) {
    const [search, setSearch] = useState('');
    const [view, setView] = useState<View>('all');
    const [sort, setSort] = useState<Sort>('course');
    const max = scheme?.scale_max ?? survey.scale_max;
    const min = scheme?.scale_min ?? 0;
    const heatMin = min || 1;
    const scopeLabel = filters.study_program_id ? studyPrograms.find((p) => String(p.value) === String(filters.study_program_id))?.label : undefined;

    const navigate = (changes: Record<string, string | number | undefined>) =>
        router.get(route('analytics.courses.index'), { survey: filters.survey, study_program_id: filters.study_program_id, ...changes }, { preserveScroll: true, preserveState: false });

    const detailUrl = (course: CourseStat, lecturer?: CourseLecturerStat) => route('analytics.courses.show', { course: course.id, survey: survey.id, lecturer: lecturer?.id });

    const pairs = useMemo(() => courses.flatMap((course) => course.lecturers.map((lecturer) => ({ course, lecturer }))), [courses]);
    const lowPairs = pairs.filter((p) => p.lecturer.score !== null && p.lecturer.score < threshold);
    const lowCourses = courses.filter((c) => c.score !== null && c.score < threshold);
    const hiddenPairs = pairs.filter((p) => !p.lecturer.sufficient);
    const ranked = pairs.filter((p) => p.lecturer.score !== null).sort((a, b) => (a.lecturer.score ?? 0) - (b.lecturer.score ?? 0));
    // Dua daftar tidak boleh memuat pasangan yang sama saat datanya sedikit.
    const weakest = ranked.slice(0, Math.min(5, Math.floor(ranked.length / 2)));
    const strongest = ranked.slice(-Math.min(5, Math.ceil(ranked.length / 2))).reverse();

    const visible = useMemo(() => {
        const term = search.trim().toLowerCase();

        const rows = courses
            .map((course) => {
                const courseMatch = !term || `${course.code} ${course.name}`.toLowerCase().includes(term);
                // Rentang & selisih tetap dihitung dari semua dosen pengampu; `shown` hanya yang cocok dengan pencarian.
                const shown = courseMatch ? course.lecturers : course.lecturers.filter((l) => l.name.toLowerCase().includes(term));
                return { ...course, shown };
            })
            .filter((course) => !term || course.shown.length > 0)
            .filter((course) => {
                if (view === 'attention') return (course.score !== null && course.score < threshold) || course.lecturers.some((l) => l.score !== null && l.score < threshold);
                if (view === 'insufficient') return course.lecturers.some((l) => !l.sufficient);
                return true;
            });

        const scoreKey = (course: CourseStat) => course.score ?? (sort === 'low' ? Infinity : -Infinity);

        if (sort === 'low') return [...rows].sort((a, b) => scoreKey(a) - scoreKey(b));
        if (sort === 'high') return [...rows].sort((a, b) => scoreKey(b) - scoreKey(a));
        if (sort === 'gap') return [...rows].sort((a, b) => (gapOf(b) ?? -1) - (gapOf(a) ?? -1));
        return rows;
    }, [courses, search, view, sort, threshold]);

    return (
        <>
            <Head title="Analisis Dosen × MK" />
            <PageHeader
                title={scopeLabel ? `Dosen × MK · ${scopeLabel}` : 'Analisis Dosen × Mata Kuliah'}
                description="Pantau skor Monev setiap dosen pada setiap mata kuliah yang diampu, per aspek, dan perubahannya dari periode sebelumnya."
                breadcrumbs={[{ label: 'Analitik', href: route('analytics.index') }, { label: 'Dosen × MK' }]}
                actions={
                    can.export && (
                        <Button variant="outline" asChild>
                            <a href={route('analytics.courses.export', { survey: survey.id, study_program_id: filters.study_program_id })}>
                                <FileSpreadsheet /> Unduh Excel
                            </a>
                        </Button>
                    )
                }
            />

            <div className="mb-6 flex flex-col gap-2 rounded-2xl border bg-card p-3 sm:flex-row sm:flex-wrap sm:items-center">
                <span className="px-1 text-xs font-bold text-muted-foreground">Filter</span>
                <SelectField label="Kegiatan Monev" value={String(filters.survey)} onChange={(v) => navigate({ survey: v })} options={surveys} className="sm:w-96" />
                <SelectField value={filters.study_program_id ?? 'all'} onChange={(v) => navigate({ study_program_id: v === 'all' ? undefined : v })} options={studyPrograms} allLabel="Seluruh cakupan saya" className="sm:w-72" />
                {survey.status === 'active' && (
                    <span className="inline-flex items-center gap-1.5 text-xs text-gold-foreground sm:ml-auto">
                        <Info className="size-3.5" /> Monev masih berjalan — angka dapat berubah.
                    </span>
                )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div className="flex flex-col gap-3 rounded-2xl border border-primary/20 bg-gradient-to-br from-secondary/80 to-card p-5">
                    <span className="text-[13px] font-semibold text-muted-foreground">Skor rata-rata pembelajaran</span>
                    <div className="flex items-end gap-2">
                        <span className="text-5xl leading-none font-extrabold tracking-tight text-primary">{formatScore(summary.score)}</span>
                        <span className="mb-1 text-sm font-semibold text-muted-foreground">/ {max}</span>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <ClassBadge classification={summary.classification} />
                        <span className="text-xs text-muted-foreground">{formatNumber(summary.responses)} respons</span>
                    </div>
                </div>
                <StatTile label="Mata kuliah dievaluasi" value={formatNumber(courses.length)} icon={BookOpenCheck} hint={`${formatNumber(pairs.length)} pasangan dosen–MK`} />
                <StatTile
                    label="Perlu perhatian"
                    value={formatNumber(lowPairs.length)}
                    icon={TriangleAlert}
                    tone={lowPairs.length ? 'danger' : 'neutral'}
                    hint={`dosen–MK di bawah ambang ${formatScore(threshold)} · ${lowCourses.length} MK`}
                />
                <StatTile label="Respons belum cukup" value={formatNumber(hiddenPairs.length)} icon={EyeOff} tone="gold" hint={`pasangan dengan respons < ${minimum}, skornya disembunyikan`} />
            </div>

            {ranked.length > 1 && (
                <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <HighlightCard
                        title="Skor terendah"
                        description={`${weakest.length} pasangan dosen–MK yang paling perlu pendampingan.`}
                        icon={TriangleAlert}
                        tone="danger"
                        items={weakest}
                        threshold={threshold}
                        detailUrl={detailUrl}
                    />
                    <HighlightCard title="Skor tertinggi" description={`${strongest.length} pasangan dosen–MK sebagai contoh praktik baik.`} icon={Sparkles} tone="success" items={strongest} threshold={threshold} detailUrl={detailUrl} />
                </div>
            )}

            <Card className="mt-6 gap-0 pb-0">
                <CardHeader className="pb-4">
                    <CardTitle className="flex items-center gap-2">
                        <UsersRound className="size-4 text-primary" /> Skor per mata kuliah dan dosen pengampu
                    </CardTitle>
                    <CardDescription>
                        Baris tebal = rata-rata MK; di bawahnya setiap dosen pengampu. ▲▼ = perubahan dari periode terakhir MK atau dosen tersebut dievaluasi
                        (arahkan kursor untuk melihat periodenya).
                    </CardDescription>
                    <div className="mt-2">
                        <SectionLegend sections={sections} />
                    </div>
                    <div className="mt-3 flex flex-col gap-2 lg:flex-row lg:items-center">
                        <Tabs value={view} onValueChange={(v) => setView(v as View)}>
                            <TabsList>
                                <TabsTrigger value="all">Semua ({courses.length})</TabsTrigger>
                                <TabsTrigger value="attention">Perlu perhatian</TabsTrigger>
                                <TabsTrigger value="insufficient">Respons belum cukup</TabsTrigger>
                            </TabsList>
                        </Tabs>
                        <div className="flex flex-col gap-2 sm:flex-row lg:ml-auto">
                            <SearchInput value={search} onChange={setSearch} placeholder="Cari MK atau dosen…" />
                            <SelectField label="Urutan" value={sort} onChange={(v) => setSort(v as Sort)} options={sortOptions} className="shrink-0 sm:w-56" />
                        </div>
                    </div>
                </CardHeader>
                {visible.length === 0 ? (
                    <CardContent className="pb-6">
                        <EmptyState icon={BookOpenCheck} title="Tidak ada mata kuliah yang cocok" description="Ubah kata kunci pencarian atau pilihan tampilan." />
                    </CardContent>
                ) : (
                    <div className="overflow-x-auto border-t" tabIndex={0} role="region" aria-label="Tabel skor dosen per mata kuliah (geser ke samping bila perlu)">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="bg-muted/50 text-left text-xs text-muted-foreground">
                                    <th scope="col" className="px-6 py-2.5 font-semibold">Mata kuliah / dosen</th>
                                    <th scope="col" className="px-2 py-2.5 font-semibold">Kelas</th>
                                    <th scope="col" className="px-2 py-2.5 text-right font-semibold" title="Jumlah mahasiswa yang mengisi Monev">Respons</th>
                                    <th scope="col" className="px-3 py-2.5 text-right font-semibold">Skor</th>
                                    {sections.map((section) => (
                                        <SectionHeader key={section.code} section={section} />
                                    ))}
                                    <th scope="col" className="px-3 py-2.5 text-right font-semibold" title="Selisih skor dosen dari rata-rata MK; pada baris MK: rentang skor antardosen">
                                        Selisih / rentang
                                    </th>
                                    <th scope="col" className="w-10 pr-4">
                                        <span className="sr-only">Detail</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((course) => {
                                    const gap = gapOf(course);
                                    return (
                                        <Fragment key={course.id}>
                                            <tr className="border-t border-primary/25 bg-primary/10">
                                                <td className="px-6 py-2.5">
                                                    <Link href={detailUrl(course)} className="font-bold hover:text-primary hover:underline">
                                                        {course.name}
                                                    </Link>
                                                    <div className="text-xs text-muted-foreground">
                                                        {course.code} · Smt {course.semester} · {course.credits} SKS{course.study_program ? ` · ${course.study_program}` : ''}
                                                    </div>
                                                </td>
                                                <td className="px-2 text-xs whitespace-nowrap text-muted-foreground">{course.classes} kelas</td>
                                                <td className="px-2 text-right tabular">{course.responses}</td>
                                                <td className="px-3 text-right">
                                                    <ScoreValue row={course} threshold={threshold} minimum={minimum} bold />
                                                </td>
                                                {sections.map((section) => (
                                                    <SectionCell key={section.code} value={course.sections[section.code]} min={heatMin} max={max} title={`${course.name} · ${section.title}`} />
                                                ))}
                                                <td className="px-3 text-right text-xs text-muted-foreground tabular">{gap !== null ? `rentang ${formatScore(gap)}` : '—'}</td>
                                                <td className="pr-4">
                                                    <DetailLink href={detailUrl(course)} label={`Detail ${course.name}`} />
                                                </td>
                                            </tr>
                                            {course.shown.map((lecturer) => {
                                                const diff = lecturer.score !== null && course.score !== null && course.lecturers.length > 1 ? lecturer.score - course.score : null;
                                                return (
                                                    <tr key={lecturer.id} className="border-t border-dashed hover:bg-muted/40">
                                                        <td className="py-2 pr-2 pl-10">
                                                            <Link href={detailUrl(course, lecturer)} className="font-medium hover:text-primary hover:underline">
                                                                {lecturer.name}
                                                            </Link>
                                                            {lecturer.nidn && <div className="text-[11px] text-muted-foreground">NIDN/NUPTK {lecturer.nidn}</div>}
                                                        </td>
                                                        <td className="px-2 text-xs whitespace-nowrap text-muted-foreground">{lecturer.class_codes ?? '—'}</td>
                                                        <td className="px-2 text-right tabular">{lecturer.responses}</td>
                                                        <td className="px-3 text-right">
                                                            <ScoreValue row={lecturer} threshold={threshold} minimum={minimum} />
                                                        </td>
                                                        {sections.map((section) => (
                                                            <SectionCell key={section.code} value={lecturer.sections[section.code]} min={heatMin} max={max} title={`${lecturer.name} · ${section.title}`} />
                                                        ))}
                                                        <td className={cn('px-3 text-right text-xs font-semibold tabular', diff === null ? 'text-muted-foreground' : diff < 0 ? 'text-danger-foreground' : 'text-success-foreground')}>
                                                            {diff === null ? '—' : `${diff > 0 ? '+' : diff < 0 ? '−' : ''}${formatScore(Math.abs(diff))}`}
                                                        </td>
                                                        <td className="pr-4">
                                                            <DetailLink href={detailUrl(course, lecturer)} label={`Detail ${lecturer.name} di ${course.name}`} />
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            <p className="mt-4 text-xs text-muted-foreground">
                Skor dosen–MK dengan kurang dari {minimum} respons disembunyikan untuk menjaga anonimitas mahasiswa.
            </p>
        </>
    );
}

function ScoreValue({ row, threshold, minimum, bold }: { row: CourseStat | CourseLecturerStat; threshold: number; minimum: number; bold?: boolean }) {
    if (!row.sufficient) return <InsufficientBadge responses={row.responses} minimum={minimum} />;

    return (
        <div className="flex flex-col items-end gap-0.5">
            <div className="flex items-center gap-2">
                <ClassBadge classification={row.classification} className="hidden xl:inline-flex" />
                <span className={cn('tabular', bold ? 'font-extrabold' : 'font-bold', row.score !== null && row.score < threshold && 'text-danger-foreground')}>{formatScore(row.score)}</span>
            </div>
            <ScoreDelta score={row.score} previous={row.previous_score} label={row.previous_label ?? undefined} />
        </div>
    );
}

function DetailLink({ href, label }: { href: string; label: string }) {
    return (
        <Link href={href} aria-label={label} title="Lihat detail" className="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-white/70 hover:text-primary focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none">
            <ChevronRight className="size-4" />
        </Link>
    );
}

function HighlightCard({
    title,
    description,
    icon: Icon,
    tone,
    items,
    threshold,
    detailUrl,
}: {
    title: string;
    description: string;
    icon: typeof Sparkles;
    tone: 'danger' | 'success';
    items: { course: CourseStat; lecturer: CourseLecturerStat }[];
    threshold: number;
    detailUrl: (course: CourseStat, lecturer?: CourseLecturerStat) => string;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Icon className={cn('size-4', tone === 'danger' ? 'text-danger-foreground' : 'text-gold-foreground')} /> {title}
                </CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-2">
                {items.map(({ course, lecturer }) => (
                    <Link
                        key={`${course.id}-${lecturer.id}`}
                        href={detailUrl(course, lecturer)}
                        className={cn('flex items-center gap-3 rounded-xl p-3 transition hover:ring-2 hover:ring-primary/20', tone === 'danger' ? 'bg-danger-soft/60' : 'bg-success-soft/70')}
                    >
                        <div className="min-w-0 flex-1">
                            <div className="truncate text-[13px] font-semibold">{lecturer.name}</div>
                            <div className="truncate text-xs text-muted-foreground">
                                {course.code} · {course.name}
                            </div>
                        </div>
                        <span className={cn('font-extrabold tabular', lecturer.score !== null && lecturer.score < threshold ? 'text-danger-foreground' : 'text-primary')}>{formatScore(lecturer.score)}</span>
                    </Link>
                ))}
            </CardContent>
        </Card>
    );
}
