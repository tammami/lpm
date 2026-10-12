<?php

namespace App\Enums;

/**
 * Status Verifikasi LPM, mengikuti template Data Karya Dosen. Hanya "Valid" yang dihitung sebagai karya valid.
 */
enum ScientificWorkStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Verified = 'verified';
    case Revision = 'revision';
    case Invalid = 'invalid';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum diverifikasi',
            self::Verified => 'Valid',
            self::Revision => 'Valid dengan perbaikan',
            self::Invalid => 'Tidak valid',
            self::Duplicate => 'Duplikat',
        };
    }

    /**
     * Karya tidak valid atau duplikat tidak ikut dihitung dalam rekap dan analisis.
     */
    public function isCounted(): bool
    {
        return ! in_array($this, [self::Invalid, self::Duplicate], true);
    }

    /**
     * @return list<string>
     */
    public static function rejectedValues(): array
    {
        return [self::Invalid->value, self::Duplicate->value];
    }

    /**
     * Keputusan yang dapat diambil verifikator.
     *
     * @return list<self>
     */
    public static function decisions(): array
    {
        return [self::Verified, self::Revision, self::Invalid, self::Duplicate];
    }
}
