<?php

namespace App\Http\Controllers\Analytics;

use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\Survey;
use App\Models\User;
use App\Services\Analytics\MonevAnalytics;
use App\Services\Monev\SurveyProgress;
use App\Services\Settings;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request, SurveyProgress $progress): Response
    {
        $user = $request->user();
        $survey = $this->resolveSurvey($request);

        if (! $survey) {
            return Inertia::render('analytics/index', ['survey' => null, 'surveys' => []]);
        }

        $scope = $this->scopeFor($user, $request->integer('study_program_id') ?: null);
        $analytics = MonevAnalytics::for($survey, $scope);
        $canLecturer = $user->can('analytics.lecturer');
        $isTeaching = $survey->mode === SurveyMode::TeachingEvaluation;
        $threshold = (float) Settings::get('monev.low_score_threshold', 3.0);
        $questions = $analytics->byQuestion();

        return Inertia::render('analytics/index', [
            'surveys' => $this->surveyOptions(),
            'survey' => $this->surveyPayload($survey),
            'filters' => ['survey' => $survey->id, 'study_program_id' => $request->input('study_program_id')],
            'studyPrograms' => Options::studyPrograms($user, activeOnly: false),
            'scheme' => $analytics->schemePayload(),
            'threshold' => $threshold,
            'summary' => $analytics->summary(),
            'benchmark' => $scope !== null ? MonevAnalytics::for($survey)->summary() : null,
            'progress' => $progress->summary($survey),
            'byStudyProgram' => $analytics->byStudyProgram(),
            'byLecturer' => $isTeaching && $canLecturer ? $analytics->byLecturer() : null,
            'bySection' => $analytics->bySection(),
            'byQuestion' => $questions,
            'heatmap' => $analytics->heatmap(),
            'trend' => $analytics->trend(),
            'comments' => $analytics->comments(limit: 12),
            'minimum' => $analytics->minimum(),
            'can' => ['lecturer' => $canLecturer, 'export' => $user->can('reports.export')],
        ]);
    }

    public function lecturer(Request $request, Lecturer $lecturer): Response
    {
        $user = $request->user();
        $scope = $user->accessibleStudyProgramIds();

        abort_unless($this->canSeeLecturer($user, $lecturer, $scope), 403, 'Dosen ini berada di luar cakupan akses Anda.');

        $survey = $this->resolveSurvey($request, $lecturer);
        $lecturer->load('studyProgram');

        return Inertia::render('analytics/lecturer', [
            ...$this->lecturerPayload($lecturer, $survey, $scope, forEvaluatee: false),
            'surveys' => $this->surveyOptions($lecturer),
            'backUrl' => route('analytics.index', array_filter(['survey' => $survey?->id])),
        ]);
    }

    /**
     * @param  list<int>|null  $scope
     * @return array<string, mixed>
     */
    public static function lecturerPayload(Lecturer $lecturer, ?Survey $survey, ?array $scope, bool $forEvaluatee): array
    {
        if (! $survey) {
            return ['lecturer' => self::lecturerInfo($lecturer), 'survey' => null];
        }

        $analytics = MonevAnalytics::for($survey, $scope, $lecturer->id);
        $programAverage = MonevAnalytics::for($survey, [$lecturer->study_program_id])->summary();
        $institutionAverage = MonevAnalytics::for($survey)->summary();

        return [
            'lecturer' => self::lecturerInfo($lecturer),
            'survey' => self::surveyPayload($survey),
            'scheme' => $analytics->schemePayload(),
            'threshold' => (float) Settings::get('monev.low_score_threshold', 3.0),
            'minimum' => $analytics->minimum(),
            'summary' => $analytics->summary(),
            'benchmarks' => [
                ['label' => 'Rata-rata prodi homebase', 'score' => $programAverage['score']],
                ['label' => 'Rata-rata institusi', 'score' => $institutionAverage['score']],
            ],
            'bySection' => $analytics->bySection(),
            'byQuestion' => $analytics->byQuestion(),
            'byClass' => $analytics->byClass(),
            'trend' => $analytics->trend(),
            'comments' => $analytics->comments(forEvaluatee: $forEvaluatee, limit: 40),
            'sectionBenchmark' => MonevAnalytics::for($survey, [$lecturer->study_program_id])->bySection(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function lecturerInfo(Lecturer $lecturer): array
    {
        return [
            'id' => $lecturer->id,
            'name' => $lecturer->full_name,
            'nidn' => $lecturer->nidn,
            'academic_rank' => $lecturer->academic_rank,
            'study_program' => $lecturer->studyProgram?->full_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function surveyPayload(Survey $survey): array
    {
        $survey->loadMissing(['instrumentVersion.instrument', 'academicPeriod']);

        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'code' => $survey->code,
            'mode' => $survey->mode->value,
            'status' => $survey->status->value,
            'status_label' => $survey->status->label(),
            'period' => $survey->academicPeriod?->name,
            'instrument' => $survey->instrumentVersion->instrument->name,
            'instrument_version' => $survey->instrumentVersion->version,
            'scoring_method' => $survey->instrumentVersion->scoring_method->label(),
            'formula' => $survey->instrumentVersion->scoring_method->formula(),
            'scale_max' => $survey->instrumentVersion->scale_max,
            'scale_min' => $survey->instrumentVersion->scale_min,
        ];
    }

    /**
     * @return list<array{value: int, label: string, mode: string}>
     */
    private function surveyOptions(?Lecturer $lecturer = null): array
    {
        return Survey::query()
            ->whereIn('status', [SurveyStatus::Active, SurveyStatus::Closed, SurveyStatus::Archived])
            ->when($lecturer, fn (Builder $q) => $q->whereIn('id', DB::table('responses')->where('lecturer_id', $lecturer->id)->select('survey_id')))
            ->orderByDesc('starts_at')
            ->get(['id', 'title', 'mode', 'status'])
            ->map(fn (Survey $survey): array => [
                'value' => $survey->id,
                'label' => $survey->title.($survey->status === SurveyStatus::Active ? ' (berjalan)' : ''),
                'mode' => $survey->mode->value,
            ])
            ->all();
    }

    private function resolveSurvey(Request $request, ?Lecturer $lecturer = null): ?Survey
    {
        $query = Survey::query()->whereIn('status', [SurveyStatus::Active, SurveyStatus::Closed, SurveyStatus::Archived])
            ->when($lecturer, fn (Builder $q) => $q->whereIn('id', DB::table('responses')->where('lecturer_id', $lecturer->id)->select('survey_id')));

        if ($request->filled('survey')) {
            return (clone $query)->find($request->integer('survey'));
        }

        // Default: kegiatan evaluasi pembelajaran terbaru yang sudah ditutup; bila belum ada, yang sedang berjalan.
        return (clone $query)->where('mode', SurveyMode::TeachingEvaluation)->whereIn('status', [SurveyStatus::Closed, SurveyStatus::Archived])->orderByDesc('starts_at')->first()
            ?? (clone $query)->orderByDesc('starts_at')->first();
    }

    /**
     * @return list<int>|null
     */
    private function scopeFor(User $user, ?int $studyProgramId): ?array
    {
        $accessible = $user->accessibleStudyProgramIds();

        if ($studyProgramId !== null) {
            abort_unless($user->canAccessStudyProgram($studyProgramId), 403);

            return [$studyProgramId];
        }

        return $accessible;
    }

    /**
     * @param  list<int>|null  $scope
     */
    private function canSeeLecturer(User $user, Lecturer $lecturer, ?array $scope): bool
    {
        if (! $user->can('analytics.lecturer')) {
            return false;
        }

        return $scope === null
            || in_array($lecturer->study_program_id, $scope, true)
            || DB::table('responses')->where('lecturer_id', $lecturer->id)->whereIn('study_program_id', $scope)->exists();
    }
}
