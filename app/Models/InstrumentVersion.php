<?php

namespace App\Models;

use App\Enums\InstrumentVersionStatus;
use App\Enums\ScoringMethod;
use App\Models\Concerns\Auditable;
use Database\Factories\InstrumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'instrument_id', 'parent_version_id', 'classification_scheme_id', 'version', 'version_number', 'status',
    'scoring_method', 'scale_min', 'scale_max', 'changelog', 'review_notes', 'submitted_by', 'submitted_at',
    'approved_by', 'approved_at', 'published_by', 'published_at', 'archived_at',
])]
class InstrumentVersion extends Model
{
    /** @use HasFactory<InstrumentVersionFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'instrument';

    protected function casts(): array
    {
        return [
            'status' => InstrumentVersionStatus::class,
            'scoring_method' => ScoringMethod::class,
            'scale_min' => 'float',
            'scale_max' => 'float',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function parentVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    public function classificationScheme(): BelongsTo
    {
        return $this->belongsTo(ClassificationScheme::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(InstrumentSection::class)->orderBy('sort_order');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(InstrumentQuestion::class)->orderBy('sort_order');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isUsable(): bool
    {
        return $this->status === InstrumentVersionStatus::Published;
    }

    public function resolvedClassificationScheme(): ?ClassificationScheme
    {
        return $this->classificationScheme()->with('classifications')->first() ?? ClassificationScheme::default();
    }
}
