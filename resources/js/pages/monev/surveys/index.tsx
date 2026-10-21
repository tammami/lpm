import { Link } from '@inertiajs/react';
import { CalendarClock, ClipboardCheck, EyeOff, Plus, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Meter } from '@/components/meter';
import { SurveyOpenSwitch } from '@/components/monev/survey-open-switch';
import { PageHeader } from '@/components/page-header';
import { PaginationBar } from '@/components/pagination-bar';
import { SearchInput } from '@/components/search-input';
import { SelectField } from '@/components/select-field';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { formatDate, formatNumber, formatPercent } from '@/lib/format';
import { daysLeft, surveyTone } from '@/lib/survey';
import type { Option, Paginated } from '@/types';

export interface SurveySummary {
    id: number;
    code: string;
    title: string;
    mode: string;
    mode_label: string;
    stage: string | null;
    stage_label: string | null;
    respondent_label: string;
    status: string;
    status_label: string;
    is_open: boolean;
    is_anonymous: boolean;
    min_responses: number;
    starts_at: string;
    ends_at: string;
    period: string | null;
    instrument: string;
    instrument_version: string;
    instrument_version_id: number;
    study_programs: string[];
}

interface Row extends SurveySummary {
    progress: { eligible: number; submitted: number; rate: number | null };
}

interface Props {
    surveys: Paginated<Row>;
    filters: Record<string, string | undefined>;
    statuses: Option[];
    periods: Option[];
    modes: Option[];
    stages: Option[];
    canManage: boolean;
}

export default function SurveysIndex({ surveys, filters, statuses, periods, modes, stages, canManage }: Props) {
    const { filters: query, setFilter } = useQueryFilters({
        search: filters.search ?? '',
        status: filters.status ?? 'all',
        academic_period_id: filters.academic_period_id ?? 'all',
        mode: filters.mode ?? 'all',
        stage: filters.stage ?? 'all',
    });

    return (
        <>
            <PageHeader
                title="Kegiatan Monev"
                description="Buka periode pengisian evaluasi pembelajaran dan survei. Responden ditentukan otomatis dari penugasan mengajar & peserta kelas."
                breadcrumbs={[{ label: 'e-Monev' }, { label: 'Kegiatan Monev' }]}
                actions={
                    canManage && (
                        <Button asChild>
                            <Link href={route('surveys.create')}>
                                <Plus /> Kegiatan baru
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="mb-5 flex flex-col gap-2 lg:flex-row">
                <SearchInput value={query.search} onChange={(v) => setFilter('search', v)} placeholder="Cari judul atau kode…" />
                <div className="grid grid-cols-2 gap-2 sm:flex">
                    <SelectField value={query.status} onChange={(v) => setFilter('status', v)} options={statuses} allLabel="Semua status" className="sm:w-40" />
                    <SelectField value={query.academic_period_id} onChange={(v) => setFilter('academic_period_id', v)} options={periods} allLabel="Semua periode" className="sm:w-48" />
                    <SelectField value={query.stage} onChange={(v) => setFilter('stage', v)} options={stages} allLabel="Semua tahap" className="sm:w-40" />
                    <SelectField value={query.mode} onChange={(v) => setFilter('mode', v)} options={modes} allLabel="Semua jenis" className="sm:w-64" />
                </div>
            </div>

            {surveys.data.length === 0 ? (
                <div className="rounded-2xl border bg-card">
                    <EmptyState icon={ClipboardCheck} title="Belum ada kegiatan Monev" description="Buat kegiatan baru dengan memilih instrumen yang sudah terbit dan periode akademik." />
                </div>
            ) : (
                <div className="flex flex-col gap-3">
                    {surveys.data.map((survey) => {
                        const left = daysLeft(survey.ends_at);
                        return (
                            <div
                                key={survey.id}
                                className="group relative grid grid-cols-1 gap-4 rounded-2xl border bg-card p-5 transition hover:border-primary/30 hover:shadow-md hover:shadow-primary/5 lg:grid-cols-[minmax(0,1fr)_280px_auto] lg:items-center"
                            >
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusBadge tone={surveyTone[survey.status]}>{survey.status_label}</StatusBadge>
                                        {survey.stage_label && (
                                            <StatusBadge tone="gold" dot={false}>
                                                {survey.stage_label}
                                            </StatusBadge>
                                        )}
                                        {survey.status === 'active' && left < 0 && <StatusBadge tone="warning">Jadwal sudah berakhir</StatusBadge>}
                                        {survey.is_open && left <= 3 && <StatusBadge tone="warning">Berakhir {left <= 0 ? 'hari ini' : `${left} hari lagi`}</StatusBadge>}
                                        {survey.is_anonymous && (
                                            <StatusBadge tone="neutral" dot={false}>
                                                <EyeOff className="size-3" /> Anonim
                                            </StatusBadge>
                                        )}
                                        <span className="font-mono text-[11px] text-muted-foreground">{survey.code}</span>
                                    </div>
                                    <h2 className="mt-2 truncate text-[16px] font-bold group-hover:text-primary">
                                        {/* Tautan menutupi seluruh kartu; sakelar buka/tutup berada di atasnya. */}
                                        <Link
                                            href={route('surveys.show', survey.id)}
                                            className="after:absolute after:inset-0 after:rounded-2xl focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-ring"
                                        >
                                            {survey.title}
                                        </Link>
                                    </h2>
                                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span>
                                            {survey.instrument} v{survey.instrument_version}
                                        </span>
                                        {survey.period && <span>{survey.period}</span>}
                                        <span className="inline-flex items-center gap-1">
                                            <CalendarClock className="size-3" /> {formatDate(survey.starts_at)} – {formatDate(survey.ends_at)}
                                        </span>
                                        <span className="inline-flex items-center gap-1">
                                            <Users className="size-3" /> {survey.study_programs.length ? `${survey.study_programs.length} prodi` : 'Semua prodi'}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <div className="mb-1.5 flex items-baseline justify-between text-xs">
                                        <span className="font-semibold text-muted-foreground">Response rate</span>
                                        <span>
                                            <span className="text-base font-extrabold">{formatPercent(survey.progress.rate)}</span>
                                            <span className="ml-1.5 text-muted-foreground tabular">
                                                {formatNumber(survey.progress.submitted)}/{formatNumber(survey.progress.eligible)}
                                            </span>
                                        </span>
                                    </div>
                                    <Meter label="Response rate" value={survey.progress.rate} />
                                </div>
                                {canManage && <SurveyOpenSwitch survey={survey} className="relative z-10 lg:justify-self-end" />}
                            </div>
                        );
                    })}
                </div>
            )}
            {surveys.last_page > 1 && (
                <div className="mt-4 rounded-2xl border bg-card">
                    <PaginationBar meta={surveys} />
                </div>
            )}
        </>
    );
}
