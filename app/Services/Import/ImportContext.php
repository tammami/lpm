<?php

namespace App\Services\Import;

use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\StudyProgram;
use App\Models\User;

/**
 * Cache pencarian (prodi, periode, mata kuliah) dan cakupan pengguna selama satu proses impor.
 */
class ImportContext
{
    /** @var array<string, StudyProgram|null> */
    private array $programs = [];

    /** @var array<string, AcademicPeriod|null> */
    private array $periods = [];

    /** @var array<string, Course|null> */
    private array $courses = [];

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(public User $user, public array $options = []) {}

    public function program(?string $code): ?StudyProgram
    {
        $code = strtoupper(trim((string) $code));

        return $this->programs[$code] ??= $code === '' ? null : StudyProgram::query()->where('code', $code)->first();
    }

    public function period(?string $code): ?AcademicPeriod
    {
        $code = trim((string) $code);

        return $this->periods[$code] ??= $code === '' ? null : AcademicPeriod::query()->where('code', $code)->first();
    }

    public function course(StudyProgram $program, ?string $code): ?Course
    {
        $key = $program->id.'|'.strtoupper((string) $code);

        return $this->courses[$key] ??= Course::query()->where('study_program_id', $program->id)->where('code', strtoupper((string) $code))->first();
    }

    /**
     * Validasi kode prodi: ada dan berada dalam cakupan pengguna.
     */
    public function programError(?string $code): ?string
    {
        $program = $this->program($code);

        if (! $program) {
            return 'Kode prodi tidak valid';
        }

        return $this->user->canAccessStudyProgram($program->id) ? null : 'Prodi di luar cakupan akses Anda';
    }
}
