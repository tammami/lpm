<?php

namespace App\Enums;

enum RespondentType: string
{
    use HasOptions;

    case Mahasiswa = 'mahasiswa';
    case Dosen = 'dosen';
    case Tendik = 'tendik';
    case Alumni = 'alumni';
    case PenggunaLulusan = 'pengguna_lulusan';
    case Auditor = 'auditor';

    public function label(): string
    {
        return match ($this) {
            self::Mahasiswa => 'Mahasiswa',
            self::Dosen => 'Dosen',
            self::Tendik => 'Tenaga Kependidikan',
            self::Alumni => 'Alumni',
            self::PenggunaLulusan => 'Pengguna Lulusan',
            self::Auditor => 'Auditor',
        };
    }
}
