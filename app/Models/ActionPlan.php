<?php

namespace App\Models;

use App\Enums\ActionPlanStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'recommendation_id', 'title', 'description', 'target_output', 'pic_user_id', 'starts_on', 'due_date', 'budget',
    'status', 'progress', 'implementation_notes', 'completed_at', 'created_by',
])]
class ActionPlan extends Model
{
    use Auditable;

    protected string $auditModule = 'improvement';

    protected function casts(): array
    {
        return [
            'status' => ActionPlanStatus::class,
            'starts_on' => 'date',
            'due_date' => 'date',
            'budget' => 'float',
            'progress' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ActionTask::class)->orderBy('sort_order');
    }

    public function evidenceMappings(): MorphMany
    {
        return $this->morphMany(EvidenceMapping::class, 'mappable');
    }

    public function verifications(): MorphMany
    {
        return $this->morphMany(Verification::class, 'verifiable')->latest('verified_at');
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_date->isPast();
    }

    /**
     * @param  Builder<ActionPlan>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', [ActionPlanStatus::Planned, ActionPlanStatus::InProgress, ActionPlanStatus::Rejected])
            ->whereDate('due_date', '<', now()->toDateString());
    }
}
