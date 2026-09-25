<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun demo per peran (kata sandi: "password"). Hapus/ganti sebelum produksi.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $faculty = Faculty::query()->where('code', 'FTK')->first();
        $mathematics = StudyProgram::query()->where('code', 'TMTK')->first();

        $accounts = [
            ['username' => 'superadmin', 'name' => 'Administrator Sistem', 'email' => 'superadmin@simutu.test', 'role' => UserRole::Superadmin],
            ['username' => 'lpm', 'name' => 'Admin LPM', 'email' => 'lpm@simutu.test', 'role' => UserRole::AdminLpm],
            ['username' => 'rektor', 'name' => 'Pimpinan Institut', 'email' => 'pimpinan@simutu.test', 'role' => UserRole::Pimpinan],
            ['username' => 'ftk', 'name' => 'Admin Fakultas Tarbiyah', 'email' => 'ftk@simutu.test', 'role' => UserRole::AdminFakultas, 'faculty_id' => $faculty?->id],
            ['username' => 'prodi.tmtk', 'name' => 'Admin Prodi Tadris Matematika', 'email' => 'tmtk@simutu.test', 'role' => UserRole::AdminProdi, 'study_program_id' => $mathematics?->id],
            ['username' => 'auditor', 'name' => 'Auditor Internal', 'email' => 'auditor@simutu.test', 'role' => UserRole::Auditor],
        ];

        foreach ($accounts as $account) {
            $role = $account['role'];
            unset($account['role']);

            $user = User::query()->updateOrCreate(['username' => $account['username']], [
                ...$account,
                'password' => 'password',
                'is_active' => true,
            ]);

            $user->syncRoles([$role->value]);
        }
    }
}
