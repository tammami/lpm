<?php

namespace App\Models;

use App\Enums\AuditeeType;
use App\Enums\AuditStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'audit_program_id', 'code', 'auditee_type', 'auditee_id', 'auditee_name', 'study_program_id', 'faculty_id',
    'instrument_version_id', 'auditee_pic_user_id', 'desk_review_due', 'scheduled_on', 'location', 'status', 'summary',
    'strengths', 'conclusion', 'started_at', 'completed_at',
])]
class Audit extends Model
{
    use Auditable, ScopedToOrganization;

    protected string $auditModule = 'ami';

    protected function casts(): array
    {
        return [
            'auditee_type' => AuditeeType::class,
            'status' => AuditStatus::class,
            'desk_review_due' => 'date',
            'scheduled_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(AuditProgram::class, 'audit_program_id');
    }

    public function instrumentVersion(): BelongsTo
    {
        return $this->belongsTo(InstrumentVersion::class);
    }

    public function auditeePic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditee_pic_user_id');
    }

    public function auditors(): BelongsToMany
    {
        return $this->belongsToMany(Auditor::class)->withPivot('role')->withTimestamps();
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AuditAnswer::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    public function evidenceMappings(): MorphMany
    {
        return $this->morphMany(EvidenceMapping::class, 'mappable');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->auditors()->where('auditors.user_id', $user->id)->exists();
    }

    public function isLeadAuditor(User $user): bool
    {
        return $this->auditors()->where('auditors.user_id', $user->id)->wherePivot('role', 'lead')->exists();
    }

    protected function personalAccess(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('auditee_pic_user_id'), $user->id)
            ->orWhereHas('auditors', fn (Builder $auditor) => $auditor->where('auditors.user_id', $user->id));
    }
}
