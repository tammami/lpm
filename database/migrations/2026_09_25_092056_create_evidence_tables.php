<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evidence_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('evidence', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('evidence_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 10)->default('file');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('unit_type', 30)->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('unit_name')->nullable();
            $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->date('valid_until')->nullable();
            $table->string('confidentiality', 20)->default('internal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['unit_type', 'unit_id']);
        });

        Schema::create('evidence_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('url', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['evidence_id', 'version']);
        });

        Schema::create('evidence_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained()->cascadeOnDelete();
            $table->morphs('mappable');
            $table->unsignedBigInteger('context_id')->nullable()->index();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['evidence_id', 'mappable_type', 'mappable_id', 'context_id'], 'evidence_mapping_unique');
        });

        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->morphs('verifiable');
            $table->foreignId('verifier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 20);
            $table->text('notes')->nullable();
            $table->timestamp('verified_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verifications');
        Schema::dropIfExists('evidence_mappings');
        Schema::dropIfExists('evidence_versions');
        Schema::dropIfExists('evidence');
        Schema::dropIfExists('evidence_categories');
    }
};
