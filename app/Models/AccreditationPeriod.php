<?php

namespace App\Models;

use App\Enums\AccreditationPeriodStatus;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'study_program_id', 'instrument_version_id', 'code', 'name', 'status', 'pic_user_id', 'starts_on', 'submission_deadline',
    'submitted_on', 'visit_on', 'decided_on', 'target_score', 'result_grade', 'result_score', 'sk_number', 'certificate_number',
    'valid_from', 'valid_until', 'notes', 'created_by',
])]
class AccreditationPeriod extends Model
{
    use Auditable, ScopedToUser;

    protected string $auditModule = 'accreditation';

    protected function casts(): array
    {
        return [
            'status' => AccreditationPeriodStatus::class,
            'starts_on' => 'date',
            'submission_deadline' => 'date',
            'submitted_on' => 'date',
            'visit_on' => 'date',
            'decided_on' => 'date',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'target_score' => 'float',
            'result_score' => 'float',
        ];
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AccreditationInstrumentVersion::class, 'instrument_version_id');
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(AccreditationAssessment::class, 'period_id');
    }

    /**
     * Pengelola akreditasi, PIC periode, atau admin prodi terkait boleh mengisi penilaian diri & bukti.
     */
    public function canContribute(User $user): bool
    {
        if (! $this->status->isOpen()) {
            return false;
        }

        return $user->can('accreditation.manage')
            || $this->pic_user_id === $user->id
            || ($user->can('evidence.manage') && $user->hasRole(UserRole::AdminProdi->value) && $this->isVisibleTo($user));
    }

    public function daysToDeadline(): ?int
    {
        return $this->submission_deadline ? (int) now()->startOfDay()->diffInDays($this->submission_deadline, false) : null;
    }
}
