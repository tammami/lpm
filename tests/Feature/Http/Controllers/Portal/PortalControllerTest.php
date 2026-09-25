<?php

use App\Enums\ParticipationStatus;
use App\Enums\UserRole;
use App\Models\CourseClass;
use App\Models\InstrumentVersion;
use App\Models\Response;
use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Siapkan kelas berisi satu mahasiswa yang dievaluasi oleh satu dosen.
 *
 * @return array{survey: Survey, assignment: TeachingAssignment, user: User, answers: array<int, int|string>}
 */
function enrolledStudentWithSurvey(array $weights = [1, 1, 1]): array
{
    $version = InstrumentVersion::factory()->published()->withLikertQuestions($weights)->create();
    $class = CourseClass::factory()->create();
    $assignment = TeachingAssignment::factory()->for($class, 'courseClass')->create();
    $survey = Survey::factory()->create(['instrument_version_id' => $version->id, 'academic_period_id' => $class->academic_period_id]);

    $user = userWithRole(UserRole::Mahasiswa);
    $student = Student::factory()->create(['user_id' => $user->id, 'study_program_id' => $class->course->study_program_id]);
    $class->students()->attach($student);

    $likert = $version->questions()->where('type', 'likert')->with('options')->get();
    $answers = $likert->mapWithKeys(fn ($question) => [$question->id => $question->options->firstWhere('score', 4.0)->id])->all();

    return compact('survey', 'assignment', 'user', 'answers');
}

it('stores an anonymous response and marks the participation as submitted', function () {
    ['survey' => $survey, 'assignment' => $assignment, 'user' => $user, 'answers' => $answers] = enrolledStudentWithSurvey();

    $this->actingAs($user)
        ->post(route('portal.surveys.submit', $survey), ['target' => $assignment->id, 'answers' => $answers])
        ->assertRedirect(route('portal.home'));

    $response = Response::query()->sole();

    expect($response->respondent_user_id)->toBeNull()
        ->and($response->lecturer_id)->toBe($assignment->lecturer_id)
        ->and($response->score)->toBe(4.0)
        ->and(SurveyParticipation::query()->sole()->status)->toBe(ParticipationStatus::Submitted);
});

it('keeps the responses table free of any column pointing to the respondent', function () {
    $columns = Schema::getColumnListing('responses');

    expect($columns)->not->toContain('user_id')
        ->not->toContain('student_id')
        ->not->toContain('survey_participation_id')
        ->not->toContain('created_at');
});

it('rejects a student who is not enrolled in the evaluated class', function () {
    ['survey' => $survey, 'assignment' => $assignment, 'answers' => $answers] = enrolledStudentWithSurvey();
    $outsider = userWithRole(UserRole::Mahasiswa);
    Student::factory()->create(['user_id' => $outsider->id]);

    $this->actingAs($outsider)
        ->post(route('portal.surveys.submit', $survey), ['target' => $assignment->id, 'answers' => $answers])
        ->assertSessionHasErrors(['survey' => 'Anda tidak terdaftar sebagai responden untuk evaluasi ini.']);

    expect(Response::query()->count())->toBe(0);
});

it('refuses a second submission for the same lecturer', function () {
    ['survey' => $survey, 'assignment' => $assignment, 'user' => $user, 'answers' => $answers] = enrolledStudentWithSurvey();
    $payload = ['target' => $assignment->id, 'answers' => $answers];

    $this->actingAs($user)->post(route('portal.surveys.submit', $survey), $payload);
    $this->actingAs($user)->post(route('portal.surveys.submit', $survey), $payload)
        ->assertSessionHasErrors(['survey' => 'Evaluasi ini sudah Anda kirim dan tidak dapat diubah.']);

    expect(Response::query()->count())->toBe(1);
});

it('requires every mandatory question to be answered', function () {
    ['survey' => $survey, 'assignment' => $assignment, 'user' => $user, 'answers' => $answers] = enrolledStudentWithSurvey();
    $firstQuestion = array_key_first($answers);
    unset($answers[$firstQuestion]);

    $this->actingAs($user)
        ->post(route('portal.surveys.submit', $survey), ['target' => $assignment->id, 'answers' => $answers])
        ->assertSessionHasErrors(["answers.{$firstQuestion}" => 'Butir ini wajib diisi.']);
});

it('applies the weighted formula recorded on the instrument version', function () {
    ['survey' => $survey, 'assignment' => $assignment, 'user' => $user] = enrolledStudentWithSurvey([3, 1]);
    $survey->instrumentVersion->update(['scoring_method' => 'weighted']);
    [$heavy, $light] = $survey->instrumentVersion->questions()->where('type', 'likert')->with('options')->orderBy('sort_order')->get()->all();

    $this->actingAs($user)->post(route('portal.surveys.submit', $survey), [
        'target' => $assignment->id,
        'answers' => [
            $heavy->id => $heavy->options->firstWhere('score', 4.0)->id,
            $light->id => $light->options->firstWhere('score', 2.0)->id,
        ],
    ]);

    // (4×3 + 2×1) / (3+1) = 3.5
    expect(Response::query()->sole()->score)->toBe(3.5);
});

it('lets an admin reopen a submission which voids the old response and allows resubmission', function () {
    ['survey' => $survey, 'assignment' => $assignment, 'user' => $user, 'answers' => $answers] = enrolledStudentWithSurvey();
    $payload = ['target' => $assignment->id, 'answers' => $answers];
    $this->actingAs($user)->post(route('portal.surveys.submit', $survey), $payload);
    $participation = SurveyParticipation::query()->sole();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('surveys.participations.reopen', [$survey, $participation]), ['reason' => 'Salah memilih jawaban karena kendala perangkat'])
        ->assertRedirect();

    expect(Response::query()->valid()->count())->toBe(0)
        ->and($participation->fresh()->status)->toBe(ParticipationStatus::Reopened);

    $this->actingAs($user)->post(route('portal.surveys.submit', $survey), $payload)->assertRedirect(route('portal.home'));

    expect(Response::query()->valid()->count())->toBe(1)
        ->and(Response::query()->count())->toBe(2)
        ->and($participation->fresh()->submission_count)->toBe(2);
});

it('forbids an admin prodi from reopening submissions', function () {
    ['survey' => $survey, 'assignment' => $assignment, 'user' => $user, 'answers' => $answers] = enrolledStudentWithSurvey();
    $this->actingAs($user)->post(route('portal.surveys.submit', $survey), ['target' => $assignment->id, 'answers' => $answers]);

    $this->actingAs(userWithRole(UserRole::AdminProdi))
        ->post(route('surveys.participations.reopen', [$survey, SurveyParticipation::query()->sole()]), ['reason' => 'Alasan yang cukup panjang'])
        ->assertForbidden();
});

it('lists the evaluation targets of the student on the portal', function () {
    ['user' => $user, 'assignment' => $assignment] = enrolledStudentWithSurvey();

    $this->actingAs($user)
        ->get(route('portal.home'))
        ->assertInertia(fn ($page) => $page
            ->component('portal/home')
            ->has('surveys.0.targets', 1)
            ->where('surveys.0.targets.0.teaching_assignment_id', $assignment->id)
            ->where('surveys.0.targets.0.status', 'pending'));
});
