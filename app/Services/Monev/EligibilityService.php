<?php

namespace App\Services\Monev;

use App\Enums\RespondentType;
use App\Enums\StudentStatus;
use App\Enums\SurveyMode;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menentukan siapa yang wajib/boleh mengisi sebuah survei (BR-001).
 *
 * - Evaluasi pembelajaran: mahasiswa aktif × dosen pengampu pada kelas yang diikutinya
 *   di periode survei. Mahasiswa tidak memilih dosen secara bebas.
 * - Survei umum: setiap responden (mahasiswa/dosen) dalam cakupan prodi, satu kali.
 */
class EligibilityService
{
    /**
     * Target yang dapat diisi seorang pengguna pada sebuah survei.
     *
     * @return Collection<int, array{target_key: string, teaching_assignment_id: int|null, course: string|null, course_code: string|null, class_code: string|null, lecturer: string|null, study_program_id: int|null}>
     */
    public function targetsFor(Survey $survey, User $user): Collection
    {
        if ($survey->mode === SurveyMode::TeachingEvaluation) {
            $student = $user->student;

            if (! $student || $survey->respondent_type !== RespondentType::Mahasiswa || $student->status !== StudentStatus::Aktif) {
                return collect();
            }

            return $this->teachingAssignmentsQuery($survey)
                ->whereHas('courseClass.students', fn (Builder $query) => $query->whereKey($student->id))
                ->with(['courseClass.course', 'lecturer'])
                ->get()
                ->map(fn (TeachingAssignment $assignment): array => [
                    'target_key' => SurveyParticipation::targetKeyFor($assignment->id),
                    'teaching_assignment_id' => $assignment->id,
                    'course' => $assignment->courseClass->course->name,
                    'course_code' => $assignment->courseClass->course->code,
                    'class_code' => $assignment->courseClass->code,
                    'lecturer' => $assignment->lecturer->full_name,
                    'study_program_id' => $assignment->courseClass->course->study_program_id,
                ])
                ->sortBy(['course', 'lecturer'])
                ->values();
        }

        $studyProgramId = $this->respondentStudyProgramId($survey, $user);

        if ($studyProgramId === false) {
            return collect();
        }

        return collect([[
            'target_key' => SurveyParticipation::targetKeyFor(null),
            'teaching_assignment_id' => null,
            'course' => null,
            'course_code' => null,
            'class_code' => null,
            'lecturer' => null,
            'study_program_id' => $studyProgramId,
        ]]);
    }

    /**
     * @return array{target_key: string, teaching_assignment_id: int|null, course: string|null, course_code: string|null, class_code: string|null, lecturer: string|null, study_program_id: int|null}|null
     */
    public function findTarget(Survey $survey, User $user, ?int $teachingAssignmentId): ?array
    {
        return $this->targetsFor($survey, $user)
            ->first(fn (array $target): bool => $target['teaching_assignment_id'] === $teachingAssignmentId);
    }

    /**
     * Jumlah pasangan responden-target yang eligible (penyebut response rate).
     */
    public function eligibleCount(Survey $survey): int
    {
        if ($survey->mode === SurveyMode::TeachingEvaluation) {
            return (int) $this->eligiblePairsQuery($survey)->count();
        }

        return $this->generalRespondentsQuery($survey)->count();
    }

    /**
     * ID pengguna yang wajib mengisi beserta jumlah target masing-masing.
     *
     * @return Collection<int, int> user_id => jumlah target
     */
    public function targetCountsByUser(Survey $survey): Collection
    {
        if ($survey->mode === SurveyMode::TeachingEvaluation) {
            return $this->eligiblePairsQuery($survey)
                ->select('students.user_id', DB::raw('count(*) as targets'))
                ->groupBy('students.user_id')
                ->pluck('targets', 'user_id')
                ->map(fn ($count): int => (int) $count);
        }

        return $this->generalRespondentsQuery($survey)->pluck('user_id')->mapWithKeys(fn ($id): array => [(int) $id => 1]);
    }

    /**
     * ID pengguna yang belum menyelesaikan seluruh target pengisian.
     *
     * @return Collection<int, int>
     */
    public function pendingUserIds(Survey $survey): Collection
    {
        $submitted = DB::table('survey_participations')
            ->where('survey_id', $survey->id)
            ->where('status', 'submitted')
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        return $this->targetCountsByUser($survey)
            ->filter(fn (int $targets, int $userId): bool => (int) ($submitted[$userId] ?? 0) < $targets)
            ->keys();
    }

