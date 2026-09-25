<?php

use App\Enums\UserRole;
use App\Models\AuditLog;

it('signs in with email and redirects staff to the dashboard', function () {
    $user = userWithRole(UserRole::AdminLpm, ['email' => 'lpm@example.test']);

    $this->post(route('login'), ['login' => 'lpm@example.test', 'password' => 'password'])
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
    $this->get(route('home'))->assertRedirect(route('dashboard'));
    expect(AuditLog::query()->where('event', 'login')->where('user_id', $user->id)->exists())->toBeTrue();
});

it('signs in with a NIM username and redirects students to the portal', function () {
    $student = userWithRole(UserRole::Mahasiswa, ['username' => '2511001']);

    $this->post(route('login'), ['login' => '2511001', 'password' => 'password']);

    $this->assertAuthenticatedAs($student);
    $this->get(route('home'))->assertRedirect(route('portal.home'));
});

it('rejects a wrong password with a generic message', function () {
    userWithRole(UserRole::AdminLpm, ['email' => 'lpm@example.test']);

    $this->post(route('login'), ['login' => 'lpm@example.test', 'password' => 'wrong'])
        ->assertSessionHasErrors(['login' => 'Email/NIM/NIDN atau kata sandi tidak sesuai.']);

    $this->assertGuest();
    expect(AuditLog::query()->where('event', 'login_failed')->count())->toBe(1);
});

it('rejects a deactivated account', function () {
    userWithRole(UserRole::Dosen, ['username' => '0801010101', 'is_active' => false]);

    $this->post(route('login'), ['login' => '0801010101', 'password' => 'password'])
        ->assertSessionHasErrors(['login' => 'Akun Anda dinonaktifkan. Hubungi admin LPM.']);

    $this->assertGuest();
});

it('forces users with a default password to change it first', function () {
    $user = userWithRole(UserRole::Dosen, ['must_change_password' => true]);

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('profile.password'));
});

it('redirects guests to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
