<?php

namespace App\Enums;

/**
 * Tahapan pelaksanaan audit: desk evaluation → audit lapangan → pelaporan → selesai.
 */
enum AuditStatus: string
{
    use HasOptions;

    case Planned = 'planned';
    case DeskReview = 'desk_review';
    case FieldAudit = 'field_audit';
    case Reporting = 'reporting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Terjadwal',
            self::DeskReview => 'Desk evaluation',
            self::FieldAudit => 'Audit lapangan',
            self::Reporting => 'Pelaporan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Planned => self::DeskReview,
            self::DeskReview => self::FieldAudit,
            self::FieldAudit => self::Reporting,
            self::Reporting => self::Completed,
            default => null,
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DeskReview, self::FieldAudit, self::Reporting], true);
    }
}
