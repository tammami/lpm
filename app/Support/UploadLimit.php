<?php

namespace App\Support;

/**
 * Batas unggah efektif: batas aplikasi yang dibatasi lagi oleh konfigurasi PHP server.
 */
final class UploadLimit
{
    public static function megabytes(int $applicationLimit): int
    {
        $server = min(self::iniBytes('upload_max_filesize'), self::iniBytes('post_max_size'));

        return max(1, (int) min($applicationLimit, floor($server / 1024 / 1024)));
    }

    public static function kilobytes(int $applicationLimit): int
    {
        return self::megabytes($applicationLimit) * 1024;
    }

    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        $number = (int) $value;

        if ($number <= 0) {
            return PHP_INT_MAX;
        }

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
