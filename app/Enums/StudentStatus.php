<?php

namespace App\Enums;

enum StudentStatus: string
{
    use HasOptions;

    case Aktif = 'aktif';
    case Cuti = 'cuti';
    case NonAktif = 'non_aktif';
    case Lulus = 'lulus';
    case Keluar = 'keluar';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Cuti => 'Cuti',
            self::NonAktif => 'Non-aktif',
            self::Lulus => 'Lulus',
            self::Keluar => 'Keluar',
        };
    }
}
