<?php

namespace App\Models;

use App\Enums\EvidenceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Dokumen bukti mutu. Setiap unggahan baru menjadi versi baru; versi lama tidak pernah hilang.
 */
#[Fillable([
    'code', 'title', 'description', 'evidence_category_id', 'document_type', 'owner_user_id', 'unit_type', 'unit_id',
    'unit_name', 'study_program_id', 'faculty_id', 'year', 'academic_period_id', 'current_version_id', 'status',
    'valid_until', 'confidentiality', 'created_by',
])]
class Evidence extends Model
{
    use Auditable, ScopedToOrganization;

    protected string $auditModule = 'evidence';

    protected function casts(): array
    {
        return [
            'status' => EvidenceStatus::class,
            'valid_until' => 'date',
            'year' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EvidenceCategory::class, 'evidence_category_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EvidenceVersion::class)->orderByDesc('version');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(EvidenceVersion::class, 'current_version_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(EvidenceMapping::class);
    }

    public function verifications(): MorphMany
    {
        return $this->morphMany(Verification::class, 'verifiable')->latest('verified_at');
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->lt(now()->startOfDay());
    }

    protected function personalAccess(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('owner_user_id'), $user->id)
            ->orWhere($this->qualifyColumn('confidentiality'), 'public')
            ->when($user->unit_id, fn (Builder $q) => $q->orWhere(fn (Builder $unit) => $unit
                ->where($this->qualifyColumn('unit_type'), 'unit')->where($this->qualifyColumn('unit_id'), $user->unit_id)));
    }
}
