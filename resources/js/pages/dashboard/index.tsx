import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlarmClock,
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    BadgeCheck,
    BookOpenCheck,
    CalendarClock,
    CheckCircle2,
    ClipboardList,
    FileSpreadsheet,
    FolderCheck,
    GraduationCap,
    Lightbulb,
    ListTodo,
    Plus,
    ShieldCheck,
    Target,
    TrendingUp,
    UserRoundSearch,
    Users,
} from 'lucide-react';
import type { AnalyticsSurvey, QuestionStat, Scheme, Summary, TrendPoint } from '@/components/analytics/types';
import { type Classification, ClassBadge } from '@/components/charts/score-badge';
import { ScoreBars } from '@/components/charts/score-bars';
import { TrendChart } from '@/components/charts/trend-chart';
import { EmptyState } from '@/components/empty-state';
import { Meter } from '@/components/meter';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate, formatNumber, formatPercent, formatScore } from '@/lib/format';
import { daysLeft } from '@/lib/survey';
import { cn } from '@/lib/utils';

interface Quality {
    survey: AnalyticsSurvey;
    score: number | null;
    classification: Classification | null;
    responses: number;
    previous_score: number | null;
    previous_label: string | null;
    scheme: Scheme | null;
    byStudyProgram: { id: number; name: string; responses: number; score: number; classification: Classification | null }[];
    lowQuestions: QuestionStat[];
    lecturersBelow: number;
    lecturersTotal: number;
    trend: TrendPoint[];
}

interface ExecutiveProps {
    variant: 'executive';
    greeting: string;
    scopeLabel: string;
    threshold: number;
    quality: Quality | null;
    activeSurveys: {
        id: number;
        title: string;
        ends_at: string;
        progress: { eligible: number; submitted: number; rate: number | null };
        byStudyProgram: { study_program_id: number; name: string; eligible: number; submitted: number; rate: number | null }[];
    }[];
    accreditation: { id: number; name: string; body: string | null; status: string | null; valid_until: string | null; days_left: number | null; period_id: number | null; readiness: number | null }[];
    accreditationWarningDays: number;
    counts: { students: number; lecturers: number; programs: number };
    can: { manageSurveys: boolean; manageInstruments: boolean; import: boolean; analytics: boolean; accreditation: boolean };
    cycle: QualityCycle;
}

interface QualityCycle {
    findings: { open: number; overdue: number; closed: number; audits_running: number } | null;
    improvement: { open: number; closed: number; overdue_plans: number; avg_progress: number } | null;
    evidence: { total: number; verified: number; pending: number; expiring: number } | null;
    attention: { type: string; code: string; title: string; pic: string | null; due_date: string | null; url: string }[];
}

interface LecturerProps {
    variant: 'lecturer';
    greeting: string;
    scopeLabel: string;
    threshold: number;
    lecturer: { name: string; study_program: string | null; classes: number } | null;
    tasks: { type: string; title: string; status: string; due_date: string | null; overdue: boolean; url: string }[];
    evaluation: { survey: AnalyticsSurvey; summary: Summary; trend: TrendPoint[]; improvements: QuestionStat[]; scheme: Scheme | null } | null;
}

export default function Dashboard(props: ExecutiveProps | LecturerProps) {
    return props.variant === 'lecturer' ? <LecturerDashboard {...props} /> : <ExecutiveDashboard {...props} />;
}

function Hero({ greeting, scopeLabel, children }: { greeting: string; scopeLabel: string; children?: React.ReactNode }) {
    const { auth, activePeriod } = usePage().props;
    const today = new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

    return (
        <section className="relative mb-6 overflow-hidden rounded-3xl bg-hero px-6 py-6 text-white sm:px-8">
            <div className="bg-pattern-star absolute inset-0 opacity-40" />
            <div className="absolute -top-24 right-10 size-72 rounded-full bg-primary/40 blur-3xl" />
            <div className="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 className="text-2xl font-extrabold tracking-tight sm:text-3xl">
                        {greeting}, {auth.user?.name.split(' ')[0]}
                    </h1>
                    <p className="mt-1.5 text-sm text-white/85">
                        {auth.user?.role_label} · {scopeLabel}
                        {activePeriod && <> · Periode aktif <span className="font-semibold text-hero-accent">{activePeriod.name}</span></>}
                        <span className="block text-white/85 sm:inline">
                            <span className="hidden sm:inline"> · </span>
                            {today}
                        </span>
                    </p>
                </div>
                {children}
            </div>
        </section>
    );
}

