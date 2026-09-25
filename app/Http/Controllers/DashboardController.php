<?php

namespace App\Http\Controllers;

use App\Enums\AccreditationPeriodStatus;
use App\Enums\ActionPlanStatus;
use App\Enums\AuditStatus;
use App\Enums\EvidenceStatus;
use App\Enums\FindingStatus;
use App\Enums\RecommendationStatus;
use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Analytics\AnalyticsController;
use App\Models\AcademicPeriod;
use App\Models\AccreditationPeriod;
use App\Models\ActionPlan;
use App\Models\Audit;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\StudyProgram;
use App\Models\Survey;
use App\Models\User;
use App\Services\Accreditation\ReadinessCalculator;
use App\Services\Analytics\MonevAnalytics;
use App\Services\Monev\SurveyProgress;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard sesuai peran: eksekutif (pimpinan/LPM/fakultas/prodi) atau dosen.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, SurveyProgress $progress): Response
    {
        $user = $request->user();

        if ($user->primaryRole() === UserRole::Dosen) {
            return $this->lecturer($user);
        }

        return $this->executive($user, $progress);
    }

    private function executive(User $user, SurveyProgress $progress): Response
    {
        $scope = $user->accessibleStudyProgramIds();
        $threshold = (float) Settings::get('monev.low_score_threshold', 3.0);

        $teachingSurveys = Survey::query()
            ->where('mode', SurveyMode::TeachingEvaluation)
            ->whereIn('status', [SurveyStatus::Active, SurveyStatus::Closed, SurveyStatus::Archived])
            ->with(['instrumentVersion', 'academicPeriod'])
            ->orderByDesc('starts_at')
            ->get();

        $latestClosed = $teachingSurveys->first(fn (Survey $s): bool => $s->status !== SurveyStatus::Active);
        $previousClosed = $teachingSurveys->filter(fn (Survey $s): bool => $s->status !== SurveyStatus::Active)->skip(1)->first();
        $active = Survey::query()->open()->with(['instrumentVersion.instrument', 'academicPeriod'])->orderBy('ends_at')->get();

        $latest = $latestClosed ? MonevAnalytics::for($latestClosed, $scope) : null;
        $latestSummary = $latest?->summary();
        $previousSummary = $previousClosed ? MonevAnalytics::for($previousClosed, $scope)->summary() : null;
        $lecturers = $latest && $user->can('analytics.lecturer') ? collect($latest->byLecturer()) : collect();
        $questions = $latest ? collect($latest->byQuestion()) : collect();

        $programs = StudyProgram::query()->visibleTo($user)->where('is_active', true)->with('accreditationBody:id,code')->orderBy('name')->get();
        $warningDays = (int) Settings::get('accreditation.expiry_warning_days', 180);
        $openPeriods = AccreditationPeriod::query()->whereIn('study_program_id', $programs->pluck('id'))
            ->whereIn('status', [AccreditationPeriodStatus::Preparing, AccreditationPeriodStatus::Submitted, AccreditationPeriodStatus::Visitation])
            ->with('version')->get()->keyBy('study_program_id');

        return Inertia::render('dashboard/index', [
            'variant' => 'executive',
            'greeting' => $this->greeting(),
            'scopeLabel' => $user->scopeLabel(),
            'threshold' => $threshold,
            'quality' => $latestClosed ? [
                'survey' => AnalyticsController::surveyPayload($latestClosed),
                'score' => $latestSummary['score'],
                'classification' => $latestSummary['classification'],
                'responses' => $latestSummary['responses'],
                'previous_score' => $previousSummary['score'] ?? null,
                'previous_label' => $previousClosed?->academicPeriod?->name,
                'scheme' => $latest->schemePayload(),
                'byStudyProgram' => $latest->byStudyProgram(),
                'lowQuestions' => $questions->filter(fn (array $q): bool => $q['score'] !== null)->sortBy('score')->take(5)->values()->all(),
                'lecturersBelow' => $lecturers->filter(fn (array $l): bool => $l['score'] !== null && $l['score'] < $threshold)->count(),
                'lecturersTotal' => $lecturers->count(),
                'trend' => $latest->trend(),
            ] : null,
            'activeSurveys' => $active->map(fn (Survey $survey): array => [
                'id' => $survey->id,
                'title' => $survey->title,
                'ends_at' => $survey->ends_at->toIso8601String(),
                'progress' => $progress->summary($survey),
                'byStudyProgram' => $progress->byStudyProgram($survey, $scope),
            ])->all(),
            'accreditation' => $programs->map(fn (StudyProgram $program): array => [
                ...$this->accreditationProgress($openPeriods->get($program->id)),
                'id' => $program->id,
                'name' => $program->full_name,
                'body' => $program->accreditationBody?->code,
                'status' => $program->accreditation_status,
                'valid_until' => $program->accreditation_valid_until?->toDateString(),
                'days_left' => $program->accreditation_valid_until ? (int) now()->startOfDay()->diffInDays($program->accreditation_valid_until, false) : null,
            ])->sortBy(fn (array $row) => $row['days_left'] ?? PHP_INT_MAX)->values()->all(),
            'accreditationWarningDays' => $warningDays,
            'cycle' => $this->qualityCycle($user),
            'counts' => [
                'students' => DB::table('students')->where('status', 'aktif')->when($scope !== null, fn ($q) => $q->whereIn('study_program_id', $scope))->count(),
                'lecturers' => DB::table('lecturers')->where('is_active', true)->when($scope !== null, fn ($q) => $q->whereIn('study_program_id', $scope))->count(),
                'programs' => $programs->count(),
            ],
            'can' => [
                'manageSurveys' => $user->can('surveys.manage'),
                'manageInstruments' => $user->can('instruments.manage'),
                'import' => $user->can('import.manage'),
                'analytics' => $user->can('analytics.view'),
                'accreditation' => $user->can('accreditation.view'),
            ],
        ]);
    }

    /**
     * @return array{period_id: int|null, readiness: float|null}
     */
    private function accreditationProgress(?AccreditationPeriod $period): array
    {
        return [
            'period_id' => $period?->id,
            'readiness' => $period ? ReadinessCalculator::for($period)->summary()['readiness'] : null,
        ];
    }

    /**
     * Ringkasan siklus PPEPP di luar Monev: temuan AMI, rencana aksi, dan kelengkapan dokumen bukti.
     *
     * @return array<string, mixed>
     */
    private function qualityCycle(User $user): array
    {
        $findings = $user->can('ami.view') || $user->can('findings.respond')
            ? Finding::query()->visibleTo($user, $user->can('ami.view'))
            : null;
        $recommendations = $user->can('improvement.view')
            ? Recommendation::query()->visibleTo($user, true)->select('id')
            : null;
        $evidence = $user->can('evidence.view') ? Evidence::query()->visibleTo($user, true) : null;

        $attention = collect();

        if ($findings) {
            $attention = $attention->merge((clone $findings)->overdue()->with('pic:id,name')->orderBy('due_date')->take(5)->get()
                ->map(fn (Finding $finding): array => [
                    'type' => 'Temuan AMI', 'code' => $finding->code, 'title' => $finding->title, 'pic' => $finding->pic?->name,
                    'due_date' => $finding->due_date?->toDateString(), 'url' => route('ami.findings.show', $finding),
                ]));
        }

        if ($recommendations) {
            $attention = $attention->merge(ActionPlan::query()->whereIn('recommendation_id', $recommendations)->overdue()->with(['pic:id,name', 'recommendation:id,code'])->orderBy('due_date')->take(5)->get()
                ->map(fn (ActionPlan $plan): array => [
                    'type' => 'Rencana aksi', 'code' => $plan->recommendation->code, 'title' => $plan->title, 'pic' => $plan->pic?->name,
                    'due_date' => $plan->due_date->toDateString(), 'url' => route('improvement.recommendations.show', $plan->recommendation_id),
                ]));
        }

        $evidenceTotal = $evidence ? (clone $evidence)->count() : 0;

        return [
            'findings' => $findings ? [
                'open' => (clone $findings)->whereIn('status', FindingStatus::unresolved())->count(),
                'overdue' => (clone $findings)->overdue()->count(),
                'closed' => (clone $findings)->where('status', FindingStatus::Closed)->count(),
                'audits_running' => Audit::query()->visibleTo($user, $user->can('ami.view'))->whereIn('status', [AuditStatus::DeskReview, AuditStatus::FieldAudit, AuditStatus::Reporting])->count(),
            ] : null,
            'improvement' => $recommendations ? [
                'open' => Recommendation::query()->whereIn('id', $recommendations)->whereIn('status', [RecommendationStatus::Open, RecommendationStatus::InProgress, RecommendationStatus::Completed])->count(),
                'closed' => Recommendation::query()->whereIn('id', $recommendations)->where('status', RecommendationStatus::Closed)->count(),
                'overdue_plans' => ActionPlan::query()->whereIn('recommendation_id', $recommendations)->overdue()->count(),
                'avg_progress' => round((float) ActionPlan::query()->whereIn('recommendation_id', $recommendations)->avg('progress'), 1),
            ] : null,
            'evidence' => $evidence ? [
                'total' => $evidenceTotal,
                'verified' => (clone $evidence)->where('status', EvidenceStatus::Verified)->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()))->count(),
                'pending' => (clone $evidence)->where('status', EvidenceStatus::Pending)->count(),
                'expiring' => (clone $evidence)->whereNotNull('valid_until')->whereBetween('valid_until', [now()->toDateString(), now()->addDays(60)->toDateString()])->count(),
            ] : null,
            'attention' => $attention->sortBy('due_date')->take(6)->values()->all(),
        ];
    }

    private function lecturer(User $user): Response
    {
        $lecturer = $user->lecturer?->load('studyProgram');
        $survey = $lecturer ? Survey::query()
            ->whereIn('status', [SurveyStatus::Closed, SurveyStatus::Archived])
            ->whereIn('id', DB::table('responses')->where('lecturer_id', $lecturer->id)->select('survey_id'))
            ->orderByDesc('starts_at')
            ->first() : null;

        $analytics = $survey ? MonevAnalytics::for($survey, null, $lecturer->id) : null;
        $threshold = (float) Settings::get('monev.low_score_threshold', 3.0);

        return Inertia::render('dashboard/index', [
            'variant' => 'lecturer',
            'greeting' => $this->greeting(),
            'scopeLabel' => $user->scopeLabel(),
            'threshold' => $threshold,
            'lecturer' => $lecturer ? [
                'name' => $lecturer->full_name,
                'study_program' => $lecturer->studyProgram?->full_name,
                'classes' => DB::table('teaching_assignments')
                    ->join('course_classes', 'course_classes.id', '=', 'teaching_assignments.course_class_id')
                    ->where('teaching_assignments.lecturer_id', $lecturer->id)
                    ->where('course_classes.academic_period_id', AcademicPeriod::active()?->id)
                    ->count(),
            ] : null,
            'tasks' => $this->qualityTasks($user),
            'evaluation' => $analytics ? [
                'survey' => AnalyticsController::surveyPayload($survey),
                'summary' => $analytics->summary(),
                'trend' => $analytics->trend(),
                'improvements' => collect($analytics->byQuestion())->filter(fn (array $q): bool => $q['score'] !== null && $q['score'] < $threshold)->sortBy('score')->take(4)->values()->all(),
                'scheme' => $analytics->schemePayload(),
            ] : null,
        ]);
    }

    /**
     * Penugasan mutu pribadi: temuan AMI, rencana aksi, dan periode akreditasi yang PIC-nya pengguna.
     *
     * @return list<array<string, mixed>>
     */
    private function qualityTasks(User $user): array
    {
        $findings = Finding::query()->where('pic_user_id', $user->id)->whereIn('status', FindingStatus::unresolved())->orderBy('due_date')->take(5)->get()
            ->map(fn (Finding $finding): array => [
                'type' => 'Temuan AMI', 'title' => "{$finding->code} — {$finding->title}", 'status' => $finding->status->label(),
                'due_date' => $finding->due_date?->toDateString(), 'overdue' => $finding->isOverdue(), 'url' => route('ami.findings.show', $finding),
            ]);

        $plans = ActionPlan::query()->where('pic_user_id', $user->id)->whereIn('status', [ActionPlanStatus::Planned, ActionPlanStatus::InProgress, ActionPlanStatus::Rejected])->orderBy('due_date')->take(5)->get()
            ->map(fn (ActionPlan $plan): array => [
                'type' => 'Rencana aksi', 'title' => $plan->title, 'status' => "{$plan->status->label()} · {$plan->progress}%",
                'due_date' => $plan->due_date->toDateString(), 'overdue' => $plan->isOverdue(), 'url' => route('improvement.recommendations.show', $plan->recommendation_id),
            ]);

        $periods = AccreditationPeriod::query()->where('pic_user_id', $user->id)->where('status', AccreditationPeriodStatus::Preparing)->get()
            ->map(fn (AccreditationPeriod $period): array => [
                'type' => 'Akreditasi', 'title' => $period->name, 'status' => 'Kesiapan '.number_format(ReadinessCalculator::for($period)->summary()['readiness'], 0, ',', '.').'%',
                'due_date' => $period->submission_deadline?->toDateString(), 'overdue' => ($period->daysToDeadline() ?? 1) < 0, 'url' => route('accreditation.periods.show', $period),
            ]);

        return $findings->concat($plans)->concat($periods)->sortBy(fn (array $task) => $task['due_date'] ?? '9999')->values()->all();
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }
}
