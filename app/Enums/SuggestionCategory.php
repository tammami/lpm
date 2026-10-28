<?php

namespace App\Enums;

enum SuggestionCategory: string
{
    use HasOptions;

    case Academic = 'academic';
    case Teaching = 'teaching';
    case Service = 'service';
    case Facility = 'facility';
    case Information = 'information';
    case Student = 'student';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Academic => 'Akademik & kurikulum',
            self::Teaching => 'Pembelajaran & dosen',
            self::Service => 'Layanan administrasi',
            self::Facility => 'Sarana & prasarana',
            self::Information => 'Sistem informasi',
            self::Student => 'Kemahasiswaan',
            self::Other => 'Lainnya',
        };
    }
}
