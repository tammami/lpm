<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use Spatie\Permission\Models\Role;

it('lets a superadmin change the permissions of a role and logs it', function () {
    $this->actingAs(userWithRole(UserRole::Superadmin))
        ->put(route('roles.update', 'pimpinan'), ['permissions' => ['dashboard.view', 'analytics.view']])
        ->assertRedirect();

    expect(Role::findByName('pimpinan')->permissions->pluck('name')->sort()->values()->all())->toBe(['analytics.view', 'dashboard.view'])
        ->and(AuditLog::query()->where('event', 'permission_changed')->exists())->toBeTrue();
});

it('refuses to edit the superadmin role', function () {
    $this->actingAs(userWithRole(UserRole::Superadmin))
        ->put(route('roles.update', 'superadmin'), ['permissions' => []])
        ->assertForbidden();
});

it('forbids an LPM admin from managing roles', function () {
    $this->actingAs(userWithRole(UserRole::AdminLpm))->get(route('roles.index'))->assertForbidden();
});
