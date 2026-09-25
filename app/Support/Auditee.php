<?php

namespace App\Support;

use App\Enums\AuditeeType;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Pihak yang diaudit/penanggung jawab mutu: institusi, fakultas, prodi, atau unit kerja.
 */
final class Auditee
{
    /**
     * @return array{type: string, id: int|null, name: string, study_program_id: int|null, faculty_id: int|null}
     */
    public static function resolve(string $type, ?int $id): array
    {
        $auditeeType = AuditeeType::tryFrom($type) ?? throw ValidationException::withMessages(['auditee' => 'Jenis auditee tidak dikenal.']);

        $model = match ($auditeeType) {
            AuditeeType::Institution => Institution::current(),
            AuditeeType::Faculty => Faculty::query()->find($id),
            AuditeeType::StudyProgram => StudyProgram::query()->find($id),
            AuditeeType::Unit => Unit::query()->find($id),
        };

        if (! $model) {
            throw ValidationException::withMessages(['auditee' => 'Auditee tidak ditemukan.']);
        }

        return [
            'type' => $auditeeType->value,
            'id' => $model->getKey(),
            'name' => $model instanceof StudyProgram ? $model->full_name : $model->name,
            'study_program_id' => $model instanceof StudyProgram ? $model->id : null,
            'faculty_id' => match (true) {
                $model instanceof Faculty => $model->id,
                $model instanceof StudyProgram, $model instanceof Unit => $model->faculty_id,
                default => null,
            },
        ];
    }

    /**
     * Opsi auditee sesuai cakupan pengguna, dengan kunci "tipe:id".
     *
     * @return list<array{value: string, label: string, group: string}>
     */
    public static function options(User $user): array
    {
        $options = [];
        $wide = $user->isInstitutionWide();

        if ($wide) {
            $institution = Institution::current();
            if ($institution) {
                $options[] = ['value' => "institution:{$institution->id}", 'label' => $institution->name, 'group' => 'Institusi'];
            }

            foreach (Faculty::query()->where('is_active', true)->orderBy('name')->get() as $faculty) {
                $options[] = ['value' => "faculty:{$faculty->id}", 'label' => $faculty->name, 'group' => 'Fakultas'];
            }
        }

        foreach (StudyProgram::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get() as $program) {
            $options[] = ['value' => "study_program:{$program->id}", 'label' => $program->full_name, 'group' => 'Program studi'];
        }

        if ($wide) {
            foreach (Unit::query()->where('is_active', true)->orderBy('name')->get() as $unit) {
                $options[] = ['value' => "unit:{$unit->id}", 'label' => $unit->name, 'group' => 'Unit kerja'];
            }
        }

        return $options;
    }

    /**
     * @return array{0: string, 1: int|null}
     */
    public static function parse(string $key): array
    {
        [$type, $id] = array_pad(explode(':', $key, 2), 2, null);

        return [$type, $id !== null && $id !== '' ? (int) $id : null];
    }
}
