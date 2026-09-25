<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['period_id', 'indicator_id', 'self_score', 'notes', 'updated_by'])]
class AccreditationAssessment extends Model
{
    protected function casts(): array
    {
        return ['self_score' => 'float'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccreditationPeriod::class, 'period_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(AccreditationIndicator::class, 'indicator_id');
    }
}
