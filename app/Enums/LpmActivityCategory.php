<?php

namespace App\Enums;

/**
 * Jenis kegiatan program kerja LPM di luar Monev dan AMI (keduanya punya modul sendiri).
 */
enum LpmActivityCategory: string
{
    use HasOptions;

    case Meeting = 'meeting';
    case Training = 'training';
    case Socialization = 'socialization';
    case Document = 'document';
    case Accreditation = 'accreditation';
    case Benchmarking = 'benchmarking';
    case Survey = 'survey';
    case Reporting = 'reporting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Meeting => 'Rapat & koordinasi',
            self::Training => 'Pelatihan / workshop',
            self::Socialization => 'Sosialisasi',
            self::Document => 'Penyusunan dokumen mutu',
            self::Accreditation => 'Pendampingan akreditasi',
            self::Benchmarking => 'Benchmarking / studi banding',
            self::Survey => 'Survei kepuasan',
            self::Reporting => 'Pelaporan',
            self::Other => 'Lainnya',
        };
    }
}
