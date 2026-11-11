<?php

namespace App\Models;

use App\Enums\AccreditationPeriodStatus;
use App\Enums\UserRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedToUser;
use Carbon\CarbonInterface;
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

    /**
     * Periode akreditasi institusi (APT) tidak terikat pada prodi.
     */
    public function isInstitutional(): bool
    {
        return $this->study_program_id === null;
    }

    /**
     * Nama yang diakreditasi: prodi, atau nama institusi untuk APT.
     */
    public function subjectName(): string
    {
        return $this->studyProgram?->full_name ?? Institution::current()?->name ?? 'Institusi';
    }

    /**
     * Status akreditasi yang berlaku saat ini. Untuk institusi diambil dari keputusan APT terakhir.
     *
     * @return array{grade: string|null, valid_until: string|null}
     */
    public function currentAccreditation(): array
    {
        if (! $this->isInstitutional()) {
            return ['grade' => $this->studyProgram?->accreditation_status, 'valid_until' => $this->studyProgram?->accreditation_valid_until?->toDateString()];
        }

        $latest = self::query()->whereNull('study_program_id')->where('status', AccreditationPeriodStatus::Decided)->latest('decided_on')->first();

        return ['grade' => $latest?->result_grade, 'valid_until' => $latest?->valid_until?->toDateString()];
    }

    /**
     * Ringkasan akreditasi institusi (APT): keputusan terakhir dan periode yang sedang berjalan.
     *
     * @return array{name: string, body: string|null, grade: string|null, valid_until: CarbonInterface|null, open: self|null}
     */
    public static function institutionSnapshot(): array
    {
        $institutional = self::query()->whereNull('study_program_id')->with(['version.instrument.body:id,code', 'pic:id,name']);
        $decided = (clone $institutional)->where('status', AccreditationPeriodStatus::Decided)->latest('decided_on')->first();
        $open = (clone $institutional)->whereIn('status', [AccreditationPeriodStatus::Preparing, AccreditationPeriodStatus::Submitted, AccreditationPeriodStatus::Visitation])->latest('id')->first();

        return [
            'name' => Institution::current()?->name ?? 'Institusi',
            'body' => ($open ?? $decided)?->version?->instrument?->body?->code ?? 'BAN-PT',
            'grade' => $decided?->result_grade,
            'valid_until' => $decided?->valid_until,
            'open' => $open,
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
