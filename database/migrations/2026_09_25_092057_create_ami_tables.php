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
        Schema::create('quality_standards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('category', 40)->default('pendidikan');
            $table->text('statement')->nullable();
            $table->text('indicator')->nullable();
            $table->string('target')->nullable();
            $table->string('reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('instrument_questions', function (Blueprint $table) {
            $table->foreignId('quality_standard_id')->nullable()->after('indicator')->constrained()->nullOnDelete();
        });

        Schema::create('auditors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('certificate_number')->nullable();
            $table->string('certification')->nullable();
            $table->date('certified_on')->nullable();
            $table->text('competencies')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finding_severities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color', 20)->default('warning');
            $table->boolean('requires_corrective_action')->default(true);
            $table->unsignedSmallInteger('default_due_days')->default(30);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('root_cause_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('audit_programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->foreignId('academic_period_id')->nullable()->constrained()->nullOnDelete();
            $table->text('scope')->nullable();
            $table->text('objective')->nullable();
            $table->text('criteria')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_program_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('auditee_type', 30);
            $table->unsignedBigInteger('auditee_id')->nullable();
            $table->string('auditee_name');
            $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instrument_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('auditee_pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('desk_review_due')->nullable();
            $table->date('scheduled_on')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('planned')->index();
            $table->text('summary')->nullable();
            $table->text('strengths')->nullable();
            $table->text('conclusion')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['auditee_type', 'auditee_id']);
        });

        Schema::create('audit_auditor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auditor_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('member');
            $table->timestamps();

            $table->unique(['audit_id', 'auditor_id']);
        });

        Schema::create('audit_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_question_id')->constrained()->restrictOnDelete();
            $table->foreignId('instrument_question_option_id')->nullable()->constrained()->nullOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 14, 4)->nullable();
            $table->decimal('score', 6, 3)->nullable();
            $table->text('auditor_note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['audit_id', 'instrument_question_id']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('audit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditee_type', 30);
            $table->unsignedBigInteger('auditee_id')->nullable();
            $table->string('auditee_name');
            $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('faculty_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quality_standard_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('instrument_question_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finding_severity_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('criteria')->nullable();
            $table->text('effect')->nullable();
            $table->text('recommendation')->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['auditee_type', 'auditee_id']);
        });

        Schema::create('corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('root_cause_category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('root_cause');
            $table->text('action_plan');
            $table->text('preventive_action')->nullable();
            $table->foreignId('pic_user_id')->constrained('users')->restrictOnDelete();
            $table->date('due_date');
            $table->string('status', 20)->default('planned')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('implementation_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
        Schema::dropIfExists('findings');
        Schema::dropIfExists('audit_answers');
        Schema::dropIfExists('audit_auditor');
        Schema::dropIfExists('audits');
        Schema::dropIfExists('audit_programs');
        Schema::dropIfExists('root_cause_categories');
        Schema::dropIfExists('finding_severities');
        Schema::dropIfExists('auditors');

        Schema::table('instrument_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quality_standard_id');
        });

        Schema::dropIfExists('quality_standards');
    }
};
