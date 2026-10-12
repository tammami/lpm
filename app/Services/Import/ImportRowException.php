<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * Kesalahan isian pada satu kolom baris impor.
 */
class ImportRowException extends RuntimeException
{
    public function __construct(public string $column, string $message)
    {
        parent::__construct($message);
    }
}
