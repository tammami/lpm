<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Hak akses program kerja LPM, kotak saran, dan prestasi mahasiswa untuk peran yang sudah ada, tanpa mengubah pemetaan lain.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'activities.view' => ['superadmin', 'admin_lpm', 'pimpinan'],
        'activities.manage' => ['superadmin', 'admin_lpm'],
        'suggestions.submit' => ['superadmin', 'dosen', 'mahasiswa'],
        'suggestions.manage' => ['superadmin', 'admin_lpm'],
        'achievements.own' => ['superadmin', 'mahasiswa'],
        'achievements.view' => ['superadmin', 'admin_lpm', 'admin_fakultas', 'admin_prodi', 'pimpinan', 'kemahasiswaan'],
        'achievements.verify' => ['superadmin', 'kemahasiswaan'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('kemahasiswaan', 'web')->givePermissionTo(Permission::findOrCreate('notifications.view', 'web'));

        foreach (self::GRANTS as $name => $roles) {
            $permission = Permission::findOrCreate($name, 'web');

            Role::query()->whereIn('name', $roles)->where('guard_name', 'web')->with('permissions')->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', array_keys(self::GRANTS))->where('guard_name', 'web')->delete();
        Role::query()->where('name', 'kemahasiswaan')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
