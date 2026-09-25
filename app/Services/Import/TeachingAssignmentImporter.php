<?php

namespace App\Services\Import;

use App\Models\CourseClass;
use App\Models\Lecturer;
use App\Models\TeachingAssignment;

/**
 * Membuka kelas (bila belum ada) sekaligus menugaskan dosen pengampu.
 */
class TeachingAssignmentImporter extends Importer
{
    public function type(): string
    {
        return 'penugasan_mengajar';
    }

    public function label(): string
    {
        return 'Kelas & Dosen Pengampu';
    }

    public function description(): string
    {
        return 'Membuka kelas per periode sekaligus menugaskan dosen pengampu (dasar eligibility Monev).';
    }

    public function columns(): array
    {
        return [
            ['key' => 'kode_periode', 'label' => 'Kode Periode', 'required' => true, 'example' => '20261', 'note' => 'Sesuai master Periode Akademik.'],
            ['key' => 'kode_prodi', 'label' => 'Kode Prodi', 'required' => true, 'example' => 'TMTK', 'note' => 'Prodi pemilik mata kuliah.'],
            ['key' => 'kode_mk', 'label' => 'Kode MK', 'required' => true, 'example' => 'TMTK301', 'note' => ''],
            ['key' => 'kelas', 'label' => 'Kelas', 'required' => true, 'example' => 'A', 'note' => 'Kode kelas/rombel.'],
            ['key' => 'nidn', 'label' => 'NIDN', 'required' => true, 'example' => '0812345678', 'note' => 'Dosen pengampu. Satu baris per dosen (team teaching = beberapa baris).'],
            ['key' => 'peran', 'label' => 'Peran', 'required' => false, 'example' => 'koordinator', 'note' => 'koordinator, anggota, atau pengampu. Default pengampu.'],
        ];
    }

    public function normalize(array $row): array
    {
        if (isset($row['nidn']) && ctype_digit((string) $row['nidn']) && strlen((string) $row['nidn']) === 9) {
            $row['nidn'] = '0'.$row['nidn'];
        }
        if (isset($row['kelas'])) {
            $row['kelas'] = strtoupper((string) $row['kelas']);
        }

        return $row;
    }

    public function key(array $row): ?string
    {
        return isset($row['kode_periode'], $row['kode_prodi'], $row['kode_mk'], $row['kelas'], $row['nidn'])
            ? strtoupper(implode('|', [$row['kode_periode'], $row['kode_prodi'], $row['kode_mk'], $row['kelas'], $row['nidn']]))
            : null;
    }

    public function exists(string $key, ImportContext $context): bool
    {
        return $this->findAssignment($key, $context) !== null;
    }

    public function validate(array $row, ImportContext $context): array
    {
        $errors = $this->requireColumns($row, ['kode_periode', 'kode_prodi', 'kode_mk', 'kelas', 'nidn']);

        if (! empty($row['kode_periode']) && ! $context->period($row['kode_periode'])) {
            $errors['kode_periode'] = 'Kode periode tidak ditemukan';
        }
        if (! empty($row['kode_prodi']) && $error = $context->programError($row['kode_prodi'])) {
            $errors['kode_prodi'] = $error;
        } elseif (! empty($row['kode_mk']) && ! $context->course($context->program($row['kode_prodi']), $row['kode_mk'])) {
            $errors['kode_mk'] = 'Mata kuliah tidak ditemukan pada prodi ini';
        }
        if (! empty($row['nidn']) && ! Lecturer::query()->where('nidn', $row['nidn'])->exists()) {
            $errors['nidn'] = 'NIDN tidak ditemukan';
        }
        if (! empty($row['peran']) && ! in_array($row['peran'], ['koordinator', 'anggota', 'pengampu'], true)) {
            $errors['peran'] = 'Peran tidak dikenal';
        }

        return $errors;
    }

    public function persist(array $row, ImportContext $context, bool $updateExisting): string
    {
        $course = $context->course($context->program($row['kode_prodi']), $row['kode_mk']);
        $class = CourseClass::query()->firstOrCreate(
            ['course_id' => $course->id, 'academic_period_id' => $context->period($row['kode_periode'])->id, 'code' => $row['kelas']],
            ['capacity' => 40],
        );
        $lecturerId = Lecturer::query()->where('nidn', $row['nidn'])->value('id');
        $existing = TeachingAssignment::query()->where('course_class_id', $class->id)->where('lecturer_id', $lecturerId)->first();

        if ($existing) {
            if (! $updateExisting) {
                return 'skipped';
            }
            $existing->update(['role' => $row['peran'] ?? $existing->role]);

            return 'updated';
        }

        TeachingAssignment::query()->create(['course_class_id' => $class->id, 'lecturer_id' => $lecturerId, 'role' => $row['peran'] ?? 'pengampu']);

        return 'created';
    }

    private function findAssignment(string $key, ImportContext $context): ?TeachingAssignment
    {
        [$periodCode, $programCode, $courseCode, $classCode, $nidn] = explode('|', $key);
        $program = $context->program($programCode);
        $period = $context->period($periodCode);
        $course = $program ? $context->course($program, $courseCode) : null;

        if (! $course || ! $period) {
            return null;
        }

        return TeachingAssignment::query()
            ->whereHas('courseClass', fn ($q) => $q->where('course_id', $course->id)->where('academic_period_id', $period->id)->where('code', $classCode))
            ->whereHas('lecturer', fn ($q) => $q->where('nidn', $nidn))
            ->first();
    }
}
