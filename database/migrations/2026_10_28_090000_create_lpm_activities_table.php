<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timeline kegiatan program kerja LPM (di luar Monev dan AMI). Buktinya disimpan di repositori Dokumen Bukti lewat evidence_mappings.
     */
    public function up(): void
    {
        Schema::create('lpm_activities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->unsignedSmallInteger('year')->index();
            $table->string('title');
            $table->string('category', 30)->index();
            $table->text('description')->nullable();
            $table->text('target_output')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('location')->nullable();
            $table->string('pic')->nullable();
            $table->string('participants')->nullable();
            $table->unsignedBigInteger('budget')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->text('realization_note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['year', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpm_activities');
    }
};