function ExecutiveDashboard({ greeting, scopeLabel, threshold, quality, activeSurveys, accreditation, accreditationWarningDays, counts, can, cycle }: ExecutiveProps) {
    const delta = quality?.score !== null && quality?.score !== undefined && quality.previous_score !== null ? quality.score - quality.previous_score : null;
    const max = quality?.scheme?.scale_max ?? 4;
    const min = quality?.scheme?.scale_min ?? 0;
    const expiring = accreditation.filter((a) => a.days_left !== null && a.days_left <= accreditationWarningDays);
    const active = activeSurveys[0];

    return (
        <>
            <Head title="Dashboard" />
            <Hero greeting={greeting} scopeLabel={scopeLabel}>
                <div className="flex flex-wrap gap-2">
                    {can.manageSurveys && (
                        <Button asChild className="bg-white bg-none text-primary shadow-float hover:bg-white/90">
                            <Link href={route('surveys.create')}>
                                <Plus /> Buka Monev
                            </Link>
                        </Button>
                    )}
                    {can.manageInstruments && (
                        <Button asChild variant="outline" className="border-white/35 bg-white/10 bg-none text-white hover:bg-white/20 hover:text-white">
                            <Link href={route('instruments.index')}>
                                <ClipboardList /> Instrumen
                            </Link>
                        </Button>
                    )}
                    {can.import && route().has('imports.index') && (
                        <Button asChild variant="outline" className="border-white/35 bg-white/10 bg-none text-white hover:bg-white/20 hover:text-white">
                            <Link href={route('imports.index')}>
                                <FileSpreadsheet /> Impor data
                            </Link>
                        </Button>
                    )}
                </div>
            </Hero>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div className="flex flex-col gap-3 rounded-2xl border border-primary/20 bg-gradient-to-br from-secondary/80 to-card p-5">
                    <span className="text-[13px] font-semibold text-muted-foreground">Skor mutu pembelajaran</span>
                    <div className="flex items-end gap-2">
                        <span className="text-5xl leading-none font-extrabold tracking-tight text-primary">{formatScore(quality?.score)}</span>
                        <span className="mb-1 text-sm font-semibold text-muted-foreground">/ {max}</span>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        <ClassBadge classification={quality?.classification} />
                        {delta !== null && (
                            <span className={cn('inline-flex items-center gap-0.5 font-semibold', delta >= 0 ? 'text-primary' : 'text-destructive')}>
                                {delta >= 0 ? <ArrowUpRight className="size-3.5" /> : <ArrowDownRight className="size-3.5" />}
                                {formatScore(Math.abs(delta))} vs {quality?.previous_label}
                            </span>
                        )}
                    </div>
                    <span className="text-[11px] text-muted-foreground">{quality?.survey.period ?? 'Belum ada Monev yang ditutup'}</span>
                </div>
                <StatTile
                    label="Response rate Monev berjalan"
                    value={active ? formatPercent(active.progress.rate) : '—'}
                    icon={Users}
                    tone="info"
                    hint={active ? `${formatNumber(active.progress.submitted)} dari ${formatNumber(active.progress.eligible)} · sisa ${Math.max(0, daysLeft(active.ends_at))} hari` : 'Tidak ada Monev yang sedang dibuka'}
                >
                    {active && <Meter value={active.progress.rate} />}
                </StatTile>
                <StatTile
                    label="Dosen di bawah ambang"
                    value={quality ? `${quality.lecturersBelow}` : '—'}
                    icon={UserRoundSearch}
                    tone={quality?.lecturersBelow ? 'danger' : 'neutral'}
                    hint={quality && quality.lecturersTotal ? `dari ${quality.lecturersTotal} dosen dengan hasil memadai (ambang ${formatScore(threshold)})` : `Skor < ${formatScore(threshold)}`}
                />
                <StatTile
                    label="Akreditasi segera berakhir"
                    value={`${expiring.length}`}
                    icon={BadgeCheck}
                    tone={expiring.length ? 'gold' : 'primary'}
                    hint={`dari ${counts.programs} prodi · dalam ${accreditationWarningDays} hari`}
                />
            </div>

            <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="size-4 text-primary" /> Tren mutu pembelajaran
                            </CardTitle>
                            <CardDescription>Skor rata-rata Monev Pembelajaran setiap periode.</CardDescription>
                        </div>
                        {can.analytics && (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('analytics.index')}>
                                    Analitik <ArrowRight />
                                </Link>
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent>
                        {quality ? <TrendChart data={quality.trend} min={min} max={max} threshold={threshold} height={260} /> : <EmptyState title="Belum ada data tren" className="py-10" />}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Peringkat program studi</CardTitle>
                        <CardDescription>{quality?.survey.period ?? '—'}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        {quality && quality.byStudyProgram.length ? (
                            <ScoreBars
                                items={quality.byStudyProgram.map((p) => ({ id: p.id, label: p.name, sublabel: `n=${p.responses}`, score: p.score, responses: p.responses, classification: p.classification }))}
                                max={max}
                                min={min}
                                threshold={threshold}
                                showBadge={false}
                                onSelect={can.analytics ? (item) => router.visit(route('analytics.index', { survey: quality.survey.id, study_program_id: item.id })) : undefined}
                            />
                        ) : (
                            <p className="text-sm text-muted-foreground">Belum ada data.</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            <CycleSection cycle={cycle} />

            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2 2xl:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <CalendarClock className="size-4 text-primary" /> Monev sedang berjalan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-5">
                        {activeSurveys.length === 0 && <p className="text-sm text-muted-foreground">Tidak ada Monev yang sedang dibuka.</p>}
                        {activeSurveys.map((survey) => (
                            <div key={survey.id}>
                                <Link href={route('surveys.show', survey.id)} className="flex items-baseline justify-between gap-3 hover:text-primary">
                                    <span className="truncate text-sm font-bold">{survey.title}</span>
                                    <StatusBadge tone={daysLeft(survey.ends_at) <= 3 ? 'warning' : 'success'} dot={false}>
                                        {Math.max(0, daysLeft(survey.ends_at))} hari lagi
                                    </StatusBadge>
                                </Link>
                                <div className="mt-3 flex flex-col gap-2.5">
                                    {survey.byStudyProgram.map((row) => (
                                        <div key={row.study_program_id}>
                                            <div className="mb-1 flex justify-between text-xs">
                                                <span className="truncate text-muted-foreground">{row.name}</span>
                                                <span className="font-semibold tabular">{formatPercent(row.rate, 0)}</span>
                                            </div>
                                            <Meter value={row.rate} size="sm" />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Lightbulb className="size-4 text-gold-foreground" /> Indikator terlemah
                        </CardTitle>
                        <CardDescription>Prioritas perbaikan dari Monev terakhir.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {(quality?.lowQuestions ?? []).map((q) => (
                            <div key={q.id} className={cn('flex items-center gap-3 rounded-xl p-3', q.score !== null && q.score < threshold ? 'bg-danger-soft/60' : 'bg-muted/50')}>
                                <span className="font-mono text-xs font-bold text-primary">{q.code}</span>
                                <span className="min-w-0 flex-1 truncate text-[13px]">{q.indicator ?? q.label}</span>
                                <span className={cn('font-extrabold tabular', q.score !== null && q.score < threshold && 'text-destructive')}>{formatScore(q.score)}</span>
                            </div>
                        ))}
                        {!quality && <p className="text-sm text-muted-foreground">Belum ada data.</p>}
                    </CardContent>
                </Card>

                <Card className="lg:col-span-2 2xl:col-span-1">
                    <CardHeader className="flex flex-row items-start justify-between gap-4">
                        <CardTitle className="flex items-center gap-2">
                            <BadgeCheck className="size-4 text-primary" /> Status akreditasi prodi
                        </CardTitle>
                        {can.accreditation && (
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('accreditation.readiness')}>
                                    Kesiapan <ArrowRight />
                                </Link>
                            </Button>
                        )}
                    </CardHeader>
                    <CardContent className="grid grid-cols-1 gap-2 lg:grid-cols-2 2xl:grid-cols-1">
                        {accreditation.map((row) => {
                            const warn = row.days_left !== null && row.days_left <= accreditationWarningDays;
                            const expired = row.days_left !== null && row.days_left < 0;
                            return (
                                <div key={row.id} className="relative flex items-center gap-3 rounded-xl border px-3 py-2.5">
                                    <span className="flex h-9 min-w-9 shrink-0 items-center justify-center rounded-lg bg-gold-soft px-2 text-[11px] font-extrabold text-gold-foreground">{row.body ?? '—'}</span>
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate text-[13px] font-semibold">{row.name}</div>
                                        <div className="text-[11px] text-muted-foreground">
                                            {row.status ?? 'Belum ada status'} · s.d. {formatDate(row.valid_until)}
                                        </div>
                                        {row.period_id !== null && can.accreditation && (
                                            <Link href={route('accreditation.periods.show', row.period_id)} className="mt-1 flex items-center gap-2 text-[11px] font-semibold text-primary after:absolute after:inset-0">
                                                <Meter value={row.readiness} severity="good" size="sm" className="w-20" />
                                                Kesiapan {formatPercent(row.readiness, 0)}
                                            </Link>
                                        )}
                                    </div>
                                    {row.days_left !== null && (
                                        <StatusBadge tone={expired ? 'danger' : warn ? 'warning' : 'success'}>{expired ? 'Kedaluwarsa' : `${row.days_left} hari`}</StatusBadge>
                                    )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            </div>

            <div className="mt-6 grid grid-cols-3 gap-4">
                <MiniCount icon={GraduationCap} label="Mahasiswa aktif" value={counts.students} />
                <MiniCount icon={Users} label="Dosen aktif" value={counts.lecturers} />
                <MiniCount icon={BookOpenCheck} label="Program studi" value={counts.programs} />
            </div>
        </>
    );
}

function CycleSection({ cycle }: { cycle: QualityCycle }) {
    const panels = [
        cycle.findings && {
            key: 'ami',
            icon: ShieldCheck,
            title: 'Audit Mutu Internal',
            href: route().has('ami.findings.index') ? route('ami.findings.index') : null,
            headline: cycle.findings.open,
            headlineLabel: 'temuan belum selesai',
            stats: [
                { label: 'Lewat tenggat', value: cycle.findings.overdue, danger: cycle.findings.overdue > 0 },
                { label: 'Audit berjalan', value: cycle.findings.audits_running },
                { label: 'Temuan ditutup', value: cycle.findings.closed },
            ],
            meter: null,
        },
        cycle.improvement && {
            key: 'improvement',
            icon: Target,
            title: 'Peningkatan Mutu',
            href: route().has('improvement.recommendations.index') ? route('improvement.recommendations.index') : null,
            headline: cycle.improvement.open,
            headlineLabel: 'rekomendasi aktif',
            stats: [
                { label: 'Rencana lewat tenggat', value: cycle.improvement.overdue_plans, danger: cycle.improvement.overdue_plans > 0 },
                { label: 'Ditutup', value: cycle.improvement.closed },
            ],
            meter: { label: 'Rata-rata progres rencana aksi', value: cycle.improvement.avg_progress },
        },
        cycle.evidence && {
            key: 'evidence',
            icon: FolderCheck,
            title: 'Dokumen Bukti',
            href: route().has('evidence.index') ? route('evidence.index') : null,
            headline: cycle.evidence.total,
            headlineLabel: 'dokumen',
            stats: [
                { label: 'Menunggu verifikasi', value: cycle.evidence.pending, danger: false },
                { label: 'Kedaluwarsa ≤ 60 hari', value: cycle.evidence.expiring, danger: cycle.evidence.expiring > 0 },
            ],
            meter: { label: 'Terverifikasi & berlaku', value: cycle.evidence.total ? (cycle.evidence.verified / cycle.evidence.total) * 100 : 0 },
        },
    ].filter((panel) => !!panel);

    if (panels.length === 0) {
        return null;
    }

    return (
        <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle>Siklus penjaminan mutu</CardTitle>
                    <CardDescription>Evaluasi → pengendalian → peningkatan: temuan, tindak lanjut, dan kelengkapan bukti.</CardDescription>
                </CardHeader>
                <CardContent className={cn('grid gap-3', panels.length === 3 ? 'md:grid-cols-3' : 'md:grid-cols-2')}>
                    {panels.map((panel) => {
                        const Icon = panel.icon;
                        const body = (
                            <>
                                <div className="flex items-center gap-2 text-[13px] font-semibold text-muted-foreground">
                                    <Icon className="size-4 text-primary" /> {panel.title}
                                </div>
                                <div className="mt-2 flex items-baseline gap-1.5">
                                    <span className="text-3xl font-extrabold tracking-tight">{formatNumber(panel.headline)}</span>
                                    <span className="text-xs text-muted-foreground">{panel.headlineLabel}</span>
                                </div>
                                {panel.meter && (
                                    <div className="mt-3">
                                        <div className="mb-1 flex justify-between text-[11px] text-muted-foreground">
                                            <span>{panel.meter.label}</span>
                                            <span className="font-semibold text-foreground tabular">{formatPercent(panel.meter.value, 0)}</span>
                                        </div>
                                        <Meter value={panel.meter.value} severity="good" size="sm" />
                                    </div>
                                )}
                                <dl className="mt-3 flex flex-col gap-1 border-t pt-3 text-xs">
                                    {panel.stats.map((stat) => (
                                        <div key={stat.label} className="flex justify-between gap-2">
                                            <dt className="text-muted-foreground">{stat.label}</dt>
                                            <dd className={cn('font-bold tabular', stat.danger && 'text-destructive')}>{formatNumber(stat.value)}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </>
                        );

                        return panel.href ? (
                            <Link key={panel.key} href={panel.href} className="rounded-2xl border p-4 transition hover:border-primary/30 hover:bg-secondary/30">
                                {body}
                            </Link>
                        ) : (
                            <div key={panel.key} className="rounded-2xl border p-4">
                                {body}
                            </div>
                        );
                    })}
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <AlarmClock className="size-4 text-destructive" /> Perlu perhatian
                    </CardTitle>
                    <CardDescription>Temuan & rencana aksi yang melewati tenggat.</CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-2">
                    {cycle.attention.length === 0 && (
                        <div className="flex items-center gap-2 rounded-xl bg-secondary/60 p-3 text-sm text-primary">
                            <CheckCircle2 className="size-4" /> Tidak ada tindak lanjut yang terlambat.
                        </div>
                    )}
                    {cycle.attention.map((item) => (
                        <Link key={`${item.type}-${item.code}-${item.title}`} href={item.url} className="flex items-start gap-3 rounded-xl border px-3 py-2.5 transition hover:border-destructive/30 hover:bg-danger-soft/30">
                            <div className="min-w-0 flex-1">
                                <div className="text-[11px] text-muted-foreground">
                                    {item.type} · <span className="font-mono">{item.code}</span>
                                </div>
                                <div className="truncate text-[13px] font-semibold">{item.title}</div>
                                <div className="text-[11px] text-muted-foreground">PIC {item.pic ?? '—'}</div>
                            </div>
                            <StatusBadge tone="danger" dot={false}>
                                {formatDate(item.due_date)}
                            </StatusBadge>
                        </Link>
                    ))}
                </CardContent>
            </Card>
        </div>
    );
}

function MiniCount({ icon: Icon, label, value }: { icon: typeof Users; label: string; value: number }) {
    return (
        <div className="flex items-center gap-3 rounded-2xl border bg-card px-4 py-3">
            <Icon className="size-5 text-primary" />
            <div>
                <div className="text-lg font-extrabold">{formatNumber(value)}</div>
                <div className="text-[11px] text-muted-foreground">{label}</div>
            </div>
        </div>
    );
}

function LecturerDashboard({ greeting, scopeLabel, threshold, lecturer, evaluation, tasks }: LecturerProps) {
    const max = evaluation?.scheme?.scale_max ?? 4;
    const min = evaluation?.scheme?.scale_min ?? 0;

    return (
        <>
            <Head title="Dashboard" />
            <Hero greeting={greeting} scopeLabel={scopeLabel}>
                <div className="flex gap-2">
                    <Button asChild className="bg-white bg-none text-primary shadow-float hover:bg-white/90">
                        <Link href={route('my-evaluation.index')}>
                            <UserRoundSearch /> Hasil evaluasi saya
                        </Link>
                    </Button>
                </div>
            </Hero>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div className="flex flex-col gap-3 rounded-2xl border border-primary/20 bg-gradient-to-br from-secondary/80 to-card p-5">
                    <span className="text-[13px] font-semibold text-muted-foreground">Skor evaluasi terakhir</span>
                    <div className="flex items-end gap-2">
                        <span className="text-5xl leading-none font-extrabold text-primary">{evaluation?.summary.suppressed ? '—' : formatScore(evaluation?.summary.score)}</span>
                        <span className="mb-1 text-sm text-muted-foreground">/ {max}</span>
                    </div>
                    <ClassBadge classification={evaluation?.summary.classification} />
                    <span className="text-[11px] text-muted-foreground">{evaluation?.survey.period ?? 'Belum ada hasil'}</span>
                </div>
                <StatTile label="Kelas diampu periode ini" value={lecturer?.classes ?? 0} icon={BookOpenCheck} hint={lecturer?.study_program ?? undefined} />
                <StatTile label="Respons terakhir" value={evaluation?.summary.responses ?? 0} icon={Users} tone="gold" hint="Seluruh jawaban mahasiswa anonim" />
            </div>
            <div className="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Perkembangan skor Anda</CardTitle>
                    </CardHeader>
                    <CardContent>{evaluation ? <TrendChart data={evaluation.trend} min={min} max={max} threshold={threshold} height={230} /> : <EmptyState title="Belum ada data" className="py-8" />}</CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Lightbulb className="size-4 text-gold-foreground" /> Fokus perbaikan
                        </CardTitle>
                        <CardDescription>Butir dengan skor di bawah {formatScore(threshold)}.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-2">
                        {evaluation?.improvements.length === 0 && <p className="text-sm text-muted-foreground">Tidak ada butir di bawah ambang. Pertahankan!</p>}
                        {evaluation?.improvements.map((q) => (
                            <div key={q.id} className="flex items-center gap-3 rounded-xl bg-danger-soft/60 p-3">
                                <span className="font-mono text-xs font-bold text-destructive">{q.code}</span>
                                <span className="flex-1 text-[13px]">{q.indicator ?? q.label}</span>
                                <span className="font-extrabold text-destructive tabular">{formatScore(q.score)}</span>
                            </div>
                        ))}
                        <Button variant="outline" asChild className="mt-2 self-start">
                            <Link href={route('my-evaluation.index')}>
                                Lihat rincian & komentar <ArrowRight />
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
            {tasks.length > 0 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ListTodo className="size-4 text-primary" /> Tugas mutu saya
                        </CardTitle>
                        <CardDescription>Temuan AMI, rencana aksi, dan dokumen akreditasi yang Anda tangani sebagai PIC.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid grid-cols-1 gap-2 md:grid-cols-2">
                        {tasks.map((task) => (
                            <Link key={task.url + task.title} href={task.url} className={cn('flex items-start gap-3 rounded-xl border px-3 py-2.5 transition hover:border-primary/30 hover:bg-secondary/30', task.overdue && 'border-destructive/40')}>
                                <div className="min-w-0 flex-1">
                                    <div className="text-[11px] text-muted-foreground">
                                        {task.type} · {task.status}
                                    </div>
                                    <div className="truncate text-[13px] font-semibold">{task.title}</div>
                                </div>
                                {task.due_date && (
                                    <StatusBadge tone={task.overdue ? 'danger' : 'neutral'} dot={false}>
                                        {formatDate(task.due_date)}
                                    </StatusBadge>
                                )}
                            </Link>
                        ))}
                    </CardContent>
                </Card>
            )}
        </>
    );
}
