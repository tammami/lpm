<?php

namespace App\Models;

use App\Enums\RespondentType;
use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Models\Concerns\Auditable;
use App\Services\Settings;
use Database\Factories\SurveyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kegiatan survei/Monev: instrumen versi tertentu yang dibuka untuk responden pada rentang waktu tertentu.
 */
#[Fillable([
    'code', 'title', 'description', 'instrument_version_id', 'academic_period_id', 'mode', 'respondent_type',
    'is_anonymous', 'min_responses', 'starts_at', 'ends_at', 'status', 'created_by', 'opened_at', 'closed_at',
])]
class Survey extends Model
{
    /** @use HasFactory<SurveyFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'monev';

    protected function casts(): array
    {
        return [
            'mode' => SurveyMode::class,
            'respondent_type' => RespondentType::class,
            'status' => SurveyStatus::class,
            'is_anonymous' => 'boolean',
            'min_responses' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function instrumentVersion(): BelongsTo
    {
        return $this->belongsTo(InstrumentVersion::class);
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function studyPrograms(): BelongsToMany
    {
        return $this->belongsToMany(StudyProgram::class);
    }

    public function participations(): HasMany
    {
        return $this->hasMany(SurveyParticipation::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Survei yang sedang dapat diisi responden (status aktif dan dalam rentang waktu).
     *
     * @param  Builder<Survey>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', SurveyStatus::Active)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    public function isOpen(): bool
    {
        return $this->status === SurveyStatus::Active
            && $this->starts_at->isPast()
            && $this->ends_at->isFuture();
    }

    public function minimumResponses(): int
    {
        return $this->min_responses ?? (int) Settings::get('monev.min_responses', 5);
    }

    /**
     * @return list<int>
     */
    public function studyProgramIds(): array
    {
        return $this->studyPrograms->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
