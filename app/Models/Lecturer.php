<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToUser;
use Database\Factories\LecturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'study_program_id', 'nidn', 'nip', 'name', 'front_title', 'back_title', 'email',
    'phone', 'gender', 'academic_rank', 'employment_status', 'is_active',
])]
class Lecturer extends Model
{
    /** @use HasFactory<LecturerFactory> */
    use Auditable, HasFactory, ScopedToUser;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Nama lengkap beserta gelar, mis. "Dr. Ahmad Fauzi, M.Pd."
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(function (): string {
            $name = trim(($this->front_title ? $this->front_title.' ' : '').$this->name);

            return $this->back_title ? "{$name}, {$this->back_title}" : $name;
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
