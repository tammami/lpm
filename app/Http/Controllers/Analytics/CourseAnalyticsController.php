<?php

namespace App\Http\Controllers\Analytics;

use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\StudyProgram;
use App\Models\Survey;
use App\Models\User;
use App\Services\Analytics\MonevAnalytics;
use App\Services\AuditLogger;
use App\Services\Settings;
use App\Support\Options;
use App\Support\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Analisis Monev pembelajaran per dosen per mata kuliah, untuk kaprodi memantau kinerja setiap MK.
 */
class CourseAnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $survey = $this->resolveSurvey($request);

        if (! $survey) {
            return Inertia::render('analytics/courses', ['survey' => null, 'surveys' => []]);
        }

        [$scope] = $this->scopeFor($user, $request->integer('study_program_id') ?: null);
        $analytics = MonevAnalytics::for($survey, $scope);

        return Inertia::render('analytics/courses', [
            'surveys' => $this->surveyOptions(),
            'survey' => AnalyticsController::surveyPayload($survey),
            'filters' => ['survey' => $survey->id, 'study_program_id' => $request->input('study_program_id')],
            'studyPrograms' => Options::studyPrograms($user, activeOnly: false),
            'scheme' => $analytics->schemePayload(),
            'threshold' => (float) Settings::get('monev.low_score_threshold', 3.0),
            'minimum' => $analytics->minimum(),
            'summary' => $analytics->summary(),
            'sections' => $analytics->scoredSections()->map->only(['code', 'title'])->values()->all(),
            'courses' => $analytics->byCourse($analytics->previousScores()),
            'can' => ['export' => $user->can('reports.export')],
        ]);
    }

    public function show(Request $request, Course $course): Response
    {
        $user = $request->user();
        $scope = $user->accessibleStudyProgramIds();

        abort_unless($scope === null || in_array($course->study_program_id, $scope, true), 403, 'Mata kuliah ini berada di luar cakupan akses Anda.');

        $course->load('studyProgram');
        $survey = $this->resolveSurvey($request, $course);
        $info = [
            'id' => $course->id,
            'code' => $course->code,
            'name' => $course->name,
            'semester' => $course->semester,
            'credits' => $course->credits,
            'study_program' => $course->studyProgram?->full_name,
        ];
        $backUrl = route('analytics.courses.index', array_filter(['survey' => $survey?->id]));

        if (! $survey) {
            return Inertia::render('analytics/course', ['course' => $info, 'survey' => null, 'surveys' => [], 'backUrl' => $backUrl]);
        }

        $courseAnalytics = MonevAnalytics::for($survey, null, null, $course->id);
        $rows = $courseAnalytics->byCourse();
        $lecturers = $rows[0]['lecturers'] ?? [];
        $lecturerId = $request->integer('lecturer') ?: null;

        if ($lecturerId !== null && ! collect($lecturers)->contains('id', $lecturerId)) {
            $lecturerId = null;
        }

        $analytics = MonevAnalytics::for($survey, null, $lecturerId, $course->id);
        $previous = $courseAnalytics->previousScores()[$course->id.($lecturerId !== null ? ':'.$lecturerId : '')] ?? null;
        $programSummary = MonevAnalytics::for($survey, [$course->study_program_id])->summary();

        return Inertia::render('analytics/course', [
            'course' => $info,
            'survey' => AnalyticsController::surveyPayload($survey),
            'surveys' => $this->surveyOptions($course),
            'backUrl' => $backUrl,
            'lecturer' => $lecturerId,
            'scheme' => $analytics->schemePayload(),
            'threshold' => (float) Settings::get('monev.low_score_threshold', 3.0),
            'minimum' => $analytics->minimum(),
            'summary' => $analytics->summary(),
            'benchmarks' => array_values(array_filter([
                $lecturerId !== null ? ['label' => 'Rata-rata MK ini', 'score' => $rows[0]['score'] ?? null] : null,
                ['label' => 'Rata-rata prodi', 'score' => $programSummary['score']],
                $previous ? ['label' => "Terakhir dievaluasi ({$previous['label']})", 'score' => $previous['score']] : null,
            ])),
            'sections' => $courseAnalytics->scoredSections()->map->only(['code', 'title'])->values()->all(),
            'lecturers' => $lecturers,
            'bySection' => $analytics->bySection(),
            'sectionBenchmark' => MonevAnalytics::for($survey, [$course->study_program_id])->bySection(),
            'byQuestion' => $analytics->byQuestion(),
            'byClass' => $analytics->byClass(),
            'trend' => $analytics->trend(),
            'comments' => $analytics->comments(limit: 40),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $survey = $this->resolveSurvey($request);

        abort_unless($survey !== null, 404);

        [$scope, $scopeLabel] = $this->scopeFor($user, $request->integer('study_program_id') ?: null);
        $analytics = MonevAnalytics::for($survey, $scope);
        $courses = $analytics->byCourse($analytics->previousScores());
        $sections = $analytics->scoredSections();
        $minimum = $analytics->minimum();

        AuditLogger::log('exported', 'report', $survey, "Mengunduh rekap dosen × mata kuliah {$survey->title} ({$scopeLabel})");

        $status = fn (array $row): string => $row['sufficient'] ? ($row['classification']['label'] ?? '—') : "Respons < {$minimum}";
        $change = fn (array $row): ?float => $row['score'] !== null && $row['previous_score'] !== null ? round($row['score'] - $row['previous_score'], 2) : null;
        $sectionValues = fn (array $row): array => $sections->map(fn ($section) => $row['sections'][$section->code] ?? null)->all();
        $sectionHeaders = $sections->map(fn ($section): string => "{$section->code}. {$section->title}")->all();
        $sectionWidths = array_fill(0, $sections->count(), 14);

        $pairRows = [];
        foreach ($courses as $course) {
            foreach ($course['lecturers'] as $lecturer) {
                $pairRows[] = [
                    $course['code'], $course['name'], $course['semester'], $course['study_program'],
                    $lecturer['name'], $lecturer['nidn'], $lecturer['class_codes'], $lecturer['responses'],
                    $lecturer['score'], $status($lecturer),
                    $lecturer['score'] !== null && $course['score'] !== null ? round($lecturer['score'] - $course['score'], 2) : null,
                    $lecturer['previous_score'], $lecturer['previous_label'], $change($lecturer),
                    ...$sectionValues($lecturer),
                ];
            }
        }

        $sheets = [
            [
                'title' => 'Dosen per MK',
                'headers' => ['Kode MK', 'Mata Kuliah', 'Smt', 'Prodi', 'Dosen', 'NIDN/NUPTK', 'Kelas', 'Respons', 'Skor', 'Klasifikasi', 'Selisih dari rata-rata MK', 'Skor terakhir dievaluasi', 'Periode pembanding', 'Perubahan', ...$sectionHeaders],
                'widths' => [12, 36, 6, 10, 34, 16, 12, 10, 8, 20, 14, 14, 18, 11, ...$sectionWidths],
                'rows' => $pairRows,
            ],
            [
                'title' => 'Per MK',
                'headers' => ['Kode MK', 'Mata Kuliah', 'Smt', 'SKS', 'Prodi', 'Kelas', 'Dosen', 'Respons', 'Skor', 'Klasifikasi', 'Skor terakhir dievaluasi', 'Periode pembanding', 'Perubahan', ...$sectionHeaders],
                'widths' => [12, 36, 6, 6, 10, 8, 8, 10, 8, 20, 14, 18, 11, ...$sectionWidths],
                'rows' => array_map(fn (array $course): array => [
                    $course['code'], $course['name'], $course['semester'], $course['credits'], $course['study_program'], $course['classes'], count($course['lecturers']),
                    $course['responses'], $course['score'], $status($course), $course['previous_score'], $course['previous_label'], $change($course),
                    ...$sectionValues($course),
                ], $courses),
            ],
            [
                'title' => 'Keterangan',
                'headers' => ['Keterangan', 'Nilai'],
                'widths' => [32, 70],
                'rows' => [
                    ['Kegiatan', $survey->title],
                    ['Cakupan', $scopeLabel],
                    ['Instrumen', $survey->instrumentVersion->instrument->name.' v'.$survey->instrumentVersion->version],
                    ['Pembanding', 'Skor periode terakhir MK / dosen–MK tersebut dievaluasi (instrumen & tahap Monev sama)'],
                    ['Minimum respons per dosen/MK', $minimum],
                    ['Ambang skor rendah', (float) Settings::get('monev.low_score_threshold', 3.0)],
                    ['Dicetak', now()->format('d/m/Y H:i').' oleh '.$user->name],
                ],
            ],
        ];

        return Spreadsheet::downloadSheets(Str::slug("rekap-dosen-mk-{$survey->code}-{$scopeLabel}").'.xlsx', $sheets);
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    private function surveyOptions(?Course $course = null): array
    {
        return $this->surveys($course)
            ->orderByDesc('starts_at')
            ->get(['id', 'title', 'status'])
            ->map(fn (Survey $survey): array => [
                'value' => $survey->id,
                'label' => $survey->title.($survey->status === SurveyStatus::Active ? ' (berjalan)' : ''),
            ])
            ->all();
    }

    private function resolveSurvey(Request $request, ?Course $course = null): ?Survey
    {
        if ($request->filled('survey')) {
            return $this->surveys($course)->with('instrumentVersion.instrument')->find($request->integer('survey'));
        }

        // Default: evaluasi pembelajaran terbaru yang sudah ditutup; bila belum ada, yang sedang berjalan.
        return $this->surveys($course)->whereIn('status', [SurveyStatus::Closed, SurveyStatus::Archived])->orderByDesc('starts_at')->first()
            ?? $this->surveys($course)->orderByDesc('starts_at')->first();
    }

    /**
     * @return Builder<Survey>
     */
    private function surveys(?Course $course): Builder
    {
        return Survey::query()
            ->where('mode', SurveyMode::TeachingEvaluation)
            ->whereIn('status', [SurveyStatus::Active, SurveyStatus::Closed, SurveyStatus::Archived])
            ->when($course, fn (Builder $q) => $q->whereIn('id', DB::table('responses')
                ->whereIn('course_class_id', DB::table('course_classes')->where('course_id', $course->id)->select('id'))
                ->select('survey_id')));
    }

    /**
     * @return array{0: list<int>|null, 1: string}
     */
    private function scopeFor(User $user, ?int $studyProgramId): array
    {
        if ($studyProgramId !== null) {
            abort_unless($user->canAccessStudyProgram($studyProgramId), 403);

            return [[$studyProgramId], StudyProgram::query()->findOrFail($studyProgramId)->full_name];
        }

        $scope = $user->accessibleStudyProgramIds();

        return [$scope, $scope === null ? 'Seluruh institusi' : $user->scopeLabel()];
    }
}
