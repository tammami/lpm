<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Models\Concerns\Auditable;
use Database\Factories\InstrumentQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'instrument_version_id', 'instrument_section_id', 'code', 'label', 'description', 'type', 'category',
    'indicator', 'quality_standard_id', 'is_required', 'is_scored', 'weight', 'min_score', 'max_score', 'requires_evidence',
    'visible_to_evaluatee', 'is_active', 'settings', 'sort_order',
])]
class InstrumentQuestion extends Model
{
    /** @use HasFactory<InstrumentQuestionFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'instrument';

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'is_required' => 'boolean',
            'is_scored' => 'boolean',
            'requires_evidence' => 'boolean',
            'visible_to_evaluatee' => 'boolean',
            'is_active' => 'boolean',
            'weight' => 'float',
            'min_score' => 'float',
            'max_score' => 'float',
            'settings' => 'array',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(InstrumentVersion::class, 'instrument_version_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(InstrumentSection::class, 'instrument_section_id');
    }

    public function standard(): BelongsTo
    {
        return $this->belongsTo(QualityStandard::class, 'quality_standard_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(InstrumentQuestionOption::class)->orderBy('sort_order');
    }

    /**
     * Apakah butir ini ikut dihitung dalam skor.
     */
    public function countsTowardScore(): bool
    {
        return $this->is_scored && $this->is_active && $this->type->isScorable();
    }
}
