import { Head, Link } from '@inertiajs/react';
import { CalendarClock, CheckCircle2, ChevronRight, ClipboardList, EyeOff, History, RotateCcw, ShieldCheck } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { StatusBadge } from '@/components/status-badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { formatDate, formatDateTime } from '@/lib/format';
import { daysLeft } from '@/lib/survey';
import { cn } from '@/lib/utils';

interface Target {
    target_key: string;
    teaching_assignment_id: number | null;
    course: string | null;
    course_code: string | null;
    class_code: string | null;
    lecturer: string | null;
    status: 'pending' | 'submitted' | 'reopened';
    submitted_at: string | null;
    reopen_reason: string | null;
}

interface PortalSurvey {
    id: number;
    title: string;
    description: string | null;
    mode: string;
    is_open: boolean;
    is_anonymous: boolean;
    starts_at: string;
    ends_at: string;
    period: string | null;
    instrument: string;
    questions_count: number;
    targets: Target[];
}

interface Props {
    profile: { name: string; identifier: string | null; study_program: string | null; semester: number | null };
    surveys: PortalSurvey[];
    highlight: string | null;
}

function ProgressRing({ value, total }: { value: number; total: number }) {
    const percent = total ? value / total : 0;
    const radius = 30;
    const circumference = 2 * Math.PI * radius;
    return (
        <div className="relative size-20 shrink-0">
            <svg viewBox="0 0 72 72" className="size-20 -rotate-90">
                <circle cx="36" cy="36" r={radius} fill="none" stroke="currentColor" strokeWidth="7" className="text-white/15" />
                <circle
                    cx="36"
                    cy="36"
                    r={radius}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="7"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={circumference * (1 - percent)}
                    className="text-hero-accent transition-[stroke-dashoffset] duration-700"
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center leading-none text-white">
                <span className="text-lg font-extrabold">
                    {value}/{total}
                </span>
                <span className="mt-0.5 text-[11px] font-semibold tracking-wide uppercase opacity-70">selesai</span>
            </div>
        </div>
    );
}

export default function PortalHome({ profile, surveys, highlight }: Props) {
    const open = surveys.filter((s) => s.is_open);
    const past = surveys.filter((s) => !s.is_open);
    const openTargets = open.flatMap((s) => s.targets);
    const done = openTargets.filter((t) => t.status === 'submitted').length;
    const firstName = profile.name.split(' ')[0];

    return (
        <>
            <Head title="Evaluasi Saya" />
            <section className="relative overflow-hidden rounded-3xl bg-hero p-5 text-white sm:p-6">
                <div className="bg-pattern-star absolute inset-0 opacity-50" />
                <div className="absolute -top-16 -right-10 size-52 rounded-full bg-primary/50 blur-3xl" />
                <div className="relative flex items-center gap-4">
                    <div className="min-w-0 flex-1">
                        <p className="text-sm text-white/85">Assalamu'alaikum,</p>
                        <h1 className="truncate text-xl font-extrabold sm:text-2xl">{firstName}</h1>
                        <p className="mt-1 truncate text-xs text-white/85">
                            {profile.identifier} · {profile.study_program}
                            {profile.semester ? ` · Semester ${profile.semester}` : ''}
                        </p>
                        {openTargets.length > 0 && (
                            <p className="mt-3 text-[13px] text-white/85">
                                {done === openTargets.length ? 'Semua evaluasi sudah Anda isi. Terima kasih!' : `${openTargets.length - done} evaluasi menunggu masukan Anda.`}
                            </p>
                        )}
                    </div>
                    {openTargets.length > 0 && <ProgressRing value={done} total={openTargets.length} />}
                </div>
            </section>

            <div className="mt-4 flex items-start gap-3 rounded-2xl border border-primary/15 bg-secondary/50 p-4 text-[13px] leading-relaxed text-secondary-foreground">
                <ShieldCheck className="mt-0.5 size-5 shrink-0 text-primary" />
                <p>
                    Jawaban Anda <b>anonim</b>: sistem hanya mencatat bahwa Anda sudah mengisi, tidak mencatat siapa memberi jawaban apa. Dosen hanya melihat rekap bila jumlah
                    respons mencukupi.
                </p>
            </div>

            <h2 className="mt-7 mb-3 flex items-center gap-2 text-sm font-bold tracking-wide text-muted-foreground uppercase">
                <ClipboardList className="size-4" /> Sedang dibuka
            </h2>

            {open.length === 0 ? (
                <div className="rounded-2xl border bg-card">
                    <EmptyState icon={CheckCircle2} title="Tidak ada Monev yang dibuka" description="Anda akan mendapat notifikasi saat LPM membuka evaluasi baru." />
                </div>
            ) : (
                <div className="flex flex-col gap-4">
                    {open.map((survey) => (
                        <SurveyCard key={survey.id} survey={survey} highlight={highlight} />
                    ))}
                </div>
            )}

            {past.length > 0 && (
                <Collapsible className="mt-8">
                    <CollapsibleTrigger className="flex w-full items-center gap-2 text-sm font-bold tracking-wide text-muted-foreground uppercase">
                        <History className="size-4" /> Riwayat ({past.length})
                        <ChevronRight className="ml-auto size-4" />
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-3 flex flex-col gap-3">
                        {past.map((survey) => (
                            <div key={survey.id} className="rounded-2xl border bg-card p-4">
                                <div className="font-semibold">{survey.title}</div>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    Ditutup {formatDate(survey.ends_at)} · {survey.targets.filter((t) => t.status === 'submitted').length}/{survey.targets.length} terisi
                                </div>
                            </div>
                        ))}
                    </CollapsibleContent>
                </Collapsible>
            )}
        </>
    );
}

