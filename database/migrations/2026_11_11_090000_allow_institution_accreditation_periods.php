<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Periode akreditasi institusi (APT): `study_program_id` boleh kosong.
 *
 * Aman untuk data yang ada: hanya melonggarkan aturan kolom (NOT NULL → NULL).
 * Tidak ada baris yang diubah/dihapus; foreign key, indeks, dan nilai lama tetap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accreditation_periods', function (Blueprint $table) {
            $table->unsignedBigInteger('study_program_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Jangan pernah menghapus periode institusi demi rollback — tolak bila masih ada.
        $institutional = DB::table('accreditation_periods')->whereNull('study_program_id')->count();

        if ($institutional > 0) {
            throw new RuntimeException("Rollback dibatalkan: masih ada {$institutional} periode akreditasi institusi. Hapus/pindahkan dahulu secara manual.");
        }

        Schema::table('accreditation_periods', function (Blueprint $table) {
            $table->unsignedBigInteger('study_program_id')->nullable(false)->change();
        });
    }
};
