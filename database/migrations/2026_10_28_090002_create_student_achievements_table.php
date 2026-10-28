<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prestasi mahasiswa yang diisi sendiri lalu diverifikasi bidang kemahasiswaan.
     */
    public function up(): void
    {
        Schema::create('student_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 20)->index();
            $table->string('level', 20)->index();
            $table->string('achievement', 100);
            $table->string('participation', 20)->default('individual');
            $table->string('organizer')->nullable();
            $table->date('achieved_on');
            $table->text('description')->nullable();
            $table->string('certificate_path')->nullable();
            $table->string('certificate_name')->nullable();
            $table->string('evidence_url', 1000)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('verification_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'achieved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_achievements');
    }
};
