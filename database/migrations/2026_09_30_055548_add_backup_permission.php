<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Hak akses cadangan database untuk peran pengelola, tanpa mengubah pemetaan peran lain.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('backups.manage', 'web');

        Role::query()->whereIn('name', ['superadmin', 'admin_lpm'])->where('guard_name', 'web')->with('permissions')->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permission));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'backups.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
