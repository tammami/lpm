<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Nomor dokumen berurutan per tahun, mis. "TMN-2026-0007".
 */
final class CodeGenerator
{
    public static function next(string $prefix, string $table, string $column = 'code', int $pad = 4): string
    {
        $base = $prefix.'-'.now()->year.'-';
        $last = DB::table($table)->where($column, 'like', $base.'%')->orderByDesc($column)->lockForUpdate()->value($column);
        $number = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $number, $pad, '0', STR_PAD_LEFT);
    }
}
