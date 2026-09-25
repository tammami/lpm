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
     * @return array{eligible: int, submitted: int, rate: float|null}
     */
    public function summary(Survey $survey): array
    {
        $eligible = $this->eligibility->eligibleCount($survey);
        $submitted = $this->submittedQuery($survey)->count();

        return ['eligible' => $eligible, 'submitted' => $submitted, 'rate' => $this->rate($submitted, $eligible)];
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