    /**
     * Eligible dan jumlah terisi per penugasan mengajar.
     *
     * @return Collection<int, object{teaching_assignment_id: int, eligible: int}>
     */
    public function eligibleByAssignment(Survey $survey): Collection
    {
        return $this->eligiblePairsQuery($survey)
            ->select('teaching_assignments.id as teaching_assignment_id', DB::raw('count(*) as eligible'))
            ->groupBy('teaching_assignments.id')
            ->get();
    }

    /**
     * Eligible per prodi (prodi mata kuliah untuk evaluasi pembelajaran; prodi responden untuk survei umum).
     *
     * @return Collection<int, int> study_program_id => eligible
     */
    public function eligibleByStudyProgram(Survey $survey): Collection
    {
        if ($survey->mode === SurveyMode::TeachingEvaluation) {
            return $this->eligiblePairsQuery($survey)
                ->select('courses.study_program_id', DB::raw('count(*) as eligible'))
                ->groupBy('courses.study_program_id')
                ->pluck('eligible', 'study_program_id')
                ->map(fn ($count): int => (int) $count);
        }

        return $this->generalRespondentsQuery($survey)
            ->select('study_program_id', DB::raw('count(*) as eligible'))
            ->groupBy('study_program_id')
            ->pluck('eligible', 'study_program_id')
            ->map(fn ($count): int => (int) $count);
    }

    /**
     * @return Builder<TeachingAssignment>
     */
    private function teachingAssignmentsQuery(Survey $survey): Builder
    {
        $programIds = $survey->studyProgramIds();

        return TeachingAssignment::query()
            ->whereHas('courseClass', function (Builder $class) use ($survey, $programIds): void {
                $class->where('academic_period_id', $survey->academic_period_id)
                    ->when($programIds !== [], fn (Builder $query) => $query->whereHas(
                        'course', fn (Builder $course) => $course->whereIn('study_program_id', $programIds),
                    ));
            });
    }

    /**
     * Pasangan (mahasiswa aktif, penugasan mengajar) pada periode survei.
     */
    private function eligiblePairsQuery(Survey $survey): QueryBuilder
    {
        $programIds = $survey->studyProgramIds();

        return DB::table('teaching_assignments')
            ->join('course_classes', 'course_classes.id', '=', 'teaching_assignments.course_class_id')
            ->join('courses', 'courses.id', '=', 'course_classes.course_id')
            ->join('class_enrollments', 'class_enrollments.course_class_id', '=', 'course_classes.id')
            ->join('students', 'students.id', '=', 'class_enrollments.student_id')
            ->where('course_classes.academic_period_id', $survey->academic_period_id)
            ->where('students.status', StudentStatus::Aktif->value)
            ->whereNotNull('students.user_id')
            ->when($programIds !== [], fn (QueryBuilder $query) => $query->whereIn('courses.study_program_id', $programIds));
    }

    /**
     * @return Builder<Student>|Builder<Lecturer>
     */
    private function generalRespondentsQuery(Survey $survey): Builder
    {
        $programIds = $survey->studyProgramIds();

        $query = $survey->respondent_type === RespondentType::Dosen
            ? Lecturer::query()->where('is_active', true)
            : Student::query()->where('status', StudentStatus::Aktif);

        return $query->whereNotNull('user_id')
            ->when($programIds !== [], fn (Builder $q) => $q->whereIn('study_program_id', $programIds));
    }

    /**
     * Prodi responden untuk survei umum, atau `false` bila pengguna tidak termasuk sasaran.
     */
    private function respondentStudyProgramId(Survey $survey, User $user): int|false
    {
        $programIds = $survey->studyProgramIds();

        $profile = match ($survey->respondent_type) {
            RespondentType::Mahasiswa => $user->student?->status === StudentStatus::Aktif ? $user->student : null,
            RespondentType::Dosen => $user->lecturer?->is_active ? $user->lecturer : null,
            default => null,
        };

        if (! $profile || ($programIds !== [] && ! in_array($profile->study_program_id, $programIds, true))) {
            return false;
        }

        return $profile->study_program_id;
    }
}
