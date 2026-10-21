<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CourseClass;
use App\Models\Evidence;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\Lecturer;
use App\Models\Response;
use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use App\Services\DataReset\DataResetService;
use App\Services\Evidence\EvidenceService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['database.backup.dump_binary' => 'mysqldump']);

    // Tiru mysqldump: tulis dump kosong ke berkas --result-file.
    Process::fake(function (PendingProcess $process) {
        $target = collect($process->command)->first(fn (string $argument): bool => str_starts_with($argument, '--result-file='));
        file_put_contents(substr($target, strlen('--result-file=')), "-- MySQL dump\n");

        return Process::result();
    });

    $this->superadmin = userWithRole(UserRole::Superadmin);
});

/**
 * Satu mahasiswa yang sudah mengisi Monev untuk satu dosen.
 *
 * @return array{student: Student, survey: Survey, assignment: TeachingAssignment}
 */
function studentWhoFilledMonev(): array
{
    $version = InstrumentVersion::factory()->published()->withLikertQuestions([1])->create();
    $class = CourseClass::factory()->create();
    $assignment = TeachingAssignment::factory()->for($class, 'courseClass')->create();
    $survey = Survey::factory()->create(['instrument_version_id' => $version->id, 'academic_period_id' => $class->academic_period_id]);

    $user = userWithRole(UserRole::Mahasiswa);
    $student = Student::factory()->create(['user_id' => $user->id, 'study_program_id' => $class->course->study_program_id]);
    $class->students()->attach($student);

    $question = $version->questions()->where('type', 'likert')->with('options')->sole();
    test()->actingAs($user)->post(route('portal.surveys.submit', $survey), [
        'target' => $assignment->id,
        'answers' => [$question->id => $question->options->firstWhere('score', 4.0)->id],
    ])->assertSessionHasNoErrors();

    return compact('student', 'survey', 'assignment');
}

it('lets only the superadmin preview or empty data', function () {
    $admin = userWithRole(UserRole::AdminLpm);

    $this->actingAs($admin)->getJson(route('data-reset.show', 'students'))->assertForbidden();
    $this->actingAs($admin)->delete(route('data-reset.destroy', 'students'), ['confirmation' => 'KOSONGKAN MAHASISWA'])->assertForbidden();
    $this->actingAs($this->superadmin)->getJson(route('data-reset.show', 'unknown'))->assertNotFound();
});

it('shares the emptiable datasets with the superadmin only', function () {
    $this->actingAs($this->superadmin)->get(route('students.index'))
        ->assertInertia(fn ($page) => $page->where('dataResets.students', 'Mahasiswa'));

    $this->actingAs(userWithRole(UserRole::AdminLpm))->get(route('students.index'))
        ->assertInertia(fn ($page) => $page->where('dataResets', null));
});

it('previews what emptying students deletes and detaches without changing anything', function () {
    studentWhoFilledMonev();

    $preview = $this->actingAs($this->superadmin)->getJson(route('data-reset.show', 'students'))->assertOk()->json();

    $deleted = collect($preview['deleted'])->pluck('count', 'label');
    expect($preview['phrase'])->toBe('KOSONGKAN MAHASISWA')
        ->and($deleted->all())->toMatchArray(['Mahasiswa' => 1, 'Akun pengguna' => 1, 'Peserta kelas' => 1, 'Status pengisian Monev' => 1, 'Peran pengguna' => 1])
        ->and($deleted)->not->toHaveKey('Respons Monev')
        ->and(Student::query()->count())->toBe(1);
});

it('refuses to empty data when the confirmation phrase does not match', function () {
    studentWhoFilledMonev();

    $this->actingAs($this->superadmin)
        ->delete(route('data-reset.destroy', 'students'), ['confirmation' => 'KOSONGKAN'])
        ->assertSessionHasErrors(['confirmation' => 'Ketik “KOSONGKAN MAHASISWA” persis untuk melanjutkan.']);

    expect(Student::query()->count())->toBe(1);
    Process::assertNothingRan();
});

