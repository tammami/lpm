<?php

namespace App\Services\Monev;

use App\Enums\ParticipationStatus;
use App\Enums\RespondentType;
use App\Enums\SurveyMode;
use App\Models\StudyProgram;
use App\Models\Survey;
use App\Models\TeachingAssignment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Statistik kelengkapan pengisian (response rate) untuk monitoring LPM.
 * Hanya menghitung status partisipasi — tidak menyentuh isi evaluasi.
 */
class SurveyProgress
{
    public function __construct(private EligibilityService $eligibility) {}

    /**
     * Ringkasan kelengkapan. Bila `$visibleProgramIds` diisi (mis. kaprodi), hanya prodi tersebut yang dihitung.
     *
     * @param  list<int>|null  $visibleProgramIds
     * @return array{eligible: int, submitted: int, rate: float|null}
     */
    public function summary(Survey $survey, ?array $visibleProgramIds = null): array
    {
        if ($visibleProgramIds !== null) {
            $rows = collect($this->byStudyProgram($survey, $visibleProgramIds));
            $eligible = (int) $rows->sum('eligible');
            $submitted = (int) $rows->sum('submitted');

            return ['eligible' => $eligible, 'submitted' => $submitted, 'rate' => $this->rate($submitted, $eligible)];
        }

        $eligible = $this->eligibility->eligibleCount($survey);
        $submitted = $this->submittedQuery($survey)->count();

        return ['eligible' => $eligible, 'submitted' => $submitted, 'rate' => $this->rate($submitted, $eligible)];
    }

    /**
     * Daftar orang (bukan pasangan) yang wajib mengisi beserta status kelengkapannya,
     * dikelompokkan menurut prodi asal responden. Hanya status — tanpa isi jawaban.
     *
     * @param  list<int>|null  $visibleProgramIds
     * @return Collection<int, array{user_id: int, name: string, identifier: string|null, study_program_id: int|null, targets: int, submitted: int, state: string}>
     */
    public function respondents(Survey $survey, ?array $visibleProgramIds = null): Collection
    {
        $targets = $this->eligibility->targetCountsByUser($survey);

        if ($targets->isEmpty()) {
            return collect();
        }

        $submitted = $this->submittedQuery($survey)
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $isLecturer = $survey->respondent_type === RespondentType::Dosen && $survey->mode !== SurveyMode::TeachingEvaluation;

        return DB::table($isLecturer ? 'lecturers' : 'students')
            ->whereIn('user_id', $targets->keys())
            ->when($visibleProgramIds !== null, fn (Builder $query) => $query->whereIn('study_program_id', $visibleProgramIds))
            ->orderBy('name')
            ->get(['user_id', 'name', $isLecturer ? 'nidn as identifier' : 'nim as identifier', 'study_program_id'])
            ->unique('user_id')
            ->map(function (object $row) use ($targets, $submitted): array {
                $targetCount = (int) $targets[$row->user_id];
                $submittedCount = min($targetCount, (int) ($submitted[$row->user_id] ?? 0));

                return [
                    'user_id' => (int) $row->user_id,
                    'name' => $row->name,
                    'identifier' => $row->identifier,
                    'study_program_id' => $row->study_program_id !== null ? (int) $row->study_program_id : null,
                    'targets' => $targetCount,
                    'submitted' => $submittedCount,
                    'state' => match (true) {
                        $submittedCount >= $targetCount => 'complete',
                        $submittedCount > 0 => 'partial',
                        default => 'none',
                    },
                ];
            })
            ->values();
    }

