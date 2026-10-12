<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * NUPTK (16 digit) — identitas nasional pendidik. Dosen baru dapat belum memiliki NIDN.
     */
    public function up(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->string('nuptk', 16)->nullable()->unique()->after('nidn');
        });
    }

    public function down(): void
    {
        Schema::table('lecturers', function (Blueprint $table) {
            $table->dropUnique(['nuptk']);
            $table->dropColumn('nuptk');
        });
    }
};
