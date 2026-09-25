<?php

namespace App\Models;

use App\Enums\AuditeeType;
use App\Enums\FindingStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Temuan audit. Wajib memiliki auditee (BR-004).
 */
#[Fillable([
    'code', 'audit_id', 'auditee_type', 'auditee_id', 'auditee_name', 'study_program_id', 'faculty_id', 'quality_standard_id',
    'instrument_question_id', 'finding_severity_id', 'title', 'description', 'criteria', 'effect', 'recommendation',
    'pic_user_id', 'due_date', 'status', 'created_by', 'issued_at', 'verified_at', 'closed_at', 'closed_by',
])]
class Finding extends Model
{
    use Auditable, ScopedToOrganization;

    protected string $auditModule = 'ami';

    protected function casts(): array
    {
        return [
            'auditee_type' => AuditeeType::class,
            'status' => FindingStatus::class,
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'verified_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function severity(): BelongsTo
    {
        return $this->belongsTo(FindingSeverity::class, 'finding_severity_id');
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(QualityStandard::class, 'quality_standard_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(InstrumentQuestion::class, 'instrument_question_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class)->oldest();
    }

    public function verifications(): MorphMany
    {
        return $this->morphMany(Verification::class, 'verifiable')->latest('verified_at');
    }

    public function evidenceMappings(): MorphMany
    {
        return $this->morphMany(EvidenceMapping::class, 'mappable');
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast() && in_array($this->status, FindingStatus::unresolved(), true);
    }

    /**
     * @param  Builder<Finding>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', FindingStatus::unresolved())->whereDate('due_date', '<', now()->toDateString());
    }

    protected function personalAccess(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('pic_user_id'), $user->id)
            ->orWhereHas('correctiveActions', fn (Builder $action) => $action->where('pic_user_id', $user->id))
            ->orWhereHas('audit.auditors', fn (Builder $auditor) => $auditor->where('auditors.user_id', $user->id));
    }
}
