<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['classification_scheme_id', 'min_score', 'max_score', 'label', 'color', 'description', 'sort_order'])]
class ScoreClassification extends Model
{
    protected function casts(): array
    {
        return [
            'min_score' => 'float',
            'max_score' => 'float',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ClassificationScheme::class, 'classification_scheme_id');
    }
}
