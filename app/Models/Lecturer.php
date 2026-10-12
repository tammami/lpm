<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToUser;
use Database\Factories\LecturerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'study_program_id', 'nidn', 'nuptk', 'nip', 'name', 'front_title', 'back_title', 'email',
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

    /**
     * Nomor identitas utama untuk tampilan: NIDN/NIDK, atau NUPTK bila belum memiliki NIDN.
     */
    protected function identifier(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->nidn ?: $this->nuptk);
    }

    /**
     * Cari dosen lewat NIDN/NIDK atau NUPTK.
     *
     * @param  Builder<self>  $query
     */
    public function scopeIdentifiedBy(Builder $query, string $number): void
    {
        $query->where(fn (Builder $inner) => $inner->where('nidn', $number)->orWhere('nuptk', $number));
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

    public function scientificWorks(): HasMany
    {
        return $this->hasMany(ScientificWork::class);
    }

    /**
     * Karya dosen lain yang menandai dosen ini sebagai penulis bersama.
     */
    public function coAuthoredWorks(): BelongsToMany
    {
        return $this->belongsToMany(ScientificWork::class, 'scientific_work_members')->wherePivot('kind', ScientificWorkMember::LECTURER);
    }
}
