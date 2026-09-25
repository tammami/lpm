<?php

use App\Enums\QuestionType;
use App\Enums\SurveyStatus;
use App\Enums\UserRole;
use App\Models\CourseClass;
use App\Models\InstrumentVersion;
use App\Models\Lecturer;
use App\Models\Response;
use App\Models\StudyProgram;
use App\Models\Survey;
use App\Models\TeachingAssignment;
use Database\Seeders\ReferenceDataSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(ReferenceDataSeeder::class));

/**
 * Buat survei tertutup dengan sejumlah respons untuk satu dosen.
 *
 * @return array{survey: Survey, lecturer: Lecturer, program: StudyProgram}
 */
function surveyWithResponses(int $responses, float $score = 3.5, ?string $comment = null, bool $commentVisible = true): array
{
    $version = InstrumentVersion::factory()->published()->withLikertQuestions([1])->create();
    $class = CourseClass::factory()->create();
    $program = $class->course->studyProgram;
    $lecturer = Lecturer::factory()->for($program)->create();
    $assignment = TeachingAssignment::factory()->create(['course_class_id' => $class->id, 'lecturer_id' => $lecturer->id]);
    $survey = Survey::factory()->create(['instrument_version_id' => $version->id, 'academic_period_id' => $class->academic_period_id, 'status' => SurveyStatus::Closed, 'min_responses' => 5]);

    $likert = $version->questions()->where('type', QuestionType::Likert)->with('options')->first();
    $text = $version->questions()->where('type', QuestionType::LongText)->first();
    $text->update(['visible_to_evaluatee' => $commentVisible]);

    foreach (range(1, $responses) as $index) {
        $response = Response::query()->create([
            'survey_id' => $survey->id,
            'instrument_version_id' => $version->id,
            'teaching_assignment_id' => $assignment->id,
            'course_class_id' => $class->id,
            'lecturer_id' => $lecturer->id,
            'study_program_id' => $program->id,
            'scoring_method' => 'average',
            'score' => $score,
            'submitted_on' => now()->toDateString(),
        ]);
        $response->answers()->create(['instrument_question_id' => $likert->id, 'instrument_question_option_id' => $likert->options->first()->id, 'score' => $score]);

        if ($comment) {
            $response->answers()->create(['instrument_question_id' => $text->id, 'value_text' => $comment]);
        }
    }

    return compact('survey', 'lecturer', 'program');
}

it('hides a lecturer score when responses are below the minimum threshold', function () {
    ['survey' => $survey] = surveyWithResponses(3);

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('analytics.index', ['survey' => $survey->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('byLecturer.0.sufficient', false)
            ->where('byLecturer.0.score', null)
            ->where('summary.responses', 3));
});

it('shows the lecturer score once the threshold is reached', function () {
    ['survey' => $survey] = surveyWithResponses(5, 3.5);

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('analytics.index', ['survey' => $survey->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('byLecturer.0.sufficient', true)
            ->where('byLecturer.0.score', 3.5)
            ->where('byLecturer.0.classification.label', 'Sangat Baik'));
});

it('forbids an admin prodi from filtering analytics by another prodi', function () {
    ['survey' => $survey, 'program' => $program] = surveyWithResponses(5);
    $admin = userWithRole(UserRole::AdminProdi, ['study_program_id' => StudyProgram::factory()->create()->id]);

    $this->actingAs($admin)
        ->get(route('analytics.index', ['survey' => $survey->id, 'study_program_id' => $program->id]))
        ->assertForbidden();
});

it('forbids an admin prodi from opening a lecturer outside its scope', function () {
    ['lecturer' => $lecturer] = surveyWithResponses(5);
    $admin = userWithRole(UserRole::AdminProdi, ['study_program_id' => StudyProgram::factory()->create()->id]);

    $this->actingAs($admin)->get(route('analytics.lecturers.show', $lecturer))->assertForbidden();
});

it('shows a lecturer only the comments allowed for evaluatees', function () {
    ['lecturer' => $lecturer] = surveyWithResponses(5, 3.0, 'Komentar rahasia untuk LPM', commentVisible: false);
    $user = userWithRole(UserRole::Dosen);
    $lecturer->update(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('my-evaluation.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/lecturer')
            ->where('summary.score', 3)
            ->has('comments', 0));
});

it('keeps lecturers away from the institutional analytics page', function () {
    $this->actingAs(userWithRole(UserRole::Dosen))->get(route('analytics.index'))->assertForbidden();
});
