<?php

use App\Enums\QuestionType;
use App\Enums\SurveyStatus;
use App\Enums\UserRole;
use App\Models\Course;
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
 * Isi respons evaluasi untuk satu dosen pada satu kelas.
 */
function respondToClass(Survey $survey, CourseClass $class, Lecturer $lecturer, int $count, float $score, ?string $comment = null): void
{
    $assignment = TeachingAssignment::query()->firstOrCreate(['course_class_id' => $class->id, 'lecturer_id' => $lecturer->id]);
    $likert = $survey->instrumentVersion->questions()->where('type', QuestionType::Likert)->with('options')->first();
    $text = $survey->instrumentVersion->questions()->where('type', QuestionType::LongText)->first();

    foreach (range(1, $count) as $index) {
        $response = Response::query()->create([
            'survey_id' => $survey->id,
            'instrument_version_id' => $survey->instrument_version_id,
            'teaching_assignment_id' => $assignment->id,
            'course_class_id' => $class->id,
            'lecturer_id' => $lecturer->id,
            'study_program_id' => $class->course->study_program_id,
            'scoring_method' => 'average',
            'score' => $score,
            'submitted_on' => now()->toDateString(),
        ]);
        $response->answers()->create(['instrument_question_id' => $likert->id, 'instrument_question_option_id' => $likert->options->first()->id, 'score' => $score]);

        if ($comment) {
            $response->answers()->create(['instrument_question_id' => $text->id, 'value_text' => $comment]);
        }
    }
}

/**
 * Satu MK yang diampu dua dosen (dua kelas) dalam kegiatan Monev yang sudah ditutup.
 *
 * @return array{survey: Survey, course: Course, program: StudyProgram, strong: Lecturer, weak: Lecturer, version: InstrumentVersion}
 */
function teamTaughtCourse(): array
{
    $version = InstrumentVersion::factory()->published()->withLikertQuestions([1])->create();
    $course = Course::factory()->create(['code' => 'MK101', 'name' => 'Statistika Dasar']);
    $program = $course->studyProgram;
    $survey = Survey::factory()->create(['instrument_version_id' => $version->id, 'status' => SurveyStatus::Closed, 'min_responses' => 5, 'starts_at' => now()->subMonth()]);
    $classA = CourseClass::factory()->create(['course_id' => $course->id, 'academic_period_id' => $survey->academic_period_id, 'code' => 'A']);
    $classB = CourseClass::factory()->create(['course_id' => $course->id, 'academic_period_id' => $survey->academic_period_id, 'code' => 'B']);
    $strong = Lecturer::factory()->for($program)->create(['name' => 'Dosen Kuat']);
    $weak = Lecturer::factory()->for($program)->create(['name' => 'Dosen Lemah']);

    respondToClass($survey, $classA, $strong, 5, 4.0, 'Penjelasan sangat jelas');
    respondToClass($survey, $classB, $weak, 3, 2.0);

    return compact('survey', 'course', 'program', 'strong', 'weak', 'version');
}

