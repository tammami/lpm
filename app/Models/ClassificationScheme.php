<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Skema klasifikasi skor (mis. 0–1 Sangat Rendah … 3–4 Sangat Baik) yang dapat diatur admin.
 */
#[Fillable(['name', 'scale_min', 'scale_max', 'description', 'is_default'])]
class ClassificationScheme extends Model
{
    protected function casts(): array
    {
        return [
            'scale_min' => 'float',
            'scale_max' => 'float',
            'is_default' => 'boolean',
        ];
    }

    public static function default(): ?self
    {
        return static::query()->with('classifications')->where('is_default', true)->first()
            ?? static::query()->with('classifications')->first();
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(ScoreClassification::class)->orderBy('sort_order')->orderBy('min_score');
    }

    /**
     * Klasifikasikan skor. Batas bawah inklusif untuk kelas pertama, eksklusif untuk kelas berikutnya
     * (sesuai konvensi "0–1, >1–2, >2–3, >3–4").
     */
    public function classify(?float $score): ?ScoreClassification
    {
        if ($score === null) {
            return null;
        }

        $classes = $this->classifications->sortBy('min_score')->values();

        foreach ($classes as $index => $class) {
            $aboveMin = $index === 0 ? $score >= $class->min_score : $score > $class->min_score;

            if ($aboveMin && $score <= $class->max_score) {
                return $class;
            }
        }

        return null;
    }
}
