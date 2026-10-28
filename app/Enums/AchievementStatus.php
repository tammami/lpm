<?php

namespace App\Enums;

enum AchievementStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Verified = 'verified';
    case Revision = 'revision';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Verified => 'Terverifikasi',
            self::Revision => 'Perlu perbaikan',
            self::Rejected => 'Ditolak',
        };
    }

    /**
     * Mahasiswa masih boleh mengubah data selama belum diputuskan final.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Pending, self::Revision], true);
    }

    /**
     * Keputusan yang dapat diambil bidang kemahasiswaan.
     *
     * @return list<self>
     */
    public static function decisions(): array
    {
        return [self::Verified, self::Revision, self::Rejected];
    }
}
