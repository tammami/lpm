<?php

use App\Enums\UserRole;
use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->programA = StudyProgram::factory()->create();
    $this->programB = StudyProgram::factory()->create();
    $this->adminA = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->programA->id]);
});

it('lists only lecturers inside the admin prodi scope', function () {
    Lecturer::factory()->for($this->programA)->create(['name' => 'Dosen Prodi A']);
    Lecturer::factory()->for($this->programB)->create(['name' => 'Dosen Prodi B']);

    $this->actingAs($this->adminA)
        ->get(route('lecturers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('master/lecturers')
            ->has('lecturers.data', 1)
            ->where('lecturers.data.0.name', 'Dosen Prodi A'));
});

it('forbids an admin prodi from updating a lecturer of another prodi', function () {
    $lecturer = Lecturer::factory()->for($this->programB)->create();

    $this->actingAs($this->adminA)
        ->put(route('lecturers.update', $lecturer), [
            'study_program_id' => $this->programB->id,
            'name' => 'Diubah',
            'employment_status' => 'tetap',
        ])
        ->assertForbidden();

    expect($lecturer->fresh()->name)->not->toBe('Diubah');
});

it('forbids an admin prodi from moving a lecturer into another prodi', function () {
    $this->actingAs($this->adminA)
        ->post(route('lecturers.store'), [
            'study_program_id' => $this->programB->id,
            'name' => 'Dosen Baru',
            'employment_status' => 'tetap',
        ])
        ->assertForbidden();

    expect(Lecturer::query()->where('name', 'Dosen Baru')->exists())->toBeFalse();
});

it('creates a lecturer with a login account that must change its password', function () {
    $this->actingAs($this->adminA)
        ->post(route('lecturers.store'), [
            'study_program_id' => $this->programA->id,
            'nidn' => '0812345678',
            'name' => 'Siti Aminah',
            'back_title' => 'M.Pd.',
            'employment_status' => 'tetap',
            'create_account' => true,
        ])
        ->assertRedirect();

    $user = User::query()->where('username', '0812345678')->firstOrFail();

    expect($user->hasRole('dosen'))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue()
        ->and(Lecturer::query()->where('nidn', '0812345678')->value('user_id'))->toBe($user->id);
});

it('forbids students from opening master data', function () {
    $this->actingAs(userWithRole(UserRole::Mahasiswa))
        ->get(route('lecturers.index'))
        ->assertForbidden();
});

it('still creates the login account when the lecturer email already belongs to another user', function () {
    User::factory()->create(['email' => 'bersama@iaia.ac.id']);

    $this->actingAs($this->adminA)
        ->post(route('lecturers.store'), [
            'study_program_id' => $this->programA->id,
            'nidn' => '0812345678',
            'name' => 'Ahmad Fauzi',
            'employment_status' => 'tetap',
            'email' => 'bersama@iaia.ac.id',
            'create_account' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Lecturer::query()->where('nidn', '0812345678')->value('email'))->toBe('bersama@iaia.ac.id')
        ->and(User::query()->where('username', '0812345678')->value('email'))->toBe('0812345678@users.simutu.local');
});
