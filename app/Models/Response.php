<?php

namespace App\Models;

use App\Enums\ScoringMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Isi evaluasi. Menggunakan UUID v4 (acak, bukan berurutan waktu) dan hanya menyimpan
 * tanggal pengiriman agar tidak dapat dikaitkan dengan partisipasi mahasiswa.
 */
#[Fillable([
    'survey_id', 'instrument_version_id', 'teaching_assignment_id', 'course_class_id', 'lecturer_id',
    'study_program_id', 'respondent_user_id', 'scoring_method', 'score', 'submitted_on', 'voided_at',
])]
class Response extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'scoring_method' => ScoringMethod::class,
            'score' => 'float',
            'submitted_on' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * Hanya respons sah (tidak dibatalkan).
     *
     * @param  Builder<Response>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull($this->qualifyColumn('voided_at'));
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function instrumentVersion(): BelongsTo
    {
        return $this->belongsTo(InstrumentVersion::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function courseClass(): BelongsTo
    {
        return $this->belongsTo(CourseClass::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ResponseAnswer::class);
    }
}
