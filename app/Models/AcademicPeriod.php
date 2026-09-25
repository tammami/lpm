<?php

namespace App\Models;

use App\Enums\AcademicSemester;
use App\Models\Concerns\Auditable;
use Database\Factories\AcademicPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'academic_year', 'semester', 'starts_on', 'ends_on', 'is_active'])]
class AcademicPeriod extends Model
{
    /** @use HasFactory<AcademicPeriodFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return [
            'semester' => AcademicSemester::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first()
            ?? static::query()->orderByDesc('starts_on')->first();
    }

    public function classes(): HasMany
    {
        return $this->hasMany(CourseClass::class);
    }
}
