<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\User;

/**
 * Membuat/menautkan akun login untuk dosen & mahasiswa.
 * Kata sandi awal = NIDN/NIM dan wajib diganti saat login pertama.
 */
class AccountProvisioner
{
    public function forLecturer(Lecturer $lecturer): ?User
    {
        $username = $lecturer->nidn ?: $lecturer->nip;

        if (! $username) {
            return null;
        }

        $user = $this->provision($lecturer->user, $username, $lecturer->full_name, $lecturer->email, UserRole::Dosen);

        if ($lecturer->user_id !== $user->id) {
            $lecturer->forceFill(['user_id' => $user->id])->saveQuietly();
        }

        return $user;
    }

    public function forStudent(Student $student): User
    {
        $user = $this->provision($student->user, $student->nim, $student->name, $student->email, UserRole::Mahasiswa);

        if ($student->user_id !== $user->id) {
            $student->forceFill(['user_id' => $user->id])->saveQuietly();
        }

        return $user;
    }

    private function provision(?User $user, string $username, string $name, ?string $email, UserRole $role): User
    {
        $user ??= User::query()->where('username', $username)->first();

        if (! $user) {
            $user = User::query()->create([
                'username' => $username,
                'name' => $name,
                'email' => $this->availableEmail($email, $username),
                'password' => $username,
                'must_change_password' => true,
                'is_active' => true,
            ]);
        } else {
            $user->fill(['name' => $name])->save();
        }

        if (! $user->hasRole($role->value)) {
            $user->assignRole($role->value);
        }

        return $user;
    }

    /**
     * Email akun harus unik. Bila email sudah dipakai akun lain (mis. email bersama),
     * akun tetap dibuat dengan alamat internal; email asli tetap tersimpan di data dosen/mahasiswa.
     */
    private function availableEmail(?string $email, string $username): string
    {
        if ($email && ! User::query()->where('email', $email)->exists()) {
            return $email;
        }

        return "{$username}@users.simutu.local";
    }

    public function resetPassword(User $user): void
    {
        $user->forceFill([
            'password' => $user->username ?? 'password',
            'must_change_password' => true,
        ])->save();
    }
}
