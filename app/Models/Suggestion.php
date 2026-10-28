<?php

namespace App\Models;

use App\Enums\SuggestionCategory;
use App\Enums\SuggestionStatus;
use Database\Factories\SuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saran dari mahasiswa/dosen untuk LPM. Pengirim tanpa nama tetap tercatat agar dapat membaca tanggapan,
 * tetapi nama dan prodinya tidak pernah dikirim ke halaman LPM. Sengaja tidak memakai Auditable agar isi saran anonim tidak tersalin ke log audit.
 */
#[Fillable([
    'user_id', 'sender_role', 'study_program_id', 'is_anonymous', 'category', 'subject', 'message', 'status',
    'response', 'responded_by', 'responded_at',
])]
class Suggestion extends Model
{
    /** @use HasFactory<SuggestionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'category' => SuggestionCategory::class,
            'status' => SuggestionStatus::class,
            'is_anonymous' => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
