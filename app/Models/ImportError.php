<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_job_id', 'row_number', 'column', 'value', 'message', 'severity'])]
class ImportError extends Model
{
    public $timestamps = false;

    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}
