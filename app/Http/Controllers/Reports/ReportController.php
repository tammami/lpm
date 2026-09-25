<?php

namespace App\Http\Controllers\Reports;

use App\Enums\QuestionType;
use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Models\Survey;
use App\Services\Analytics\MonevAnalytics;
use App\Services\AuditLogger;
use App\Services\Monev\SurveyProgress;
use App\Services\ReportBranding;
use App\Services\Settings;
use App\Support\Options;
use App\Support\Spreadsheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan PDF & Excel (BRD §62–64).
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('reports/index', [
            'surveys' => Survey::query()
                ->whereIn('status', [SurveyStatus::Active, SurveyStatus::Closed, SurveyStatus::Archived])
                ->with(['academicPeriod', 'instrumentVersion.instrument'])
                ->orderByDesc('starts_at')
                ->get()
                ->map(fn (Survey $survey): array => [
                    'value' => $survey->id,
                    'label' => $survey->title,
                    'mode' => $survey->mode->value,
                    'status_label' => $survey->status->label(),
                ])->all(),
            'studyPrograms' => Options::studyPrograms($user, activeOnly: false),
            'lecturers' => $user->can('analytics.lecturer') ? Options::lecturers($user) : [],
            'canRaw' => $user->isInstitutionWide(),
        ]);
    }

    public function monevPdf(Request $request, SurveyProgress $progress): HttpResponse
    {
        [$survey, $scope, $scopeLabel] = $this->resolve($request);
        $analytics = MonevAnalytics::for($survey, $scope);

        AuditLogger::log('exported', 'report', $survey, "Mengunduh laporan PDF {$survey->title} ({$scopeLabel})");

        return Pdf::loadView('reports.monev', [
            ...$this->common($request, "Laporan {$survey->title}"),
            'survey' => $survey->load(['instrumentVersion.instrument', 'academicPeriod']),
            'scopeLabel' => $scopeLabel,
            'scheme' => $analytics->schemePayload(),
            'summary' => $analytics->summary(),
            'progress' => $progress->summary($survey),
            'programs' => $analytics->byStudyProgram(),
            'sections' => $analytics->bySection(),
            'questions' => $analytics->byQuestion(),
            'lecturers' => $survey->mode === SurveyMode::TeachingEvaluation && $request->user()->can('analytics.lecturer') ? $analytics->byLecturer() : null,
            'comments' => $analytics->comments(limit: 10),
            'minimum' => $analytics->minimum(),
            'threshold' => (float) Settings::get('monev.low_score_threshold', 3.0),
        ])->setPaper('a4')->download($this->filename('laporan-monev', $survey, $scopeLabel, 'pdf'));
    }

    public function monevExcel(Request $request): StreamedResponse
    {
        [$survey, $scope, $scopeLabel] = $this->resolve($request);
        $analytics = MonevAnalytics::for($survey, $scope);
        $summary = $analytics->summary();

        AuditLogger::log('exported', 'report', $survey, "Mengunduh rekap Excel {$survey->title} ({$scopeLabel})");

        $sheets = [
            ['title' => 'Ringkasan', 'headers' => ['Keterangan', 'Nilai'], 'widths' => [30, 60], 'rows' => [
                ['Kegiatan', $survey->title],
                ['Cakupan', $scopeLabel],
                ['Instrumen', $survey->instrumentVersion->instrument->name.' v'.$survey->instrumentVersion->version],
                ['Metode skor', $survey->instrumentVersion->scoring_method->formula()],
                ['Jumlah respons', $summary['responses']],
                ['Skor rata-rata', $summary['score']],
                ['Klasifikasi', $summary['classification']['label'] ?? '—'],
                ['Minimum respons per dosen', $analytics->minimum()],
                ['Dicetak', now()->format('d/m/Y H:i').' oleh '.$request->user()->name],
            ]],
            ['title' => 'Per Prodi', 'headers' => ['Program Studi', 'Respons', 'Skor', 'Klasifikasi'], 'widths' => [40, 12, 10, 18], 'rows' => array_map(
                fn (array $row): array => [$row['name'], $row['responses'], $row['score'], $row['classification']['label'] ?? '—'],
                $analytics->byStudyProgram(),
            )],
            ['title' => 'Per Butir', 'headers' => ['Kode', 'Butir', 'Indikator', 'Bagian', 'Bobot', 'Respons', 'Skor', 'Klasifikasi'], 'widths' => [8, 60, 24, 24, 8, 10, 10, 16], 'rows' => array_map(
                fn (array $row): array => [$row['code'], $row['label'], $row['indicator'], $row['section_title'], $row['weight'], $row['responses'], $row['score'], $row['classification']['label'] ?? '—'],
                $analytics->byQuestion(),
            )],
        ];

        if ($survey->mode === SurveyMode::TeachingEvaluation && $request->user()->can('analytics.lecturer')) {
            $sheets[] = ['title' => 'Per Dosen', 'headers' => ['Dosen', 'Homebase', 'Kelas', 'Respons', 'Skor', 'Klasifikasi'], 'widths' => [36, 32, 8, 10, 10, 22], 'rows' => array_map(
                fn (array $row): array => [$row['name'], $row['study_program'], $row['classes'], $row['responses'], $row['sufficient'] ? $row['score'] : null, $row['sufficient'] ? ($row['classification']['label'] ?? '—') : 'Respons tidak mencukupi'],
                $analytics->byLecturer(),
            )];
        }

        return Spreadsheet::downloadSheets($this->filename('rekap-monev', $survey, $scopeLabel, 'xlsx'), $sheets);
    }

    /**
     * Data respons mentah (anonim) untuk analisis lanjutan. Hanya untuk peran tingkat institusi.
     */
    public function responsesExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isInstitutionWide(), 403);

        $survey = Survey::query()->with('instrumentVersion')->findOrFail($request->integer('survey'));
        $questions = $survey->instrumentVersion->questions()->with('options')->get();
        $optionLabels = $questions->flatMap->options->pluck('value', 'id');

        AuditLogger::log('exported', 'report', $survey, "Mengunduh data respons mentah {$survey->title}");

        $rows = DB::table('responses')
            ->leftJoin('study_programs', 'study_programs.id', '=', 'responses.study_program_id')
            ->leftJoin('lecturers', 'lecturers.id', '=', 'responses.lecturer_id')
            ->leftJoin('course_classes', 'course_classes.id', '=', 'responses.course_class_id')
            ->leftJoin('courses', 'courses.id', '=', 'course_classes.course_id')
            ->where('responses.survey_id', $survey->id)
            ->whereNull('responses.voided_at')
            ->orderBy('responses.id')
            ->select('responses.id', 'responses.score', 'study_programs.code as program', 'lecturers.name as lecturer', 'courses.code as course', 'course_classes.code as class_code')
            ->lazyById(500, 'responses.id', 'id')
            ->map(function (object $response) use ($questions, $optionLabels): array {
                $answers = DB::table('response_answers')->where('response_id', $response->id)->get()->keyBy('instrument_question_id');

                return [
                    Str::substr($response->id, 0, 8),
                    $response->program,
                    $response->course,
                    $response->class_code,
                    $response->lecturer,
                    ...$questions->map(function ($question) use ($answers, $optionLabels) {
                        $answer = $answers[$question->id] ?? null;

                        return match (true) {
                            $answer === null => null,
                            $answer->instrument_question_option_id !== null => $optionLabels[$answer->instrument_question_option_id] ?? null,
                            $question->type === QuestionType::MultipleChoice => $answer->value_json,
                            $answer->value_number !== null => (float) $answer->value_number,
                            default => $answer->value_text,
                        };
                    })->all(),
                    $response->score !== null ? (float) $response->score : null,
                ];
            });

        return Spreadsheet::download(
            $this->filename('respons-mentah', $survey, 'institusi', 'xlsx'),
            ['ID Respons', 'Prodi', 'Kode MK', 'Kelas', 'Dosen', ...$questions->pluck('code')->all(), 'Skor'],
            $rows,
            title: 'Respons',
        );
    }

    public function lecturerPdf(Request $request, Lecturer $lecturer): HttpResponse
    {
        $user = $request->user();
        $isSelf = $user->lecturer?->id === $lecturer->id;
        $scope = $user->accessibleStudyProgramIds();

        abort_unless($isSelf || ($user->can('analytics.lecturer') && ($scope === null || in_array($lecturer->study_program_id, $scope, true))), 403);

        $survey = Survey::query()->with(['instrumentVersion.instrument', 'academicPeriod'])->findOrFail($request->integer('survey'));
        $analytics = MonevAnalytics::for($survey, $isSelf ? null : $scope, $lecturer->id);
        $lecturer->load('studyProgram');

        AuditLogger::log('exported', 'report', $lecturer, "Mengunduh laporan dosen {$lecturer->full_name} ({$survey->title})");

        return Pdf::loadView('reports.lecturer', [
            ...$this->common($request, "Laporan Dosen {$lecturer->full_name}"),
            'survey' => $survey,
            'lecturer' => $lecturer,
            'scheme' => $analytics->schemePayload(),
            'summary' => $analytics->summary(),
            'benchmarks' => [
                ['label' => 'Rata-rata prodi', 'score' => MonevAnalytics::for($survey, [$lecturer->study_program_id])->summary()['score']],
                ['label' => 'Rata-rata institusi', 'score' => MonevAnalytics::for($survey)->summary()['score']],
            ],
            'sections' => $analytics->bySection(),
            'questions' => $analytics->byQuestion(),
            'classes' => $analytics->byClass(),
            'comments' => $analytics->comments(forEvaluatee: $isSelf, limit: 25),
            'minimum' => $analytics->minimum(),
            'threshold' => (float) Settings::get('monev.low_score_threshold', 3.0),
        ])->setPaper('a4')->download($this->filename('laporan-dosen-'.Str::slug($lecturer->name), $survey, '', 'pdf'));
    }

    /**
     * @return array{0: Survey, 1: list<int>|null, 2: string}
     */
    private function resolve(Request $request): array
    {
        $survey = Survey::query()->with(['instrumentVersion.instrument', 'academicPeriod'])->findOrFail($request->integer('survey'));
        $user = $request->user();
        $programId = $request->integer('study_program_id') ?: null;

        if ($programId) {
            abort_unless($user->canAccessStudyProgram($programId), 403);

            return [$survey, [$programId], StudyProgram::query()->findOrFail($programId)->full_name];
        }

        $scope = $user->accessibleStudyProgramIds();

        return [$survey, $scope, $scope === null ? 'Seluruh institusi' : $user->scopeLabel()];
    }

    /**
     * @return array<string, mixed>
     */
    private function common(Request $request, string $title): array
    {
        return [
            'title' => $title,
            'brand' => ReportBranding::data(),
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
            'generatedBy' => $request->user()->name,
        ];
    }

    private function filename(string $prefix, Survey $survey, string $scope, string $extension): string
    {
        return Str::slug(implode('-', array_filter([$prefix, $survey->code, $scope]))).'.'.$extension;
    }
}
