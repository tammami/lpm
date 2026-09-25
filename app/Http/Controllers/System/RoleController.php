<?php

namespace App\Http\Controllers\System;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::query()->with('permissions:id,name')->withCount('users')->get()->keyBy('name');

        return Inertia::render('system/roles', [
            'roles' => collect(UserRole::cases())->map(fn (UserRole $role): array => [
                'name' => $role->value,
                'label' => $role->label(),
                'users_count' => $roles[$role->value]->users_count ?? 0,
                'permissions' => $role === UserRole::Superadmin ? Permissions::all() : ($roles[$role->value]?->permissions->pluck('name')->all() ?? []),
                'locked' => $role === UserRole::Superadmin,
                'scope' => match ($role) {
                    UserRole::Superadmin, UserRole::AdminLpm, UserRole::Pimpinan => 'Seluruh institusi',
                    UserRole::AdminFakultas => 'Prodi dalam fakultasnya',
                    UserRole::AdminProdi => 'Prodinya sendiri',
                    UserRole::Auditor => 'Audit yang ditugaskan',
                    UserRole::Dosen => 'Data dirinya',
                    UserRole::Mahasiswa => 'Pengisian Monev',
                },
            ])->all(),
            'groups' => collect(Permissions::grouped())->map(fn (array $permissions, string $group): array => [
                'group' => $group,
                'permissions' => collect($permissions)->map(fn (string $label, string $name): array => ['name' => $name, 'label' => $label])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        abort_if($role === UserRole::Superadmin->value, 403, 'Hak akses superadmin tidak dapat diubah.');

        $model = Role::findByName($role, 'web');
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);

        $before = $model->permissions->pluck('name')->sort()->values()->all();
        $model->syncPermissions($validated['permissions'] ?? []);
        $after = collect($validated['permissions'] ?? [])->sort()->values()->all();

        AuditLogger::log('permission_changed', 'roles', $model, "Mengubah hak akses peran {$role}", ['permissions' => $before], ['permissions' => $after]);
        $this->toast('Hak akses peran diperbarui.');

        return back();
    }

    public function reset(string $role): RedirectResponse
    {
        $userRole = UserRole::from($role);
        Role::findByName($role, 'web')->syncPermissions(Permissions::defaultsFor($userRole));
        AuditLogger::log('permission_changed', 'roles', null, "Mengembalikan hak akses bawaan peran {$role}");
        $this->toast('Hak akses dikembalikan ke bawaan.');

        return back();
    }
}
