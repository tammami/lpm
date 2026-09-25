<?php

namespace App\Enums;

/**
 * Siklus hidup versi instrumen. Versi yang sudah dipublikasikan terkunci
 * (tidak dapat diubah) sehingga respons historis selalu konsisten.
 */
enum InstrumentVersionStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Review => 'Ditinjau',
            self::Approved => 'Disetujui',
            self::Published => 'Terbit',
            self::Archived => 'Diarsipkan',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Review],
            self::Review => [self::Draft, self::Approved],
            self::Approved => [self::Draft, self::Published],
            self::Published => [self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
