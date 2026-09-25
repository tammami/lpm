<?php

namespace App\Models;

use App\Enums\AccreditationVersionStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['accreditation_body_id', 'code', 'name', 'description', 'is_active'])]
class AccreditationInstrument extends Model
{
    use Auditable;

    protected string $auditModule = 'accreditation';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function body(): BelongsTo
    {
        return $this->belongsTo(AccreditationBody::class, 'accreditation_body_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AccreditationInstrumentVersion::class)->orderByDesc('id');
    }

    public function publishedVersion(): HasOne
    {
        return $this->hasOne(AccreditationInstrumentVersion::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('status', AccreditationVersionStatus::Published));
    }
}
