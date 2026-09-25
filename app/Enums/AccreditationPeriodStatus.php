<?php

namespace App\Enums;

enum AccreditationPeriodStatus: string
{
    use HasOptions;

    case Preparing = 'preparing';
    case Submitted = 'submitted';
    case Visitation = 'visitation';
    case Decided = 'decided';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Penyusunan dokumen',
            self::Submitted => 'Dokumen diajukan',
            self::Visitation => 'Asesmen lapangan',
            self::Decided => 'Keputusan terbit',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Preparing => self::Submitted,
            self::Submitted => self::Visitation,
            self::Visitation => self::Decided,
            default => null,
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Preparing, self::Submitted, self::Visitation], true);
    }
}
