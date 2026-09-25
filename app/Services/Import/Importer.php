<?php

namespace App\Services\Import;

/**
 * Kontrak satu jenis impor. Setiap baris sudah dipetakan: kunci kolom => nilai.
 */
abstract class Importer
{
    abstract public function type(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /**
     * Definisi kolom template.
     *
     * @return list<array{key: string, label: string, required: bool, example: string, note: string}>
     */
    abstract public function columns(): array;

    /**
     * Kunci unik baris (untuk deteksi duplikat), atau null bila tidak dapat ditentukan.
     *
     * @param  array<string, mixed>  $row
     */
    abstract public function key(array $row): ?string;

    /**
     * Apakah data dengan kunci ini sudah ada di sistem.
     */
    abstract public function exists(string $key, ImportContext $context): bool;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string> kolom => pesan kesalahan
     */
    abstract public function validate(array $row, ImportContext $context): array;

    /**
     * Simpan satu baris valid. Mengembalikan "created", "updated", atau "skipped".
     *
     * @param  array<string, mixed>  $row
     */
    abstract public function persist(array $row, ImportContext $context, bool $updateExisting): string;

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function normalize(array $row): array
    {
        return $row;
    }

    public function templateFilename(): string
    {
        return "template_{$this->type()}.xlsx";
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    protected function requireColumns(array $row, array $keys): array
    {
        $errors = [];

        foreach ($keys as $key) {
            if (($row[$key] ?? null) === null || $row[$key] === '') {
                $errors[$key] = 'Wajib diisi';
            }
        }

        return $errors;
    }
}
