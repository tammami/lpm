<?php

namespace App\Enums;

enum RecommendationStatus: string
{
    use HasOptions;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Verified = 'verified';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Baru',
            self::InProgress => 'Ditindaklanjuti',
            self::Completed => 'Selesai (menunggu verifikasi)',
            self::Verified => 'Terverifikasi',
            self::Closed => 'Ditutup',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
