<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Hak akses karya ilmiah dosen untuk peran yang sudah ada, tanpa mengubah pemetaan lain.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'research.view' => ['superadmin', 'admin_lpm', 'admin_fakultas', 'admin_prodi', 'pimpinan'],
        'research.manage' => ['superadmin', 'admin_lpm', 'admin_fakultas', 'admin_prodi'],
        'research.verify' => ['superadmin', 'admin_lpm'],
        'research.own' => ['superadmin', 'dosen'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
