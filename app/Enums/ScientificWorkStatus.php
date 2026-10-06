<?php

namespace App\Enums;

enum ScientificWorkStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Verified = 'verified';
    case Revision = 'revision';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Verified => 'Terverifikasi',
            self::Revision => 'Perlu perbaikan',
        };
    }
}
