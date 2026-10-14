<?php

use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->programA = StudyProgram::factory()->create(['name' => 'Pendidikan Agama Islam', 'degree' => 'S1']);
    $this->programB = StudyProgram::factory()->create(['name' => 'Hukum Keluarga Islam', 'degree' => 'S1']);
    $this->period = AcademicPeriod::factory()->active()->create();
    $this->course = Course::factory()->for($this->programA)->create();
    $this->admin = userWithRole(UserRole::AdminLpm);
});

it('exposes lecturer options with prodi and NIDN so identical names can be told apart', function () {
    Lecturer::factory()->for($this->programA)->create(['name' => 'Ahmad Fauzi', 'nidn' => '0811111111', 'back_title' => null]);
    Lecturer::factory()->for($this->programB)->create(['name' => 'Ahmad Fauzi', 'nidn' => '0822222222', 'back_title' => null]);
    Student::factory()->for($this->programA)->count(2)->create(['entry_year' => 2024]);
    Student::factory()->for($this->programA)->create(['entry_year' => 2023]);

    $this->actingAs($this->admin)
        ->get(route('classes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('master/classes/index')
            ->has('lecturers', 2)
            ->where('lecturers.0.label', 'Ahmad Fauzi')
            ->where('lecturers.0.description', 'S1 Pendidikan Agama Islam · NIDN 0811111111')
            ->where('lecturers.0.study_program_id', $this->programA->id)
            ->where('lecturers.1.description', 'S1 Hukum Keluarga Islam · NIDN 0822222222')
            ->has('cohorts', 2)
            ->where('cohorts.0', ['study_program_id' => $this->programA->id, 'entry_year' => 2024, 'students_count' => 2])
            ->where('cohorts.1', ['study_program_id' => $this->programA->id, 'entry_year' => 2023, 'students_count' => 1]));
});

it('creates a class and enrolls the chosen cohort of the selected prodi', function () {
    $lecturer = Lecturer::factory()->for($this->programA)->create();
    Student::factory()->for($this->programA)->count(3)->create(['entry_year' => 2024]);
    Student::factory()->for($this->programA)->create(['entry_year' => 2024, 'status' => 'cuti']);
    Student::factory()->for($this->programA)->create(['entry_year' => 2023]);
    Student::factory()->for($this->programB)->create(['entry_year' => 2024]);

    $this->actingAs($this->admin)
        ->post(route('classes.store'), [
            'academic_period_id' => $this->period->id,
            'study_program_id' => $this->programA->id,
            'course_id' => $this->course->id,
            'code' => 'A',
            'capacity' => 40,
            'lecturer_ids' => [$lecturer->id],
            'entry_year' => 2024,
        ])
        ->assertRedirect();

    $class = CourseClass::query()->where('course_id', $this->course->id)->firstOrFail();
    expect($class->students()->count())->toBe(3)
        ->and($class->teachingAssignments()->where('lecturer_id', $lecturer->id)->value('role'))->toBe('koordinator');
});

it('creates a class without participants when no cohort is chosen', function () {
    Student::factory()->for($this->programA)->create(['entry_year' => 2024]);

    $this->actingAs($this->admin)
        ->post(route('classes.store'), [
            'academic_period_id' => $this->period->id,
            'study_program_id' => $this->programA->id,
            'course_id' => $this->course->id,
            'code' => 'B',
            'entry_year' => null,
        ])
        ->assertRedirect();

    expect(CourseClass::query()->where('code', 'B')->firstOrFail()->students()->count())->toBe(0);
});

it('rejects a course that does not belong to the selected prodi', function () {
    $this->actingAs($this->admin)
        ->from(route('classes.index'))
        ->post(route('classes.store'), [
            'academic_period_id' => $this->period->id,
            'study_program_id' => $this->programB->id,
            'course_id' => $this->course->id,
            'code' => 'A',
        ])
        ->assertSessionHasErrors(['course_id']);

    expect(CourseClass::query()->count())->toBe(0);
});

it('scopes cohorts to the admin prodi', function () {
    Student::factory()->for($this->programA)->create(['entry_year' => 2024]);
    Student::factory()->for($this->programB)->create(['entry_year' => 2024]);
    $adminProdi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->programA->id]);

    $this->actingAs($adminProdi)
        ->get(route('classes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cohorts', 1)
            ->where('cohorts.0.study_program_id', $this->programA->id));
});