    /**
     * Jumlah responden (orang) per prodi asal: lengkap, sebagian, belum mengisi.
     *
     * @param  Collection<int, array<string, mixed>>  $respondents  Hasil `respondents()`.
     * @return list<array{study_program_id: int|null, name: string, total: int, complete: int, partial: int, none: int, rate: float|null}>
     */
    public function respondentsByStudyProgram(Collection $respondents): array
    {
        $names = StudyProgram::query()->whereIn('id', $respondents->pluck('study_program_id')->filter()->unique())->get()
            ->mapWithKeys(fn (StudyProgram $program): array => [$program->id => $program->full_name]);

        return $respondents->groupBy(fn (array $row): int => $row['study_program_id'] ?? 0)
            ->map(function (Collection $rows, int $programId) use ($names): array {
                $complete = $rows->where('state', 'complete')->count();

                return [
                    'study_program_id' => $programId ?: null,
                    'name' => $names[$programId] ?? 'Tanpa prodi',
                    'total' => $rows->count(),
                    'complete' => $complete,
                    'partial' => $rows->where('state', 'partial')->count(),
                    'none' => $rows->where('state', 'none')->count(),
                    'rate' => $this->rate($complete, $rows->count()),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $visibleProgramIds
     * @return list<array{study_program_id: int, name: string, eligible: int, submitted: int, rate: float|null}>
     */
    public function byStudyProgram(Survey $survey, ?array $visibleProgramIds = null): array
    {
        $eligible = $this->eligibility->eligibleByStudyProgram($survey);

        $submitted = $survey->mode === SurveyMode::TeachingEvaluation
            ? $this->submittedQuery($survey)
                ->join('teaching_assignments', 'teaching_assignments.id', '=', 'survey_participations.teaching_assignment_id')
                ->join('course_classes', 'course_classes.id', '=', 'teaching_assignments.course_class_id')
                ->join('courses', 'courses.id', '=', 'course_classes.course_id')
                ->select('courses.study_program_id', DB::raw('count(*) as total'))
                ->groupBy('courses.study_program_id')
                ->pluck('total', 'study_program_id')
            : $this->submittedQuery($survey)
                ->join($this->profileTable($survey), $this->profileTable($survey).'.user_id', '=', 'survey_participations.user_id')
                ->select($this->profileTable($survey).'.study_program_id', DB::raw('count(*) as total'))
                ->groupBy($this->profileTable($survey).'.study_program_id')
                ->pluck('total', 'study_program_id');

        $programs = StudyProgram::query()->whereIn('id', $eligible->keys())
            ->when($visibleProgramIds !== null, fn ($query) => $query->whereIn('id', $visibleProgramIds))
            ->orderBy('name')->get();

        return $programs->map(fn (StudyProgram $program): array => [
            'study_program_id' => $program->id,
            'name' => $program->full_name,
            'eligible' => (int) ($eligible[$program->id] ?? 0),
            'submitted' => (int) ($submitted[$program->id] ?? 0),
            'rate' => $this->rate((int) ($submitted[$program->id] ?? 0), (int) ($eligible[$program->id] ?? 0)),
        ])->values()->all();
    }

    /**
     * Progres per dosen-kelas (evaluasi pembelajaran).
     *
     * @param  list<int>|null  $visibleProgramIds
     * @return Collection<int, array<string, mixed>>
     */
    public function byAssignment(Survey $survey, ?array $visibleProgramIds = null): Collection
    {
        if ($survey->mode !== SurveyMode::TeachingEvaluation) {
            return collect();
        }

        $eligible = $this->eligibility->eligibleByAssignment($survey)->pluck('eligible', 'teaching_assignment_id');
        $submitted = $this->submittedQuery($survey)
            ->select('teaching_assignment_id', DB::raw('count(*) as total'))
            ->groupBy('teaching_assignment_id')
            ->pluck('total', 'teaching_assignment_id');

        $minimum = $survey->minimumResponses();

        return TeachingAssignment::query()
            ->whereIn('id', $eligible->keys())
            ->with(['lecturer', 'courseClass.course.studyProgram'])
            ->get()
            ->filter(fn (TeachingAssignment $assignment): bool => $visibleProgramIds === null
                || in_array($assignment->courseClass->course->study_program_id, $visibleProgramIds, true))
            ->map(function (TeachingAssignment $assignment) use ($eligible, $submitted, $minimum): array {
                $eligibleCount = (int) ($eligible[$assignment->id] ?? 0);
                $submittedCount = (int) ($submitted[$assignment->id] ?? 0);

                return [
                    'teaching_assignment_id' => $assignment->id,
                    'lecturer' => $assignment->lecturer->full_name,
                    'course' => $assignment->courseClass->course->name,
                    'course_code' => $assignment->courseClass->course->code,
                    'class_code' => $assignment->courseClass->code,
                    'study_program' => $assignment->courseClass->course->studyProgram->full_name,
                    'eligible' => $eligibleCount,
                    'submitted' => $submittedCount,
                    'rate' => $this->rate($submittedCount, $eligibleCount),
                    'sufficient' => $submittedCount >= $minimum,
                ];
            })
            ->sortBy([['rate', 'asc'], ['course', 'asc']])
            ->values();
    }

    private function submittedQuery(Survey $survey): Builder
    {
        return DB::table('survey_participations')
            ->where('survey_participations.survey_id', $survey->id)
            ->where('survey_participations.status', ParticipationStatus::Submitted->value);
    }

    private function profileTable(Survey $survey): string
    {
        return $survey->respondent_type === RespondentType::Dosen ? 'lecturers' : 'students';
    }

    private function rate(int $submitted, int $eligible): ?float
    {
        return $eligible > 0 ? round($submitted / $eligible * 100, 1) : null;
    }
}
