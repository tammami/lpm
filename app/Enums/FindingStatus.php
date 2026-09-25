<?php

namespace App\Enums;

/**
 * Alur temuan (BRD §42): OPEN → ACTION REQUIRED → IN PROGRESS → SUBMITTED/VERIFICATION → VERIFIED → CLOSED.
 */
enum FindingStatus: string
{
    use HasOptions;

    case Open = 'open';
    case ActionRequired = 'action_required';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Terbuka (draf auditor)',
            self::ActionRequired => 'Perlu tindakan',
            self::InProgress => 'Dalam perbaikan',
            self::Submitted => 'Menunggu verifikasi',
            self::Verified => 'Terverifikasi',
            self::Closed => 'Ditutup',
        };
    }

    public function step(): int
    {
        return match ($this) {
            self::Open => 1,
            self::ActionRequired => 2,
            self::InProgress => 3,
            self::Submitted => 4,
            self::Verified => 5,
            self::Closed => 6,
        };
    }

    /**
     * Status yang masih menuntut tindak lanjut.
     *
     * @return list<self>
     */
    public static function unresolved(): array
    {
        return [self::Open, self::ActionRequired, self::InProgress, self::Submitted];
    }
}
