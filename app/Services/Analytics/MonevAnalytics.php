<?php

namespace App\Services\Analytics;

use App\Enums\QuestionType;
use App\Models\ClassificationScheme;
use App\Models\InstrumentQuestion;
use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Models\Survey;
use App\Services\Settings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analitik hasil Monev: agregasi skor dengan ambang minimum respons (BR-009) dan cakupan akses.
 *
 * Cakupan: `$programIds` (null = seluruh institusi), `$lecturerId` (opsional, untuk drill-down dosen).
 */
class MonevAnalytics
{
    private ?ClassificationScheme $scheme = null;

    public function __construct(
        private Survey $survey,
        private ?array $programIds = null,
        private ?int $lecturerId = null,
    ) {
        $this->scheme = $survey->instrumentVersion->resolvedClassificationScheme();
    }

    public static function for(Survey $survey, ?array $programIds = null, ?int $lecturerId = null): self
    {
        return new self($survey->loadMissing('instrumentVersion'), $programIds, $lecturerId);
    }

    public function minimum(): int
    {
        return $this->survey->minimumResponses();
    }

    /**
     * @return array{responses: int, score: float|null, classification: array<string, mixed>|null, suppressed: bool}
     */
    public function summary(): array
    {
        $row = $this->responses()->selectRaw('count(*) as n, avg(score) as score')->first();
        $n = (int) $row->n;
        $suppressed = $this->lecturerId !== null && $n < $this->minimum();
        $score = $suppressed || $row->score === null ? null : round((float) $row->score, 2);

        return ['responses' => $n, 'score' => $score, 'classification' => $this->classify($score), 'suppressed' => $suppressed];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function byStudyProgram(): array
    {
        $rows = $this->responses()
            ->selectRaw('study_program_id, count(*) as n, avg(score) as score')
            ->groupBy('study_program_id')
            ->get()
            ->keyBy('study_program_id');

        return StudyProgram::query()->whereIn('id', $rows->keys()->filter())->orderBy('name')->get()
            ->map(function (StudyProgram $program) use ($rows): array {
                $score = round((float) $rows[$program->id]->score, 2);

                return [
                    'id' => $program->id,
                    'name' => $program->full_name,
                    'short' => $program->code,
                    'responses' => (int) $rows[$program->id]->n,
                    'score' => $score,
                    'classification' => $this->classify($score),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }

    /**
     * Skor per dosen. Dosen dengan respons di bawah ambang disembunyikan skornya.
     *
     * @return list<array<string, mixed>>
     */
    public function byLecturer(): array
    {
        $rows = $this->responses()
            ->whereNotNull('lecturer_id')
            ->selectRaw('lecturer_id, count(*) as n, avg(score) as score, count(distinct course_class_id) as classes')
            ->groupBy('lecturer_id')
            ->get()
            ->keyBy('lecturer_id');

        $minimum = $this->minimum();

        return Lecturer::query()->whereIn('id', $rows->keys())->with('studyProgram:id,name,degree,code')->get()
            ->map(function (Lecturer $lecturer) use ($rows, $minimum): array {
                $row = $rows[$lecturer->id];
                $sufficient = (int) $row->n >= $minimum;
                $score = $sufficient ? round((float) $row->score, 2) : null;

                return [
                    'id' => $lecturer->id,
                    'name' => $lecturer->full_name,
                    'study_program' => $lecturer->studyProgram?->full_name,
                    'responses' => (int) $row->n,
                    'classes' => (int) $row->classes,
                    'sufficient' => $sufficient,
                    'score' => $score,
                    'classification' => $this->classify($score),
                ];
            })
            ->sortBy([['sufficient', 'desc'], ['score', 'desc']])
            ->values()
            ->all();
    }

    /**
     * Skor per kelas untuk dosen tertentu.
     *
     * @return list<array<string, mixed>>
     */
    public function byClass(): array
    {
        $minimum = $this->minimum();

        return $this->responses()
            ->join('course_classes', 'course_classes.id', '=', 'responses.course_class_id')
            ->join('courses', 'courses.id', '=', 'course_classes.course_id')
            ->selectRaw('course_classes.id, courses.code, courses.name, course_classes.code as class_code, count(*) as n, avg(responses.score) as score')
            ->groupBy('course_classes.id', 'courses.code', 'courses.name', 'course_classes.code')
            ->orderBy('courses.name')
            ->get()
            ->map(function (object $row) use ($minimum): array {
                $sufficient = (int) $row->n >= $minimum;
                $score = $sufficient ? round((float) $row->score, 2) : null;

                return [
                    'id' => (int) $row->id,
                    'course' => $row->name,
                    'course_code' => $row->code,
                    'class_code' => $row->class_code,
                    'responses' => (int) $row->n,
                    'sufficient' => $sufficient,
                    'score' => $score,
                    'classification' => $this->classify($score),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{id: int, code: string, title: string, score: float|null, responses: int}>
     */
    public function bySection(): array
    {
        if ($this->isSuppressed()) {
            return [];
        }

        return $this->answers()
            ->join('instrument_sections', 'instrument_sections.id', '=', 'instrument_questions.instrument_section_id')
            ->whereNotNull('response_answers.score')
            ->selectRaw('instrument_sections.id, instrument_sections.code, instrument_sections.title, instrument_sections.sort_order, avg(response_answers.score) as score, count(distinct response_answers.response_id) as n')
            ->groupBy('instrument_sections.id', 'instrument_sections.code', 'instrument_sections.title', 'instrument_sections.sort_order')
            ->orderBy('instrument_sections.sort_order')
            ->get()
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'title' => $row->title,
                'score' => round((float) $row->score, 2),
                'responses' => (int) $row->n,
            ])
            ->all();
    }

    /**
     * Skor & sebaran jawaban per butir.
     *
     * @return list<array<string, mixed>>
     */
    public function byQuestion(): array
    {
        if ($this->isSuppressed()) {
            return [];
        }

        $scores = $this->answers()
            ->whereNotNull('response_answers.score')
            ->selectRaw('response_answers.instrument_question_id as id, avg(response_answers.score) as score, count(*) as n')
            ->groupBy('response_answers.instrument_question_id')
            ->get()
            ->keyBy('id');

        $distribution = $this->answers()
            ->whereNotNull('response_answers.instrument_question_option_id')
            ->selectRaw('response_answers.instrument_question_id as question_id, response_answers.instrument_question_option_id as option_id, count(*) as n')
            ->groupBy('response_answers.instrument_question_id', 'response_answers.instrument_question_option_id')
            ->get()
            ->groupBy('question_id');

        return $this->survey->instrumentVersion->questions()
            ->with(['options', 'section:id,code,title,sort_order'])
            ->whereIn('type', [QuestionType::Likert, QuestionType::SingleChoice, QuestionType::YesNo, QuestionType::Rating, QuestionType::Numeric, QuestionType::MultipleChoice])
            ->get()
            ->sortBy([fn (InstrumentQuestion $a, InstrumentQuestion $b) => [$a->section?->sort_order, $a->sort_order] <=> [$b->section?->sort_order, $b->sort_order]])
            ->map(function (InstrumentQuestion $question) use ($scores, $distribution): array {
                $counts = ($distribution[$question->id] ?? collect())->pluck('n', 'option_id');
                $total = $counts->sum();
                $score = isset($scores[$question->id]) ? round((float) $scores[$question->id]->score, 2) : null;

                return [
                    'id' => $question->id,
                    'code' => $question->code,
                    'label' => $question->label,
                    'indicator' => $question->indicator,
                    'section' => $question->section?->code,
                    'section_title' => $question->section?->title,
                    'weight' => $question->weight,
                    'score' => $score,
                    'classification' => $this->classify($score),
                    'responses' => (int) ($scores[$question->id]->n ?? $total),
                    'distribution' => $question->options->map(fn ($option): array => [
                        'label' => $option->label,
                        'value' => $option->value,
                        'count' => (int) ($counts[$option->id] ?? 0),
                        'percent' => $total > 0 ? round(($counts[$option->id] ?? 0) / $total * 100, 1) : 0,
                    ])->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Matriks prodi × bagian instrumen (heatmap).
     *
     * @return array{sections: list<array{code: string, title: string}>, rows: list<array{id: int, name: string, cells: array<string, float|null>}>}
     */
    public function heatmap(): array
    {
        $rows = $this->answers()
            ->join('instrument_sections', 'instrument_sections.id', '=', 'instrument_questions.instrument_section_id')
            ->whereNotNull('response_answers.score')
            ->selectRaw('responses.study_program_id, instrument_sections.code, avg(response_answers.score) as score')
            ->groupBy('responses.study_program_id', 'instrument_sections.code')
            ->get();

        $sections = $this->survey->instrumentVersion->sections()->whereHas('questions', fn ($q) => $q->where('is_scored', true))->get(['code', 'title']);
        $programs = StudyProgram::query()->whereIn('id', $rows->pluck('study_program_id')->unique())->orderBy('name')->get();

        return [
            'sections' => $sections->map->only(['code', 'title'])->values()->all(),
            'rows' => $programs->map(fn (StudyProgram $program): array => [
                'id' => $program->id,
                'name' => $program->full_name,
                'cells' => $sections->mapWithKeys(fn ($section): array => [
                    $section->code => ($value = $rows->first(fn ($row) => (int) $row->study_program_id === $program->id && $row->code === $section->code)?->score) !== null
                        ? round((float) $value, 2)
                        : null,
                ])->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Komentar terbuka. Hanya butir yang boleh dilihat evaluatee bila `$forEvaluatee` true.
     *
     * @return list<array{question: string, text: string}>
     */
    public function comments(bool $forEvaluatee = false, int $limit = 60): array
    {
        if ($this->isSuppressed() || ($forEvaluatee && ! Settings::get('monev.show_comments_to_lecturer', true))) {
            return [];
        }

        return $this->answers()
            ->whereIn('instrument_questions.type', [QuestionType::Text->value, QuestionType::LongText->value])
            ->whereNotNull('response_answers.value_text')
            ->when($forEvaluatee, fn (Builder $q) => $q->where('instrument_questions.visible_to_evaluatee', true))
            ->select('instrument_questions.label as question', 'response_answers.value_text as text')
            ->inRandomOrder()
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => ['question' => $row->question, 'text' => $row->text])
            ->all();
    }

    /**
     * Tren skor lintas kegiatan dengan instrumen & jenis yang sama (termasuk versi berbeda).
     *
     * @return list<array{survey_id: int, label: string, score: float|null, responses: int}>
     */
    public function trend(): array
    {
        $instrumentId = $this->survey->instrumentVersion->instrument_id;
        $minimum = $this->minimum();

        $surveys = Survey::query()
            ->where('mode', $this->survey->mode)
            ->whereHas('instrumentVersion', fn ($q) => $q->where('instrument_id', $instrumentId))
            ->where('status', '!=', 'draft')
            ->with('academicPeriod')
            ->orderBy('starts_at')
            ->get();

        return $surveys->map(function (Survey $survey) use ($minimum): array {
            $row = DB::table('responses')
                ->where('survey_id', $survey->id)
                ->whereNull('voided_at')
                ->when($this->programIds !== null, fn (Builder $q) => $q->whereIn('study_program_id', $this->programIds))
                ->when($this->lecturerId !== null, fn (Builder $q) => $q->where('lecturer_id', $this->lecturerId))
                ->selectRaw('count(*) as n, avg(score) as score')
                ->first();

            $suppressed = $this->lecturerId !== null && (int) $row->n < $minimum;

            return [
                'survey_id' => $survey->id,
                'label' => $survey->academicPeriod?->name ?? $survey->starts_at->translatedFormat('M Y'),
                'score' => $suppressed || $row->score === null ? null : round((float) $row->score, 2),
                'responses' => (int) $row->n,
            ];
        })->values()->all();
    }

    /**
     * @return array{id: int, name: string, scale_min: float, scale_max: float, classes: list<array<string, mixed>>}|null
     */
    public function schemePayload(): ?array
    {
        return $this->scheme ? [
            'id' => $this->scheme->id,
            'name' => $this->scheme->name,
            'scale_min' => $this->scheme->scale_min,
            'scale_max' => $this->scheme->scale_max,
            'classes' => $this->scheme->classifications->map->only(['label', 'min_score', 'max_score', 'color'])->values()->all(),
        ] : null;
    }

    /**
     * @return array{label: string, color: string}|null
     */
    public function classify(?float $score): ?array
    {
        $class = $this->scheme?->classify($score);

        return $class ? ['label' => $class->label, 'color' => $class->color] : null;
    }

    private function isSuppressed(): bool
    {
        return $this->lecturerId !== null && $this->responses()->count() < $this->minimum();
    }

    private function responses(): Builder
    {
        return DB::table('responses')
            ->where('responses.survey_id', $this->survey->id)
            ->whereNull('responses.voided_at')
            ->when($this->programIds !== null, fn (Builder $q) => $q->whereIn('responses.study_program_id', $this->programIds))
            ->when($this->lecturerId !== null, fn (Builder $q) => $q->where('responses.lecturer_id', $this->lecturerId));
    }

    private function answers(): Builder
    {
        return $this->responses()
            ->join('response_answers', 'response_answers.response_id', '=', 'responses.id')
            ->join('instrument_questions', 'instrument_questions.id', '=', 'response_answers.instrument_question_id');
    }

    /**
     * @return Collection<int, int>
     */
    public function lowScoringQuestionIds(float $threshold): Collection
    {
        return collect($this->byQuestion())->filter(fn (array $q): bool => $q['score'] !== null && $q['score'] < $threshold)->pluck('id');
    }
}
