<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['instrument_question_id', 'label', 'value', 'score', 'sort_order'])]
class InstrumentQuestionOption extends Model
{
    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(InstrumentQuestion::class, 'instrument_question_id');
    }
}
