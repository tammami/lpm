<?php

namespace App\Enums;

enum AchievementLevel: string
{
    use HasOptions;

    case International = 'international';
    case National = 'national';
    case Provincial = 'provincial';
    case Regency = 'regency';
    case Institution = 'institution';

    public function label(): string
    {
        return match ($this) {
            self::International => 'Internasional',
            self::National => 'Nasional',
            self::Provincial => 'Provinsi / wilayah',
            self::Regency => 'Kabupaten / kota',
            self::Institution => 'Perguruan tinggi',
        };
    }

    /**
     * Tingkat pada tabel LKPS: lokal/wilayah, nasional, internasional.
     */
    public function lkpsLabel(): string
    {
        return match ($this) {
            self::International => 'Internasional',
            self::National => 'Nasional',
            default => 'Lokal/Wilayah',
        };
    }
}
