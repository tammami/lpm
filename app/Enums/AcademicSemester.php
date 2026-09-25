<?php

namespace App\Enums;

enum AcademicSemester: string
{
    use HasOptions;

    case Ganjil = 'ganjil';
    case Genap = 'genap';
    case Pendek = 'pendek';

    public function label(): string
    {
        return match ($this) {
            self::Ganjil => 'Ganjil',
            self::Genap => 'Genap',
            self::Pendek => 'Pendek',
        };
    }
}