it('backs up the database, then empties students and their own accounts', function () {
    ['student' => $student, 'assignment' => $assignment] = studentWhoFilledMonev();
    $studentUser = $student->user_id;

    $this->actingAs($this->superadmin)->from(route('students.index'))
        ->delete(route('data-reset.destroy', 'students'), ['confirmation' => ' kosongkan mahasiswa '])
        ->assertRedirect(route('students.index'));

    expect(Student::query()->count())->toBe(0)
        ->and(User::query()->whereKey($studentUser)->exists())->toBeFalse()
        ->and(SurveyParticipation::query()->count())->toBe(0)
        ->and(DB::table('class_enrollments')->count())->toBe(0)
        ->and(DB::table('model_has_roles')->where('model_id', $studentUser)->count())->toBe(0)
        // Data lain tetap utuh: kelas, penugasan dosen, dan jawaban anonim.
        ->and(TeachingAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue()
        ->and(Response::query()->count())->toBe(1)
        ->and(User::query()->whereKey($this->superadmin->id)->exists())->toBeTrue()
        ->and(app(DatabaseBackup::class)->all())->toHaveCount(1);

    $log = AuditLog::query()->where('event', 'emptied')->sole();
    expect($log->description)->toBe('Mengosongkan data Mahasiswa')
        ->and($log->new_values['deleted']['Mahasiswa'])->toBe(1)
        ->and($log->new_values['backup'])->toBe(app(DatabaseBackup::class)->all()[0]['name']);
});

it('keeps student accounts that also hold another role', function () {
    $user = userWithRole(UserRole::Mahasiswa);
    $user->assignRole(UserRole::Auditor->value);
    Student::factory()->create(['user_id' => $user->id]);

    $this->actingAs($this->superadmin)->delete(route('data-reset.destroy', 'students'), ['confirmation' => 'KOSONGKAN MAHASISWA']);

    expect(Student::query()->count())->toBe(0)
        ->and(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('removes surveys and responses that block emptying instruments', function () {
    ['assignment' => $assignment] = studentWhoFilledMonev();

    $preview = $this->actingAs($this->superadmin)->getJson(route('data-reset.show', 'instruments'))->json();
    expect(collect($preview['deleted'])->pluck('label'))->toContain('Kegiatan Monev', 'Respons Monev', 'Jawaban Monev');

    $this->actingAs($this->superadmin)->delete(route('data-reset.destroy', 'instruments'), ['confirmation' => 'KOSONGKAN INSTRUMEN'])
        ->assertSessionHasNoErrors();

    expect(Instrument::query()->count())->toBe(0)
        ->and(Survey::query()->count())->toBe(0)
        ->and(Response::query()->count())->toBe(0)
        ->and(CourseClass::query()->whereKey($assignment->course_class_id)->exists())->toBeTrue();
});

it('never deletes superadmin accounts when emptying users', function () {
    $lecturer = Lecturer::factory()->create(['user_id' => userWithRole(UserRole::Dosen)->id]);
    userWithRole(UserRole::AdminLpm);

    $this->actingAs($this->superadmin)->delete(route('data-reset.destroy', 'users'), ['confirmation' => 'KOSONGKAN PENGGUNA']);

    expect(User::query()->pluck('id')->all())->toBe([$this->superadmin->id])
        ->and($lecturer->fresh()->user_id)->toBeNull();
});

it('deletes the uploaded files of emptied evidence', function () {
    $evidence = app(EvidenceService::class)->create(['title' => 'SK Rektor'], $this->superadmin, UploadedFile::fake()->create('sk.pdf', 10, 'application/pdf'));
    $path = $evidence->versions()->sole()->file_path;
    Storage::disk('local')->assertExists($path);

    $preview = $this->actingAs($this->superadmin)->getJson(route('data-reset.show', 'evidence'))->json();
    expect($preview['files'])->toBe(1);

    $this->actingAs($this->superadmin)->delete(route('data-reset.destroy', 'evidence'), ['confirmation' => 'KOSONGKAN DOKUMEN BUKTI']);

    expect(Evidence::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

it('does not empty anything when the backup fails', function () {
    studentWhoFilledMonev();
    Process::fake(fn () => Process::result(errorOutput: 'Access denied', exitCode: 2));

    $this->actingAs($this->superadmin)->delete(route('data-reset.destroy', 'students'), ['confirmation' => 'KOSONGKAN MAHASISWA']);

    expect(Student::query()->count())->toBe(1);
});

it('can preview and empty every dataset on the demo data', function () {
    $this->seed(DatabaseSeeder::class);
    $resets = app(DataResetService::class);

    foreach ($resets->datasets() as $dataset) {
        expect($resets->preview($dataset)['total'])->toBeGreaterThanOrEqual(0);

        DB::beginTransaction();
        $resets->empty($dataset);
        foreach ($dataset->roots as $table => $scope) {
            if ($scope === null) {
                expect(DB::table($table)->count())->toBe(0, "{$dataset->key}: {$table}");
            }
        }
        DB::rollBack();
    }
});
