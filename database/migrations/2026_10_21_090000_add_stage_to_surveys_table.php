<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Monev pembelajaran dibuka dua kali per semester (awal & akhir). Kegiatan lama dijadwalkan
     * di akhir semester sehingga dianggap Monev Akhir.
     */
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->string('stage', 10)->nullable()->after('mode');
        });

        DB::table('surveys')->where('mode', 'teaching_evaluation')->update(['stage' => 'akhir']);
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
