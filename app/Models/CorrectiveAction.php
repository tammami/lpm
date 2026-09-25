<?php

namespace App\Models;

use App\Enums\CorrectiveActionStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Tindakan koreksi atas temuan. Wajib memiliki PIC (BR-005).
 */
#[Fillable([
    'finding_id', 'root_cause_category_id', 'root_cause', 'action_plan', 'preventive_action', 'pic_user_id', 'due_date',
    'status', 'progress', 'implementation_notes', 'completed_at', 'created_by',
])]
class CorrectiveAction extends Model
{
    use Auditable;

    protected string $auditModule = 'ami';

    protected function casts(): array
    {
        return [
            'status' => CorrectiveActionStatus::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function rootCauseCategory(): BelongsTo
    {
        return $this->belongsTo(RootCauseCategory::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function evidenceMappings(): MorphMany
    {
        return $this->morphMany(EvidenceMapping::class, 'mappable');
    }

    public function verifications(): MorphMany
    {
        return $this->morphMany(Verification::class, 'verifiable')->latest('verified_at');
    }
}
