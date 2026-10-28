<?php

namespace App\Enums;

enum LpmActivityStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Done = 'done';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Direncanakan',
            self::Ongoing => 'Berlangsung',
            self::Done => 'Terlaksana',
            self::Postponed => 'Ditunda',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Kegiatan yang masih harus dikerjakan (dipakai untuk penanda terlambat).
     */
    public function isPending(): bool
    {
        return in_array($this, [self::Planned, self::Ongoing], true);
    }
}
