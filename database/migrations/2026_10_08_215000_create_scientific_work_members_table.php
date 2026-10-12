<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penulis bersama yang ditandai pada karya: dosen rekan IAIA dan mahasiswa yang ikut menulis.
     * Mahasiswa yang belum ada di master data dicatat dengan nama (dan NIM bila ada) saja.
     */
    public function up(): void
    {
        Schema::create('scientific_work_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scientific_work_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 10);
            $table->foreignId('lecturer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('nim', 20)->nullable();
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['scientific_work_id', 'lecturer_id']);
            $table->unique(['scientific_work_id', 'student_id']);
            $table->index(['kind', 'scientific_work_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scientific_work_members');
    }
};
