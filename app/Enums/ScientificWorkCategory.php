<?php

namespace App\Enums;

/**
 * Jenis Karya pada template Data Karya Dosen LPM. Diturunkan dari subjenis, tidak diisi terpisah.
 */
enum ScientificWorkCategory: string
{
    use HasOptions;

    case Article = 'article';
    case Book = 'book';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Artikel & Publikasi Ilmiah',
            self::Book => 'Buku Akademik',
            self::Other => 'Karya Lainnya',
        };
    }
}
