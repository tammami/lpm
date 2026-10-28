<?php

namespace App\Enums;

/**
 * Pemisahan prestasi akademik & nonakademik mengikuti tabel prestasi mahasiswa LKPS.
 */
enum AchievementCategory: string
{
    use HasOptions;

    case Academic = 'academic';
    case NonAcademic = 'non_academic';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Akademik',
            self::NonAcademic => 'Nonakademik',
        };
    }
}
