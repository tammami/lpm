<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['instrument_version_id', 'criterion_id', 'code', 'statement', 'target', 'evidence_hint', 'weight', 'is_essential', 'sort_order'])]
class AccreditationIndicator extends Model
{
    protected function casts(): array
    {
        return ['weight' => 'float', 'is_essential' => 'boolean', 'sort_order' => 'integer'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AccreditationInstrumentVersion::class, 'instrument_version_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AccreditationCriterion::class, 'criterion_id');
    }

    /**
     * Bukti dipetakan per periode lewat `context_id` — bukti siklus lama tidak terhitung untuk siklus baru.
     */
    public function evidenceMappings(): MorphMany
    {
        return $this->morphMany(EvidenceMapping::class, 'mappable');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(AccreditationAssessment::class, 'indicator_id');
    }
}
