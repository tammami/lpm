<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolePermissionSeeder::class))
    ->in('Feature');

/**
 * Buat pengguna dengan peran tertentu.
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(UserRole $role, array $attributes = []): User
{
    return User::factory()->role($role, $attributes)->create();
}
