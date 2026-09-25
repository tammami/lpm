<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['action_plan_id', 'title', 'due_date', 'is_done', 'done_at', 'sort_order'])]
class ActionTask extends Model
{
    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'due_date' => 'date', 'done_at' => 'datetime'];
    }

    public function actionPlan(): BelongsTo
    {
        return $this->belongsTo(ActionPlan::class);
    }
}
