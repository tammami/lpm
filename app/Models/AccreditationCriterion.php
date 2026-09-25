<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['instrument_version_id', 'parent_id', 'code', 'title', 'description', 'weight', 'sort_order'])]
class AccreditationCriterion extends Model
{
    protected $table = 'accreditation_criteria';

    protected function casts(): array
    {
        return ['weight' => 'float', 'sort_order' => 'integer'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AccreditationInstrumentVersion::class, 'instrument_version_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(AccreditationIndicator::class, 'criterion_id')->orderBy('sort_order');
    }
}
