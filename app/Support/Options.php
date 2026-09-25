<?php

namespace App\Support;

use App\Models\AcademicPeriod;
use App\Models\AccreditationBody;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Models\User;

/**
 * Opsi dropdown untuk frontend, sudah disaring sesuai cakupan pengguna.
 */
final class Options
{
    /**
     * @return list<array{value: int, label: string, faculty_id: int}>
     */
    public static function studyPrograms(User $user, bool $activeOnly = true): array
    {
        return StudyProgram::query()
            ->visibleTo($user)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'degree', 'faculty_id'])
            ->map(fn (StudyProgram $program): array => [
                'value' => $program->id,
                'label' => $program->full_name,
                'faculty_id' => $program->faculty_id,
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function faculties(): array
    {
        return Faculty::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Faculty $faculty): array => ['value' => $faculty->id, 'label' => $faculty->name])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function accreditationBodies(): array
    {
        return AccreditationBody::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn (AccreditationBody $body): array => ['value' => $body->id, 'label' => $body->code])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string, is_active: bool}>
     */
    public static function periods(): array
    {
        return AcademicPeriod::query()->orderByDesc('starts_on')->get(['id', 'name', 'is_active'])
            ->map(fn (AcademicPeriod $period): array => [
                'value' => $period->id,
                'label' => $period->name,
                'is_active' => $period->is_active,
            ])
            ->all();
    }

    /**
     * @return list<array{value: int, label: string, study_program_id: int}>
     */
    public static function lecturers(User $user, bool $scoped = true): array
    {
        return Lecturer::query()->when($scoped, fn ($query) => $query->visibleTo($user))->where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'front_title', 'back_title', 'study_program_id'])
            ->map(fn (Lecturer $lecturer): array => [
                'value' => $lecturer->id,
                'label' => $lecturer->full_name,
                'study_program_id' => $lecturer->study_program_id,
            ])
            ->all();
    }
}