it('summarises each course with its lecturers and hides pairs below the response minimum', function () {
    ['survey' => $survey, 'strong' => $strong, 'weak' => $weak] = teamTaughtCourse();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('analytics.courses.index', ['survey' => $survey->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/courses')
            ->where('sections.0.code', 'A')
            ->has('courses', 1)
            ->where('courses.0.code', 'MK101')
            ->where('courses.0.responses', 8)
            ->where('courses.0.classes', 2)
            ->where('courses.0.score', 3.25)
            ->where('courses.0.sections.A', 3.25)
            ->has('courses.0.lecturers', 2)
            ->where('courses.0.lecturers.0.id', $strong->id)
            ->where('courses.0.lecturers.0.score', 4)
            ->where('courses.0.lecturers.0.class_codes', 'A')
            ->where('courses.0.lecturers.0.sections.A', 4)
            ->where('courses.0.lecturers.1.id', $weak->id)
            ->where('courses.0.lecturers.1.sufficient', false)
            ->where('courses.0.lecturers.1.score', null)
            ->where('courses.0.lecturers.1.sections.A', null));
});

it('compares each pair with the last period it was evaluated, skipping monev without that course', function () {
    ['survey' => $survey, 'course' => $course, 'strong' => $strong, 'version' => $version] = teamTaughtCourse();

    $earlier = Survey::factory()->create(['instrument_version_id' => $version->id, 'status' => SurveyStatus::Closed, 'min_responses' => 5, 'starts_at' => now()->subYear()]);
    $oldClass = CourseClass::factory()->create(['course_id' => $course->id, 'academic_period_id' => $earlier->academic_period_id, 'code' => 'A']);
    respondToClass($earlier, $oldClass, $strong, 6, 3.0);

    // Semester di antaranya hanya memuat MK lain (MK ganjil tidak ditawarkan di semester genap).
    $between = Survey::factory()->create(['instrument_version_id' => $version->id, 'status' => SurveyStatus::Closed, 'min_responses' => 5, 'starts_at' => now()->subMonths(6)]);
    $otherClass = CourseClass::factory()->create(['course_id' => Course::factory()->create(['study_program_id' => $course->study_program_id])->id, 'academic_period_id' => $between->academic_period_id]);
    respondToClass($between, $otherClass, $strong, 6, 2.0);

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('analytics.courses.index', ['survey' => $survey->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('courses.0.previous_score', 3)
            ->where('courses.0.previous_label', $earlier->academicPeriod->name)
            ->where('courses.0.lecturers.0.previous_score', 3)
            ->where('courses.0.lecturers.1.previous_score', null));

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('analytics.courses.show', ['course' => $course, 'survey' => $survey->id, 'lecturer' => $strong->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('benchmarks.2.label', "Terakhir dievaluasi ({$earlier->academicPeriod->name})")
            ->where('benchmarks.2.score', 3)
            ->has('trend', 2));
});

it('limits an admin prodi to the courses of its own study program', function () {
    ['survey' => $survey, 'program' => $program] = teamTaughtCourse();

    $otherCourse = Course::factory()->create();
    $otherClass = CourseClass::factory()->create(['course_id' => $otherCourse->id, 'academic_period_id' => $survey->academic_period_id]);
    respondToClass($survey, $otherClass, Lecturer::factory()->for($otherCourse->studyProgram)->create(), 5, 3.0);

    $this->actingAs(userWithRole(UserRole::AdminProdi, ['study_program_id' => $program->id]))
        ->get(route('analytics.courses.index', ['survey' => $survey->id]))
        ->assertInertia(fn (Assert $page) => $page->has('courses', 1)->where('courses.0.code', 'MK101'));

    $this->actingAs(userWithRole(UserRole::AdminProdi, ['study_program_id' => $program->id]))
        ->get(route('analytics.courses.show', ['course' => $otherCourse, 'survey' => $survey->id]))
        ->assertForbidden();
});

it('shows the detail of one course, optionally narrowed to one lecturer', function () {
    ['survey' => $survey, 'course' => $course, 'strong' => $strong, 'weak' => $weak] = teamTaughtCourse();
    $admin = userWithRole(UserRole::AdminLpm);

    $this->actingAs($admin)
        ->get(route('analytics.courses.show', ['course' => $course, 'survey' => $survey->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/course')
            ->where('lecturer', null)
            ->where('summary.score', 3.25)
            ->has('lecturers', 2)
            ->has('byClass', 2)
            ->where('byQuestion.0.score', 3.25)
            ->where('comments.0.text', 'Penjelasan sangat jelas'));

    $this->actingAs($admin)
        ->get(route('analytics.courses.show', ['course' => $course, 'survey' => $survey->id, 'lecturer' => $strong->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('lecturer', $strong->id)
            ->where('summary.score', 4)
            ->where('benchmarks.0.label', 'Rata-rata MK ini')
            ->where('benchmarks.0.score', 3.25)
            ->has('byClass', 1));

    $this->actingAs($admin)
        ->get(route('analytics.courses.show', ['course' => $course, 'survey' => $survey->id, 'lecturer' => $weak->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.suppressed', true)
            ->where('summary.score', null)
            ->has('byQuestion', 0)
            ->has('comments', 0));
});

it('ignores a lecturer who does not teach the course', function () {
    ['survey' => $survey, 'course' => $course] = teamTaughtCourse();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('analytics.courses.show', ['course' => $course, 'survey' => $survey->id, 'lecturer' => Lecturer::factory()->create()->id]))
        ->assertInertia(fn (Assert $page) => $page->where('lecturer', null)->where('summary.score', 3.25));
});

it('exports the lecturer-by-course recap to excel', function () {
    ['survey' => $survey] = teamTaughtCourse();

    $rows = downloadedRows($this->actingAs(userWithRole(UserRole::AdminLpm))->get(route('analytics.courses.export', ['survey' => $survey->id])));

    expect($rows[0][0])->toBe('Kode MK')
        ->and($rows)->toHaveCount(3)
        ->and($rows[1][4])->toStartWith('Dosen Kuat')
        ->and((float) $rows[1][8])->toBe(4.0)
        ->and((float) $rows[1][10])->toBe(0.75)
        ->and($rows[2][4])->toStartWith('Dosen Lemah')
        ->and($rows[2][9])->toBe('Respons < 5');
});

it('keeps lecturers and pimpinan-less roles away from the course analysis', function () {
    $this->actingAs(userWithRole(UserRole::Dosen))->get(route('analytics.courses.index'))->assertForbidden();
    $this->actingAs(userWithRole(UserRole::Lp2m))->get(route('analytics.courses.index'))->assertForbidden();
});
