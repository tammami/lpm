<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\CourseClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kelas perkuliahan (rombel) sebuah mata kuliah pada periode akademik tertentu.
 */
#[Fillable(['course_id', 'academic_period_id', 'code', 'capacity'])]
class CourseClass extends Model
{
    /** @use HasFactory<CourseClassFactory> */
    use Auditable, HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function lecturers(): BelongsToMany
    {
        return $this->belongsToMany(Lecturer::class, 'teaching_assignments')->withPivot('id', 'role')->withTimestamps();
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'class_enrollments')->withTimestamps();
    }

    /**
     * Batasi ke kelas yang dapat diakses pengguna berdasarkan prodi mata kuliah.
     *
     * @param  Builder<CourseClass>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = $user->accessibleStudyProgramIds();

        if ($ids !== null) {
            $query->whereHas('course', fn (Builder $course) => $course->whereIn('study_program_id', $ids));
        }
    }
}
