<?php

namespace App\Services\DataReset;

use Closure;
use Illuminate\Database\Query\Builder;

/**
 * Sekelompok data yang dapat dikosongkan Superadmin dari halaman daftarnya.
 */
final readonly class Dataset
{
    /**
     * @param  array<string, (Closure(Builder): mixed)|null>  $roots  Tabel utama yang dikosongkan; null = semua baris.
     * @param  list<string>  $notes  Keterangan tambahan yang ditampilkan sebelum konfirmasi.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public array $roots,
        public array $notes = [],
    ) {}

    /**
     * Kalimat yang harus diketik untuk mengonfirmasi.
     */
    public function phrase(): string
    {
        return 'KOSONGKAN '.mb_strtoupper($this->label);
    }
}
