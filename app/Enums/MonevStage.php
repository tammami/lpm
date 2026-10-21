<?php

namespace App\Enums;

/**
 * Tahap Monev pembelajaran dalam satu semester: awal (pertengahan perkuliahan) dan akhir semester.
 */
enum MonevStage: string
{
    use HasOptions;

    case Awal = 'awal';
    case Akhir = 'akhir';

    public function label(): string
    {
        return match ($this) {
            self::Awal => 'Monev Awal',
            self::Akhir => 'Monev Akhir',
        };
    }

    public function titlePhrase(): string
    {
        return match ($this) {
            self::Awal => 'Awal Semester',
            self::Akhir => 'Akhir Semester',
        };
    }
}
