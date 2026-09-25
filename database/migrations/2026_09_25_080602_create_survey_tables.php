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
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('instrument_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_period_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('mode', 30)->default('teaching_evaluation');
            $table->string('respondent_type', 30)->default('mahasiswa');
            $table->boolean('is_anonymous')->default(true);
            $table->unsignedSmallInteger('min_responses')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('study_program_survey', function (Blueprint $table) {
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_program_id')->constrained()->cascadeOnDelete();

            $table->primary(['survey_id', 'study_program_id']);
        });

        /*
         * Eligibility & status pengisian. Sengaja tidak menyimpan referensi
         * terbaca ke tabel responses agar isi evaluasi tetap anonim.
         */
        Schema::create('survey_participations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teaching_assignment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('target_key', 40);
            $table->string('status', 20)->default('submitted');
            $table->text('response_ref')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reopen_reason')->nullable();
            $table->unsignedTinyInteger('submission_count')->default(1);
            $table->timestamps();

            $table->unique(['survey_id', 'user_id', 'target_key']);
            $table->index(['survey_id', 'teaching_assignment_id']);
        });

        /*
         * Isi evaluasi. Tidak memiliki kolom yang menunjuk ke mahasiswa
         * (respondent_user_id hanya terisi bila survei tidak anonim) dan
         * memakai UUID acak supaya urutan insert tidak bisa dicocokkan.
         */
        Schema::create('responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('teaching_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('respondent_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scoring_method', 20);
            $table->decimal('score', 6, 3)->nullable();
            $table->date('submitted_on');
            $table->timestamp('voided_at')->nullable();

            $table->index(['survey_id', 'lecturer_id']);
            $table->index(['survey_id', 'study_program_id']);
        });

        Schema::create('response_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_question_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_question_option_id')->nullable()->constrained()->nullOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 14, 4)->nullable();
            $table->json('value_json')->nullable();
            $table->decimal('score', 6, 3)->nullable();

            $table->index('instrument_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('response_answers');
        Schema::dropIfExists('responses');
        Schema::dropIfExists('survey_participations');
        Schema::dropIfExists('study_program_survey');
        Schema::dropIfExists('surveys');
    }
};
