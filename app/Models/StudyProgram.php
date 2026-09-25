<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToUser;
use Database\Factories\StudyProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'faculty_id', 'accreditation_body_id', 'code', 'name', 'degree', 'accreditation_status',
    'accreditation_valid_until', 'accreditation_sk_number', 'head_name', 'is_active',
])]
class StudyProgram extends Model
{
    /** @use HasFactory<StudyProgramFactory> */
    use Auditable, HasFactory, ScopedToUser;

    protected string $studyProgramColumn = 'id';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'accreditation_valid_until' => 'date',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => "{$this->degree} {$this->name}");
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function accreditationBody(): BelongsTo
    {
        return $this->belongsTo(AccreditationBody::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function lecturers(): HasMany
    {
        return $this->hasMany(Lecturer::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
