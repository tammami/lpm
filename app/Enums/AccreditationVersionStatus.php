<?php

namespace App\Enums;

enum AccreditationVersionStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Berlaku',
            self::Archived => 'Diarsipkan',
        };
    }
}
