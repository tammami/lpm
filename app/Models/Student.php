<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToUser;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['user_id', 'study_program_id', 'nim', 'name', 'email', 'phone', 'gender', 'entry_year', 'semester', 'status'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use Auditable, HasFactory, ScopedToUser;

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'entry_year' => 'integer',
            'semester' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(CourseClass::class, 'class_enrollments')->withTimestamps();
    }
}
