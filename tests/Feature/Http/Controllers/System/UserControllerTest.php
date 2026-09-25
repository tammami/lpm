<?php

use App\Enums\UserRole;
use App\Models\StudyProgram;
use App\Models\User;

it('creates an admin prodi bound to a study program', function () {
    $program = StudyProgram::factory()->create();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('users.store'), [
            'name' => 'Admin Baru',
            'email' => 'admin.baru@example.test',
            'roles' => ['admin_prodi'],
            'study_program_id' => $program->id,
            'is_active' => true,
            'password' => 'rahasia123',
        ])
        ->assertRedirect();

    $user = User::query()->where('email', 'admin.baru@example.test')->firstOrFail();

    expect($user->hasRole('admin_prodi'))->toBeTrue()
        ->and($user->accessibleStudyProgramIds())->toBe([$program->id])
        ->and($user->must_change_password)->toBeTrue();
});

it('forbids an LPM admin from granting the superadmin role', function () {
    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('users.store'), ['name' => 'X', 'email' => 'x@example.test', 'roles' => ['superadmin']])
        ->assertForbidden();
});

it('prevents a superadmin from demoting itself', function () {
    $admin = userWithRole(UserRole::Superadmin);

    $this->actingAs($admin)->put(route('users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'roles' => ['admin_lpm'],
        'is_active' => true,
    ]);

    expect($admin->fresh()->hasRole('superadmin'))->toBeTrue();
});
