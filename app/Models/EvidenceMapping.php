<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Pemetaan satu dokumen ke banyak kebutuhan (temuan, tindakan, rencana aksi, indikator akreditasi…).
 */
#[Fillable(['evidence_id', 'mappable_type', 'mappable_id', 'context_id', 'note', 'created_by'])]
class EvidenceMapping extends Model
{
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function mappable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
