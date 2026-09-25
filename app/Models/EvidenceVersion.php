<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evidence_id', 'version', 'file_path', 'original_name', 'mime_type', 'size', 'checksum', 'url', 'notes', 'uploaded_by'])]
class EvidenceVersion extends Model
{
    protected function casts(): array
    {
        return ['size' => 'integer', 'version' => 'integer'];
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
