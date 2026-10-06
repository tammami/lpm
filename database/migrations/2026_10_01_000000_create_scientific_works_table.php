<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Karya ilmiah dosen (publikasi, buku, HKI) — hanya data isian, tanpa unggah berkas.
     */
    public function up(): void
    {
        Schema::create('scientific_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->string('title', 500);
            $table->string('type', 40)->index();
            $table->unsignedSmallInteger('year')->index();
            $table->string('venue')->nullable();
            $table->string('rank', 20)->nullable();
            $table->string('author_role', 20)->default('first');
            $table->text('co_authors')->nullable();
            $table->string('source', 20)->default('research');
            $table->string('funding', 20)->default('self');
            $table->string('identifier', 100)->nullable();
            $table->string('volume', 150)->nullable();
            $table->string('url', 1000)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->text('verification_note')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lecturer_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scientific_works');
    }
};
