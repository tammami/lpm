<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Spreadsheet;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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

/**
 * Jalankan unduhan XLSX sampai selesai lalu baca kembali baris sheet pertamanya.
 *
 * @return list<list<mixed>>
 */
function downloadedRows(TestResponse $response): array
{
    $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    return array_values(iterator_to_array(Spreadsheet::read($path)));
}
