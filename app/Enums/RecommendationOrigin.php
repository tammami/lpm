<?php

namespace App\Enums;

enum RecommendationOrigin: string
{
    use HasOptions;

    case Monev = 'monev';
    case Ami = 'ami';
    case Accreditation = 'accreditation';
    case System = 'system';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Monev => 'Hasil Monev',
            self::Ami => 'Temuan AMI',
            self::Accreditation => 'Kesiapan akreditasi',
            self::System => 'Aturan sistem',
            self::Manual => 'Input manual',
        };
    }
}
