<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_body_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('accreditation_instrument_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_instrument_id')->constrained(indexName: 'accr_versions_instrument_fk')->cascadeOnDelete();
            $table->string('version', 20);
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('scale_max', 4, 2)->default(4);
            // Ambang peringkat estimasi, mis. [{"label":"Unggul","min":361}, ...] pada skala 0–400.
            $table->json('grade_thresholds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['accreditation_instrument_id', 'version'], 'accr_version_unique');
        });

        Schema::create('accreditation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_version_id')->constrained('accreditation_instrument_versions')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accreditation_criteria')->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('weight', 6, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('accreditation_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_version_id')->constrained('accreditation_instrument_versions')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('accreditation_criteria')->cascadeOnDelete();
            $table->string('code', 20);
            $table->text('statement');
            $table->text('target')->nullable();
            $table->text('evidence_hint')->nullable();
            $table->decimal('weight', 6, 2)->default(1);
            $table->boolean('is_essential')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['instrument_version_id', 'code']);
        });

        Schema::create('accreditation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_version_id')->constrained('accreditation_instrument_versions')->restrictOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('status', 20)->default('preparing')->index();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('starts_on')->nullable();
            $table->date('submission_deadline')->nullable();
            $table->date('submitted_on')->nullable();
            $table->date('visit_on')->nullable();
            $table->date('decided_on')->nullable();
            $table->decimal('target_score', 6, 2)->nullable();
            $table->string('result_grade', 50)->nullable();
            $table->decimal('result_score', 6, 2)->nullable();
            $table->string('sk_number')->nullable();
            $table->string('certificate_number')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('accreditation_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('accreditation_periods')->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained('accreditation_indicators')->cascadeOnDelete();
            $table->decimal('self_score', 4, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['period_id', 'indicator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_assessments');
        Schema::dropIfExists('accreditation_periods');
        Schema::dropIfExists('accreditation_indicators');
        Schema::dropIfExists('accreditation_criteria');
        Schema::dropIfExists('accreditation_instrument_versions');
        Schema::dropIfExists('accreditation_instruments');
    }
};
