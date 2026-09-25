<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'type', 'file_path', 'original_name', 'status', 'options', 'total_rows', 'valid_rows',
    'invalid_rows', 'duplicate_rows', 'imported_rows', 'preview', 'failure_message', 'validated_at', 'completed_at',
])]
class ImportJob extends Model
{
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'preview' => 'array',
            'validated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class)->orderBy('row_number');
    }
}
