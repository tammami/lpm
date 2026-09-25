<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'response_id', 'instrument_question_id', 'instrument_question_option_id', 'value_text', 'value_number',
    'value_json', 'score',
])]
class ResponseAnswer extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'value_number' => 'float',
            'value_json' => 'array',
            'score' => 'float',
        ];
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(InstrumentQuestion::class, 'instrument_question_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(InstrumentQuestionOption::class, 'instrument_question_option_id');
    }
}
