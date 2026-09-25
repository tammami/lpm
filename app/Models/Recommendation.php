<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\RecommendationOrigin;
use App\Enums\RecommendationStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'code', 'title', 'description', 'rationale', 'origin', 'source_type', 'source_id', 'rule_key', 'target_type',
    'target_id', 'target_name', 'study_program_id', 'faculty_id', 'indicator', 'priority', 'pic_user_id', 'due_date',
    'status', 'created_by', 'closed_at',
])]
class Recommendation extends Model
{
    use Auditable, ScopedToOrganization;

    protected string $auditModule = 'improvement';

    protected function casts(): array
    {
        return [
            'origin' => RecommendationOrigin::class,
            'priority' => Priority::class,
            'status' => RecommendationStatus::class,
            'due_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function actionPlans(): HasMany
    {
        return $this->hasMany(ActionPlan::class)->oldest();
    }

    public function verifications(): MorphMany
    {
        return $this->morphMany(Verification::class, 'verifiable')->latest('verified_at');
    }

    protected function personalAccess(Builder $query, User $user): void
    {
        $query->where($this->qualifyColumn('pic_user_id'), $user->id)
            ->orWhereHas('actionPlans', fn (Builder $plan) => $plan->where('pic_user_id', $user->id));
    }
}
