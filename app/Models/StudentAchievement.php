<?php

namespace App\Models;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementStatus;
use App\Models\Concerns\Auditable;
use Database\Factories\StudentAchievementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prestasi mahasiswa yang dicatat sendiri, lalu diverifikasi bidang kemahasiswaan.
 */
#[Fillable([
    'student_id', 'title', 'category', 'level', 'achievement', 'participation', 'organizer', 'achieved_on', 'description',
    'certificate_path', 'certificate_name', 'evidence_url', 'status', 'verification_note', 'verified_by', 'verified_at',
])]
class StudentAchievement extends Model
{
    /** @use HasFactory<StudentAchievementFactory> */
    use Auditable, HasFactory;

    protected string $auditModule = 'achievements';

    public const PARTICIPATIONS = [
        'individual' => 'Individu',
        'team' => 'Beregu / tim',
    ];

    /** Saran isian "Prestasi yang dicapai"; mahasiswa tetap boleh menulis sendiri. */
    public const ACHIEVEMENT_SUGGESTIONS = [
        'Juara 1', 'Juara 2', 'Juara 3', 'Juara Harapan', 'Medali Emas', 'Medali Perak', 'Medali Perunggu',
        'Finalis', 'Penerima Hibah', 'Delegasi / Peserta Terpilih', 'Penghargaan Khusus',
    ];

    protected function casts(): array
    {
        return [
            'category' => AchievementCategory::class,
            'level' => AchievementLevel::class,
            'status' => AchievementStatus::class,
            'achieved_on' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Batasi ke prestasi mahasiswa dalam cakupan prodi pengguna.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $programIds = $user->accessibleStudyProgramIds();

        if ($programIds !== null) {
            $query->whereHas('student', fn (Builder $student) => $student->whereIn('study_program_id', $programIds));
        }
    }
}
