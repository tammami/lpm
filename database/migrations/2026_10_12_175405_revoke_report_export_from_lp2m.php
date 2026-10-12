<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * `reports.export` membuka laporan Monev seluruh institusi (termasuk respons mentah).
     * LP2M cukup `research.view` untuk melihat & mengekspor data karya ilmiah.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->where('name', 'lp2m')->where('guard_name', 'web')->first()?->revokePermissionTo('reports.export');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()->where('name', 'lp2m')->where('guard_name', 'web')->first()?->givePermissionTo('reports.export');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
