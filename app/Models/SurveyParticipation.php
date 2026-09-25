<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan bahwa seorang responden telah mengisi (eligibility), terpisah dari isi evaluasi.
 *
 * `response_ref` terenkripsi dan hanya dipakai untuk membatalkan respons lama saat
 * administrator membuka kembali pengisian (BR-002). Tidak pernah ditampilkan di UI.
 */
#[Fillable([
    'survey_id', 'user_id', 'teaching_assignment_id', 'target_key', 'status', 'response_ref', 'submitted_at',
    'reopened_at', 'reopened_by', 'reopen_reason', 'submission_count',
])]
#[Hidden(['response_ref'])]
class SurveyParticipation extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'response_ref' => 'encrypted',
            'submitted_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public static function targetKeyFor(?int $teachingAssignmentId): string
    {
        return $teachingAssignmentId ? "ta:{$teachingAssignmentId}" : 'general';
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teachingAssignment(): BelongsTo
    {
        return $this->belongsTo(TeachingAssignment::class);
    }

    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
