<?php

namespace App\Enums;

enum CorrectiveActionStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::InProgress => 'Dikerjakan',
            self::Completed => 'Selesai (menunggu verifikasi)',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Perlu perbaikan',
        };
    }
}
