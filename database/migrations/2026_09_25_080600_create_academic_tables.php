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
        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('academic_year', 9);
            $table->string('semester', 10);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('study_program_id')->constrained()->restrictOnDelete();
            $table->string('nidn', 20)->nullable()->unique();
            $table->string('nip', 30)->nullable();
            $table->string('name');
            $table->string('front_title', 30)->nullable();
            $table->string('back_title', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('gender', 1)->nullable();
            $table->string('academic_rank', 30)->nullable();
            $table->string('employment_status', 20)->default('tetap');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('study_program_id')->constrained()->restrictOnDelete();
            $table->string('nim', 20)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('gender', 1)->nullable();
            $table->unsignedSmallInteger('entry_year');
            $table->unsignedTinyInteger('semester')->default(1);
            $table->string('status', 20)->default('aktif')->index();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_program_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->unsignedTinyInteger('credits')->default(2);
            $table->unsignedTinyInteger('semester')->default(1);
            $table->string('type', 20)->default('wajib');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['study_program_id', 'code']);
        });

        Schema::create('course_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->string('code', 10);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'academic_period_id', 'code']);
        });

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained()->restrictOnDelete();
            $table->string('role', 20)->default('pengampu');
            $table->timestamps();

            $table->unique(['course_class_id', 'lecturer_id']);
        });

        Schema::create('class_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['course_class_id', 'student_id']);
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_enrollments');
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('course_classes');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('students');
        Schema::dropIfExists('lecturers');
        Schema::dropIfExists('academic_periods');
    }
};