function SurveyCard({ survey, highlight }: { survey: PortalSurvey; highlight: string | null }) {
    const left = daysLeft(survey.ends_at);
    const done = survey.targets.filter((t) => t.status === 'submitted').length;

    return (
        <article className="overflow-hidden rounded-2xl border bg-card">
            <header className="border-b p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <StatusBadge tone={left <= 3 ? 'warning' : 'success'} dot={false}>
                        <CalendarClock className="size-3" /> {left <= 0 ? 'Berakhir hari ini' : `Sisa ${left} hari`}
                    </StatusBadge>
                    {survey.is_anonymous && (
                        <StatusBadge tone="neutral" dot={false}>
                            <EyeOff className="size-3" /> Anonim
                        </StatusBadge>
                    )}
                    <span className="ml-auto text-xs font-semibold text-muted-foreground tabular">
                        {done}/{survey.targets.length}
                    </span>
                </div>
                <h3 className="mt-2 text-[16px] leading-snug font-bold">{survey.title}</h3>
                <p className="mt-0.5 text-xs text-muted-foreground">
                    {survey.questions_count} pertanyaan · ±{Math.max(2, Math.round(survey.questions_count * 0.25))} menit · hingga {formatDateTime(survey.ends_at)}
                </p>
            </header>
            <ul className="divide-y">
                {survey.targets.map((target) => {
                    const key = `${survey.id}|${target.teaching_assignment_id ?? 'general'}`;
                    const submitted = target.status === 'submitted';
                    const href = route('portal.surveys.show', { survey: survey.id, target: target.teaching_assignment_id ?? undefined });
                    const content = (
                        <>
                            <span
                                className={cn(
                                    'flex size-10 shrink-0 items-center justify-center rounded-xl text-xs font-extrabold',
                                    submitted ? 'bg-grad-primary text-white' : target.status === 'reopened' ? 'bg-warning-soft text-gold-foreground' : 'bg-gold-soft text-gold-foreground',
                                )}
                            >
                                {submitted ? <CheckCircle2 className="size-5" /> : target.status === 'reopened' ? <RotateCcw className="size-4" /> : (target.class_code ?? '•')}
                            </span>
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-[14px] font-semibold">{target.course ?? survey.instrument}</div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {target.lecturer ? `${target.lecturer} · Kelas ${target.class_code}` : 'Survei umum'}
                                </div>
                                {target.status === 'reopened' && <div className="mt-0.5 text-[11px] font-medium text-gold-foreground">Dibuka kembali: {target.reopen_reason}</div>}
                            </div>
                            {submitted ? (
                                <span className="shrink-0 text-[11px] font-semibold text-primary">Terkirim</span>
                            ) : (
                                <span className="flex shrink-0 items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-bold text-primary-foreground">
                                    Isi <ChevronRight className="size-3.5" />
                                </span>
                            )}
                        </>
                    );
                    return (
                        <li key={target.target_key} className={cn(highlight === key && 'animate-in fade-in bg-secondary/60 duration-700')}>
                            {submitted ? (
                                <div className="flex items-center gap-3 px-4 py-3.5 opacity-80">{content}</div>
                            ) : (
                                <Link href={href} className="flex items-center gap-3 px-4 py-3.5 transition active:bg-muted hover:bg-muted/50">
                                    {content}
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ul>
        </article>
    );
}
