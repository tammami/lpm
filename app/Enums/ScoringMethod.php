<?php

namespace App\Enums;

enum ScoringMethod: string
{
    use HasOptions;

    case Average = 'average';
    case Weighted = 'weighted';

    public function label(): string
    {
        return match ($this) {
            self::Average => 'Rata-rata sederhana',
            self::Weighted => 'Rata-rata berbobot',
        };
    }

    public function formula(): string
    {
        return match ($this) {
            self::Average => 'Skor = Σ skor butir ÷ jumlah butir',
            self::Weighted => 'Skor = Σ(skor butir × bobot) ÷ Σ bobot',
        };
    }
}
