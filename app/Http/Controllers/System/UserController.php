<?php

namespace App\Http\Controllers\System;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::query()
            ->with(['roles:id,name', 'faculty:id,name', 'studyProgram:id,name,degree', 'unit:id,name'])
            ->when($request->filled('role') && $request->input('role') !== 'all', fn ($q) => $q->role($request->string('role')->toString()))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false));

        $users = TableQuery::for($query, $request)
            ->search(['name', 'email', 'username'])
            ->sort(['name', 'last_login_at', 'created_at'], 'name')
            ->paginate()
            ->through(fn (User $user): array => [
                ...$user->only(['id', 'name', 'username', 'email', 'phone', 'is_active', 'faculty_id', 'study_program_id', 'unit_id', 'must_change_password']),
                'roles' => $user->roles->pluck('name')->all(),
                'role_labels' => $user->roles->map(fn ($role) => UserRole::tryFrom($role->name)?->label() ?? $role->name)->all(),
                'scope' => $user->faculty?->name ?? $user->studyProgram?->full_name ?? $user->unit?->name,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('system/users', [
            'users' => $users,
            'filters' => $this->filters($request, ['role', 'status']),
            'roles' => UserRole::options(),
            'faculties' => Options::faculties(),
            'studyPrograms' => Options::studyPrograms($request->user(), activeOnly: false),
            'units' => Unit::query()->orderBy('name')->get(['id', 'name'])->map(fn (Unit $unit): array => ['value' => $unit->id, 'label' => $unit->name])->all(),
            'counts' => collect(UserRole::cases())->mapWithKeys(fn (UserRole $role): array => [$role->value => User::role($role->value)->count()])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $password = $validated['password'] ?? Str::password(10, symbols: false);

        $user = User::query()->create([
            ...collect($validated)->except(['roles', 'password'])->all(),
            'password' => $password,
            'must_change_password' => true,
        ]);
        $user->syncRoles($validated['roles']);

        AuditLogger::log('permission_changed', 'users', $user, "Membuat pengguna {$user->name} dengan peran ".implode(', ', $validated['roles']));
        $this->toast(isset($validated['password'])
            ? 'Pengguna dibuat. Pengguna wajib mengganti kata sandi saat login pertama.'
            : "Pengguna dibuat dengan kata sandi sementara: {$password}", isset($validated['password']) ? 'success' : 'info');

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validated($request, $user);
        $previousRoles = $user->getRoleNames()->sort()->values()->all();

        if ($user->is($request->user()) && ! in_array(UserRole::Superadmin->value, $validated['roles'], true) && $user->hasRole(UserRole::Superadmin->value)) {
            $this->failWith('Anda tidak dapat mencabut peran superadmin dari akun sendiri.');
        }

        if ($user->is($request->user()) && ! ($validated['is_active'] ?? true)) {
            $this->failWith('Anda tidak dapat menonaktifkan akun sendiri.');
        }

        $user->update([
            ...collect($validated)->except(['roles', 'password'])->all(),
            ...(isset($validated['password']) ? ['password' => $validated['password'], 'must_change_password' => true] : []),
        ]);
        $user->syncRoles($validated['roles']);

        $newRoles = collect($validated['roles'])->sort()->values()->all();

        if ($previousRoles !== $newRoles) {
            AuditLogger::log('permission_changed', 'users', $user, "Mengubah peran {$user->name}", ['roles' => $previousRoles], ['roles' => $newRoles]);
        }

        $this->toast('Pengguna diperbarui.');

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            $this->failWith('Anda tidak dapat menghapus akun sendiri.');
        }

        if ($user->lecturer()->exists() || $user->student()->exists()) {
            $user->update(['is_active' => false]);
            $this->toast('Akun terhubung dengan data dosen/mahasiswa sehingga dinonaktifkan, bukan dihapus.', 'info');

            return back();
        }

        $this->deleteSafely($user, 'Pengguna');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(UserRole::class)],
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'study_program_id' => ['nullable', 'exists:study_programs,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'is_active' => ['boolean'],
            'password' => ['nullable', Password::defaults()],
        ], ['roles.required' => 'Pilih minimal satu peran.', 'username.regex' => 'Nama pengguna hanya boleh berisi huruf, angka, titik, tanda hubung, dan garis bawah.']);

        if (in_array(UserRole::AdminFakultas->value, $validated['roles'], true) && empty($validated['faculty_id'])) {
            $this->failWith('Admin Fakultas wajib memiliki fakultas.');
        }

        if (in_array(UserRole::AdminProdi->value, $validated['roles'], true) && empty($validated['study_program_id'])) {
            $this->failWith('Admin Prodi wajib memiliki program studi.');
        }

        if (in_array(UserRole::Superadmin->value, $validated['roles'], true) && ! $request->user()->hasRole(UserRole::Superadmin->value)) {
            abort(403, 'Hanya superadmin yang dapat memberikan peran superadmin.');
        }

        return $validated;
    }
}
