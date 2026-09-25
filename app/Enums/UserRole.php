<?php

namespace App\Enums;

enum UserRole: string
{
    use HasOptions;

    case Superadmin = 'superadmin';
    case AdminLpm = 'admin_lpm';
    case AdminFakultas = 'admin_fakultas';
    case AdminProdi = 'admin_prodi';
    case Pimpinan = 'pimpinan';
    case Auditor = 'auditor';
    case Dosen = 'dosen';
    case Mahasiswa = 'mahasiswa';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminLpm => 'Admin LPM',
            self::AdminFakultas => 'Admin Fakultas',
            self::AdminProdi => 'Admin Prodi',
            self::Pimpinan => 'Pimpinan',
            self::Auditor => 'Auditor',
            self::Dosen => 'Dosen',
            self::Mahasiswa => 'Mahasiswa',
        };
    }

    /**
     * Peran yang dapat melihat data seluruh institusi.
     */
    public function isInstitutionWide(): bool
    {
        return in_array($this, [self::Superadmin, self::AdminLpm, self::Pimpinan], true);
    }
}
