import { Link, router } from '@inertiajs/react';
import { AlarmClock, BadgeCheck, CalendarClock, FileWarning, Gauge, Plus, ShieldAlert } from 'lucide-react';
import { ReadinessBar } from '@/components/accreditation/readiness-bar';
import type { ReadinessSummary } from '@/components/accreditation/types';
import { Heatmap } from '@/components/charts/heatmap';
import { PageHeader } from '@/components/page-header';
import { StatTile } from '@/components/stat-tile';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { deadlineLabel, deadlineTone, periodTone } from '@/lib/accreditation';
import { formatDate, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/utils';

interface ProgramReadiness {
    id: number;
    name: string;
    code: string;
    body: string | null;
    status: string | null;
    valid_until: string | null;
    days_left: number | null;
    period: {
        id: number;
        code: string;
        name: string;
        status: string;
        status_label: string;
        instrument: string;
        pic: string | null;
        submission_deadline: string | null;
        days_to_deadline: number | null;
    } | null;
    summary: ReadinessSummary | null;
    criteria: { code: string; title: string; readiness: number | null }[];
}

interface Props {
    programs: ProgramReadiness[];
    warningDays: number;
    can: { manage: boolean };
}

export default function Readiness({ programs, warningDays, can }: Props) {
    const active = programs.filter((p) => p.period && p.summary);
    const average = active.length ? active.reduce((sum, p) => sum + (p.summary?.readiness ?? 0), 0) / active.length : null;
    const gaps = active.reduce((sum, p) => sum + (p.summary?.gap ?? 0), 0);
    const expiring = programs.filter((p) => p.days_left !== null && p.days_left <= warningDays);
    const unprepared = expiring.filter((p) => !p.period);
    const criteriaCodes = Array.from(new Map(active.flatMap((p) => p.criteria.map((c) => [c.code, c.title] as const))).entries()).map(([code, title]) => ({ code, title }));

    return (
        <>
            <PageHeader
                title="Kesiapan Akreditasi"
                description="Pantau kelengkapan bukti per kriteria, estimasi skor, dan tenggat reakreditasi seluruh program studi."
                breadcrumbs={[{ label: 'Akreditasi' }, { label: 'Kesiapan' }]}
                actions={
                    can.manage && (
                        <Button asChild>
                            <Link href={route('accreditation.periods.index', { create: 1 })}>
                                <Plus /> Periode akreditasi
                            </Link>
                        </Button>
                    )
                }
            />

            <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile label="Rata-rata kesiapan dokumen" value={formatPercent(average, 0)} icon={Gauge} hint={`${active.length} periode sedang berjalan`} />
                <StatTile label="Indikator belum ada bukti" value={formatNumber(gaps)} icon={FileWarning} tone={gaps ? 'danger' : 'neutral'} hint="Seluruh periode berjalan" />
                <StatTile label={`Akreditasi berakhir ≤ ${warningDays} hari`} value={expiring.length} icon={AlarmClock} tone={expiring.length ? 'gold' : 'primary'} hint={`dari ${programs.length} prodi`} />
                <StatTile label="Belum memulai persiapan" value={unprepared.length} icon={ShieldAlert} tone={unprepared.length ? 'danger' : 'neutral'} hint={unprepared.length ? unprepared.map((p) => p.code).join(', ') : 'Semua prodi yang mendekati masa berakhir sudah memiliki periode'} />
            </div>

            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                {programs.map((program) => (
                    <ProgramCard key={program.id} program={program} warningDays={warningDays} canManage={can.manage} />
                ))}
            </div>

            {active.length > 0 && criteriaCodes.length > 0 && (
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Peta kesiapan per kriteria</CardTitle>
                        <CardDescription>Persentase kesiapan dokumen per kriteria untuk setiap periode berjalan. Klik nama prodi untuk membuka periode.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Heatmap
                            sections={criteriaCodes}
                            rows={active.map((p) => ({ id: p.period!.id, name: p.name, cells: Object.fromEntries(p.criteria.map((c) => [c.code, c.readiness])) }))}
                            min={0}
                            max={100}
                            format={(value) => (value === null || value === undefined ? '—' : `${Math.round(value)}%`)}
                            onSelectRow={(id) => router.visit(route('accreditation.periods.show', id))}
                        />
                    </CardContent>
                </Card>
            )}
        </>
    );
}

function ProgramCard({ program, warningDays, canManage }: { program: ProgramReadiness; warningDays: number; canManage: boolean }) {
    const expiring = program.days_left !== null && program.days_left <= warningDays;
    const summary = program.summary;

    return (
        <Card className={cn('gap-0 overflow-hidden py-0', expiring && !program.period && 'border-destructive/40')}>
            <div className="flex items-start gap-3 border-b bg-muted/30 px-5 py-4">
                <span className="flex h-10 min-w-10 shrink-0 items-center justify-center rounded-xl bg-gold-soft px-2 text-[11px] font-extrabold text-gold-foreground">{program.body ?? '—'}</span>
                <div className="min-w-0 flex-1">
                    <div className="truncate font-bold">{program.name}</div>
                    <div className="text-xs text-muted-foreground">
                        {program.status ?? 'Belum terakreditasi'} · berlaku s.d. {formatDate(program.valid_until)}
                    </div>
                </div>
                {program.days_left !== null && (
                    <StatusBadge tone={program.days_left < 0 ? 'danger' : expiring ? 'warning' : 'success'}>{program.days_left < 0 ? 'Kedaluwarsa' : `${program.days_left} hari`}</StatusBadge>
                )}
            </div>

            {program.period && summary ? (
                <Link href={route('accreditation.periods.show', program.period.id)} className="block px-5 py-4 transition hover:bg-secondary/30">
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                        <StatusBadge tone={periodTone[program.period.status]}>{program.period.status_label}</StatusBadge>
                        <span className="text-muted-foreground">{program.period.instrument}</span>
                        <StatusBadge tone={deadlineTone(program.period.days_to_deadline)} dot={false} className="ml-auto">
                            <CalendarClock className="size-3" /> {deadlineLabel(program.period.days_to_deadline)}
                        </StatusBadge>
                    </div>
                    <div className="mt-4 flex items-end gap-6">
                        <div>
                            <div className="text-[11px] font-semibold text-muted-foreground uppercase">Kesiapan dokumen</div>
                            <div className="text-4xl font-extrabold tracking-tight text-primary tabular">{formatPercent(summary.readiness, 0)}</div>
                        </div>
                        <div className="pb-1">
                            <div className="text-[11px] font-semibold text-muted-foreground uppercase">Estimasi</div>
                            <div className="text-sm font-bold">
                                {summary.estimated_grade ? (
                                    <>
                                        {summary.estimated_grade}
                                        <span className="ml-1 font-medium text-muted-foreground tabular">({formatNumber(summary.estimated_score)})</span>
                                    </>
                                ) : (
                                    <span className="font-medium text-muted-foreground">Belum cukup data</span>
                                )}
                            </div>
                            <div className="text-[11px] text-muted-foreground">{formatPercent(summary.coverage, 0)} indikator dinilai</div>
                        </div>
                        {summary.essential_unmet > 0 && (
                            <div className="ml-auto pb-1 text-right">
                                <div className="text-[11px] font-semibold text-destructive uppercase">Syarat perlu</div>
                                <div className="text-sm font-bold text-destructive">{summary.essential_unmet} belum terpenuhi</div>
                            </div>
                        )}
                    </div>
                    <ReadinessBar ready={summary.ready} partial={summary.partial} gap={summary.gap} legend className="mt-3" />
                    <div className="mt-4 grid gap-1" style={{ gridTemplateColumns: `repeat(${Math.max(program.criteria.length, 1)}, minmax(0, 1fr))` }}>
                        {program.criteria.map((criterion) => (
                            <div key={criterion.code} title={`${criterion.code} ${criterion.title}: ${formatPercent(criterion.readiness, 0)}`} className="flex flex-col items-center gap-1">
                                <div className="flex h-10 w-full items-end overflow-hidden rounded-md bg-muted">
                                    <div className={cn('w-full rounded-md', (criterion.readiness ?? 0) >= 75 ? 'bg-primary' : (criterion.readiness ?? 0) >= 40 ? 'bg-warning' : 'bg-destructive/60')} style={{ height: `${criterion.readiness ? Math.max(6, criterion.readiness) : 0}%` }} />
                                </div>
                                <span className="text-[11px] font-bold text-muted-foreground">{criterion.code}</span>
                            </div>
                        ))}
                    </div>
                </Link>
            ) : (
                <div className="flex flex-col items-start gap-3 px-5 py-6">
                    <p className="text-sm text-muted-foreground">
                        {expiring ? (
                            <>
                                <b className="text-destructive">Belum ada periode persiapan</b> padahal akreditasi berakhir dalam {program.days_left} hari.
                            </>
                        ) : (
                            'Belum ada periode akreditasi berjalan.'
                        )}
                    </p>
                    {canManage && (
                        <Button size="sm" variant={expiring ? 'default' : 'outline'} asChild>
                            <Link href={route('accreditation.periods.index', { create: 1, study_program_id: program.id })}>
                                <BadgeCheck /> Mulai persiapan
                            </Link>
                        </Button>
                    )}
                </div>
            )}
        </Card>
    );
}
