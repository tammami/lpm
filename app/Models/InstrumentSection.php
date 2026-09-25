<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\InstrumentSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['instrument_version_id', 'code', 'title', 'description', 'sort_order'])]
class InstrumentSection extends Model
{
    /** @use HasFactory<InstrumentSectionFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'instrument';

    public function version(): BelongsTo
    {
        return $this->belongsTo(InstrumentVersion::class, 'instrument_version_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(InstrumentQuestion::class)->orderBy('sort_order');
    }
}
