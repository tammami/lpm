<?php

namespace App\Models;

use App\Enums\AccreditationVersionStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['accreditation_instrument_id', 'version', 'status', 'scale_max', 'grade_thresholds', 'notes', 'published_at', 'created_by'])]
class AccreditationInstrumentVersion extends Model
{
    use Auditable;

    protected string $auditModule = 'accreditation';

    /**
     * Ambang bawaan (skala 0–400) — dapat disesuaikan dengan ketentuan LAM yang berlaku.
     */
    public const DEFAULT_THRESHOLDS = [
        ['label' => 'Unggul', 'min' => 361],
        ['label' => 'Baik Sekali', 'min' => 301],
        ['label' => 'Baik', 'min' => 200],
        ['label' => 'Tidak Terakreditasi', 'min' => 0],
    ];

    protected function casts(): array
    {
        return [
            'status' => AccreditationVersionStatus::class,
            'scale_max' => 'float',
            'grade_thresholds' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(AccreditationInstrument::class, 'accreditation_instrument_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(AccreditationCriterion::class, 'instrument_version_id')->orderBy('sort_order');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(AccreditationIndicator::class, 'instrument_version_id')->orderBy('sort_order');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccreditationPeriod::class, 'instrument_version_id');
    }

    public function isEditable(): bool
    {
        return $this->status === AccreditationVersionStatus::Draft;
    }

    public function label(): string
    {
        return "{$this->instrument?->code} v{$this->version}";
    }

    /**
     * @return list<array{label: string, min: float}>
     */
    public function thresholds(): array
    {
        $rows = $this->grade_thresholds ?: self::DEFAULT_THRESHOLDS;
        usort($rows, fn (array $a, array $b): int => $b['min'] <=> $a['min']);

        return array_map(fn (array $row): array => ['label' => (string) $row['label'], 'min' => (float) $row['min']], $rows);
    }

    public function gradeFor(?float $score): ?string
    {
        if ($score === null) {
            return null;
        }

        foreach ($this->thresholds() as $threshold) {
            if ($score >= $threshold['min']) {
                return $threshold['label'];
            }
        }

        return null;
    }
}
