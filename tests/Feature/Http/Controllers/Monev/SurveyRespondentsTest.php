<?php

use App\Enums\ParticipationStatus;
use App\Enums\UserRole;
use App\Models\CourseClass;
use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Models\TeachingAssignment;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Satu kelas dengan dua dosen pengampu dan tiga mahasiswa: lengkap, sebagian, belum.
 *
 * @return array{survey: Survey, class: CourseClass}
 */
function surveyWithMixedProgress(): array
{
    $class = CourseClass::factory()->create();
    [$first, $second] = TeachingAssignment::factory()->count(2)->for($class, 'courseClass')->create();
    $survey = Survey::factory()->create(['academic_period_id' => $class->academic_period_id]);
    $programId = $class->course->study_program_id;

    foreach (['Andi Lengkap' => [$first, $second], 'Budi Sebagian' => [$first], 'Citra Belum' => []] as $name => $done) {
        $user = userWithRole(UserRole::Mahasiswa);
        $student = Student::factory()->create(['user_id' => $user->id, 'study_program_id' => $programId, 'name' => $name]);
        $class->students()->attach($student);

        foreach ($done as $assignment) {
            SurveyParticipation::query()->create([
                'survey_id' => $survey->id,
                'user_id' => $user->id,
                'teaching_assignment_id' => $assignment->id,
                'target_key' => SurveyParticipation::targetKeyFor($assignment->id),
                'status' => ParticipationStatus::Submitted,
                'submitted_at' => now(),
                'submission_count' => 1,
            ]);
        }
    }

    return compact('survey', 'class');
}

it('counts complete, partial and missing respondents per study program', function () {
    ['survey' => $survey] = surveyWithMixedProgress();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('surveys.respondents', $survey))
        ->assertInertia(fn (Assert $page) => $page
            ->component('monev/surveys/respondents')
            ->where('totals', ['total' => 3, 'complete' => 1, 'partial' => 1, 'none' => 1])
            ->has('byStudyProgram', 1)
            ->where('byStudyProgram.0.none', 1));
});

it('lists only respondents who have not finished when filtered as pending', function () {
    ['survey' => $survey] = surveyWithMixedProgress();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('surveys.respondents', [$survey, 'state' => 'pending']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('respondents.data', 2)
            ->where('respondents.data.0.name', 'Budi Sebagian')
            ->where('respondents.data.1.name', 'Citra Belum'));
});

it('limits a kaprodi to respondents from their own study program', function () {
    ['survey' => $survey, 'class' => $class] = surveyWithMixedProgress();
    $otherClass = CourseClass::factory()->create(['academic_period_id' => $class->academic_period_id]);
    TeachingAssignment::factory()->for($otherClass, 'courseClass')->create();
    $outsider = Student::factory()->create(['user_id' => userWithRole(UserRole::Mahasiswa)->id, 'study_program_id' => $otherClass->course->study_program_id]);
    $otherClass->students()->attach($outsider);

    $kaprodi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $class->course->study_program_id]);

    $this->actingAs($kaprodi)
        ->get(route('surveys.respondents', $survey))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', 3)
            ->has('byStudyProgram', 1)
            ->where('byStudyProgram.0.study_program_id', $class->course->study_program_id));

    $this->actingAs($kaprodi)
        ->get(route('surveys.show', $survey))
        ->assertInertia(fn (Assert $page) => $page->where('progress.eligible', 6)->where('progress.submitted', 3));
});

it('exports the respondent status to excel', function () {
    ['survey' => $survey] = surveyWithMixedProgress();

    $rows = downloadedRows($this->actingAs(userWithRole(UserRole::AdminLpm))->get(route('surveys.respondents.export', [$survey, 'state' => 'none'])), 'Responden');

    expect($rows)->toHaveCount(2)->and($rows[1][0])->toBe('Citra Belum')->and($rows[1][3])->toBe('Belum mengisi');
});

it('requires the export permission to download the respondent list', function () {
    ['survey' => $survey] = surveyWithMixedProgress();
    $viewer = userWithRole(UserRole::Pimpinan);
    Role::findByName(UserRole::Pimpinan->value)->revokePermissionTo('reports.export');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($viewer)->get(route('surveys.respondents', $survey))->assertOk();
    $this->actingAs($viewer)->get(route('surveys.respondents.export', $survey))->assertForbidden();
});
