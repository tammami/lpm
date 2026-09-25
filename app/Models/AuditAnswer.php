<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['audit_id', 'instrument_question_id', 'instrument_question_option_id', 'value_text', 'value_number', 'score', 'auditor_note', 'updated_by'])]
class AuditAnswer extends Model
{
    protected function casts(): array
    {
        return ['score' => 'float', 'value_number' => 'float'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
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
