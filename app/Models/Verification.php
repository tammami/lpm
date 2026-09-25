<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['verifiable_type', 'verifiable_id', 'verifier_id', 'decision', 'notes', 'verified_at'])]
class Verification extends Model
{
    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function verifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }
}
