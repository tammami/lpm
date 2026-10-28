<?php

namespace App\Models;

use App\Enums\LpmActivityCategory;
use App\Enums\LpmActivityStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\LpmActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Kegiatan program kerja LPM di luar Monev dan AMI. Bukti pelaksanaannya ditautkan dari repositori Dokumen Bukti.
 */
#[Fillable([
    'code', 'year', 'title', 'category', 'description', 'target_output', 'start_date', 'end_date', 'location', 'pic',
    'participants', 'budget', 'status', 'realization_note', 'created_by',
])]
class LpmActivity extends Model
{
    /** @use HasFactory<LpmActivityFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'activities';

    protected function casts(): array
    {
        return [
            'category' => LpmActivityCategory::class,
            'status' => LpmActivityStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'year' => 'integer',
            'budget' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function evidenceMappings(): MorphMany
    {
        return $this->morphMany(EvidenceMapping::class, 'mappable');
    }

    /**
     * Masih direncanakan/berlangsung padahal tanggal selesainya sudah lewat.
     */
    public function isOverdue(): bool
    {
        return $this->status->isPending() && $this->end_date->lt(now()->startOfDay());
    }
}
