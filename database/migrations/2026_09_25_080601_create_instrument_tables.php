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
        Schema::create('classification_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('scale_min', 6, 2)->default(0);
            $table->decimal('scale_max', 6, 2)->default(4);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('score_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classification_scheme_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_score', 6, 2);
            $table->decimal('max_score', 6, 2);
            $table->string('label');
            $table->string('color', 20)->default('neutral');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('answer_scales', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('question_type', 30)->default('likert');
            $table->json('options');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('type', 30)->index();
            $table->string('respondent_type', 30)->default('mahasiswa');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        Schema::create('instrument_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_version_id')->nullable()->constrained('instrument_versions')->nullOnDelete();
            $table->foreignId('classification_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('version', 10);
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft')->index();
            $table->string('scoring_method', 20)->default('average');
            $table->decimal('scale_min', 6, 2)->default(1);
            $table->decimal('scale_max', 6, 2)->default(4);
            $table->text('changelog')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['instrument_id', 'version_number']);
        });

        Schema::create('instrument_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_version_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('instrument_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_section_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->text('label');
            $table->text('description')->nullable();
            $table->string('type', 30);
            $table->string('category')->nullable();
            $table->string('indicator')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_scored')->default(true);
            $table->decimal('weight', 6, 2)->default(1);
            $table->decimal('min_score', 6, 2)->nullable();
            $table->decimal('max_score', 6, 2)->nullable();
            $table->boolean('requires_evidence')->default(false);
            $table->boolean('visible_to_evaluatee')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['instrument_version_id', 'sort_order']);
        });

        Schema::create('instrument_question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_question_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('value', 50);
            $table->decimal('score', 6, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrument_question_options');
        Schema::dropIfExists('instrument_questions');
        Schema::dropIfExists('instrument_sections');
        Schema::dropIfExists('instrument_versions');
        Schema::dropIfExists('instruments');
        Schema::dropIfExists('answer_scales');
        Schema::dropIfExists('score_classifications');
        Schema::dropIfExists('classification_schemes');
    }
};
