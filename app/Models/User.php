<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'username', 'email', 'phone', 'password', 'faculty_id', 'study_program_id', 'unit_id',
    'is_active', 'must_change_password', 'last_login_at', 'last_login_ip',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'must_change_password' => false,
    ];

    /**
     * Cache per-instance untuk cakupan prodi agar tidak dihitung berulang dalam satu request.
     *
     * @var list<int>|false|null
     */
    protected array|false|null $cachedStudyProgramIds = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lecturer(): HasOne
    {
        return $this->hasOne(Lecturer::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function auditor(): HasOne
    {
        return $this->hasOne(Auditor::class);
    }

    /**
     * @return list<UserRole>
     */
    public function userRoles(): array
    {
        return $this->getRoleNames()
            ->map(fn (string $name): ?UserRole => UserRole::tryFrom($name))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Peran utama untuk menentukan tampilan (dashboard & menu).
     */
    public function primaryRole(): ?UserRole
    {
        $roles = $this->userRoles();

        foreach (UserRole::cases() as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return null;
    }

    public function hasUserRole(UserRole ...$roles): bool
    {
        return $this->hasAnyRole(array_map(fn (UserRole $role): string => $role->value, $roles));
    }

    public function isInstitutionWide(): bool
    {
        return collect($this->userRoles())->contains(fn (UserRole $role): bool => $role->isInstitutionWide());
    }

    /**
     * Daftar ID prodi yang boleh diakses. `null` berarti seluruh institusi.
     *
     * @return list<int>|null
     */
    public function accessibleStudyProgramIds(): ?array
    {
        if ($this->cachedStudyProgramIds !== false) {
            return $this->cachedStudyProgramIds;
        }

        if ($this->isInstitutionWide()) {
            return $this->cachedStudyProgramIds = null;
        }

        $ids = collect();

        if ($this->hasUserRole(UserRole::AdminFakultas) && $this->faculty_id) {
            $ids = $ids->merge(StudyProgram::query()->where('faculty_id', $this->faculty_id)->pluck('id'));
        }

        if ($this->hasUserRole(UserRole::AdminProdi) && $this->study_program_id) {
            $ids->push($this->study_program_id);
        }

        if ($this->hasUserRole(UserRole::Dosen) && $this->lecturer) {
            $ids->push($this->lecturer->study_program_id);
        }

        if ($this->hasUserRole(UserRole::Mahasiswa) && $this->student) {
            $ids->push($this->student->study_program_id);
        }

        return $this->cachedStudyProgramIds = $ids->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    public function canAccessStudyProgram(int $studyProgramId): bool
    {
        $ids = $this->accessibleStudyProgramIds();

        return $ids === null || in_array($studyProgramId, $ids, true);
    }

    /**
     * Label cakupan untuk ditampilkan di UI (mis. "Institusi", "FTK", "S1 Tadris Matematika").
     */
    public function scopeLabel(): string
    {
        if ($this->isInstitutionWide()) {
            return 'Seluruh Institusi';
        }

        if ($this->hasUserRole(UserRole::AdminFakultas) && $this->faculty) {
            return $this->faculty->name;
        }

        if ($this->studyProgram) {
            return $this->studyProgram->full_name;
        }

        return $this->lecturer?->studyProgram?->full_name
            ?? $this->student?->studyProgram?->full_name
            ?? '—';
    }
}
