<?php

namespace App\Enums;

enum InstrumentType: string
{
    use HasOptions;

    case MonevPembelajaran = 'monev_pembelajaran';
    case Survei = 'survei';
    case Ami = 'ami';
    case Evaluasi = 'evaluasi';
    case Asesmen = 'asesmen';
    case Kustom = 'kustom';

    public function label(): string
    {
        return match ($this) {
            self::MonevPembelajaran => 'Monev Pembelajaran',
            self::Survei => 'Survei Kepuasan',
            self::Ami => 'Audit Mutu Internal',
            self::Evaluasi => 'Evaluasi',
            self::Asesmen => 'Asesmen',
            self::Kustom => 'Kustom',
        };
    }
}
