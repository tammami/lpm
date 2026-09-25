<?php

namespace App\Models;

use App\Enums\InstrumentType;
use App\Enums\InstrumentVersionStatus;
use App\Enums\RespondentType;
use App\Models\Concerns\Auditable;
use Database\Factories\InstrumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'name', 'type', 'respondent_type', 'description', 'created_by', 'archived_at'])]
class Instrument extends Model
{
    /** @use HasFactory<InstrumentFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'instrument';

    protected function casts(): array
    {
        return [
            'type' => InstrumentType::class,
            'respondent_type' => RespondentType::class,
            'archived_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(InstrumentVersion::class)->orderByDesc('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(InstrumentVersion::class)->latestOfMany('version_number');
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(InstrumentVersion::class)
            ->ofMany(['version_number' => 'max'], fn ($query) => $query->where('status', InstrumentVersionStatus::Published));
    }

    public function surveys(): HasManyThrough
    {
        return $this->hasManyThrough(Survey::class, InstrumentVersion::class);
    }
}
