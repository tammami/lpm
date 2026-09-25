<?php

namespace App\Services\Import;

use App\Models\CourseClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class EnrollmentImporter extends Importer
{
    public function type(): string
    {
        return 'peserta_kelas';
    }

    public function label(): string
    {
        return 'Peserta Kelas (KRS)';
    }

    public function description(): string
    {
        return 'Daftar mahasiswa per kelas — menentukan siapa yang mengevaluasi dosen di kelas tersebut.';
    }

    public function columns(): array
    {
        return [
            ['key' => 'kode_periode', 'label' => 'Kode Periode', 'required' => true, 'example' => '20261', 'note' => ''],
            ['key' => 'kode_prodi', 'label' => 'Kode Prodi', 'required' => true, 'example' => 'TMTK', 'note' => 'Prodi pemilik mata kuliah.'],
            ['key' => 'kode_mk', 'label' => 'Kode MK', 'required' => true, 'example' => 'TMTK301', 'note' => ''],
            ['key' => 'kelas', 'label' => 'Kelas', 'required' => true, 'example' => 'A', 'note' => 'Kelas harus sudah dibuka (impor Kelas & Dosen Pengampu dulu).'],
            ['key' => 'nim', 'label' => 'NIM', 'required' => true, 'example' => '2511001', 'note' => ''],
        ];
    }

    public function normalize(array $row): array
    {
        if (isset($row['kelas'])) {
            $row['kelas'] = strtoupper((string) $row['kelas']);
        }

        return $row;
    }

    public function key(array $row): ?string
    {
        return isset($row['kode_periode'], $row['kode_prodi'], $row['kode_mk'], $row['kelas'], $row['nim'])
            ? strtoupper(implode('|', [$row['kode_periode'], $row['kode_prodi'], $row['kode_mk'], $row['kelas'], $row['nim']]))
            : null;
    }

    public function exists(string $key, ImportContext $context): bool
    {
        [$periodCode, $programCode, $courseCode, $classCode, $nim] = explode('|', $key);
        $class = $this->findClass($context, $periodCode, $programCode, $courseCode, $classCode);
        $studentId = Student::query()->where('nim', $nim)->value('id');

        return $class && $studentId && DB::table('class_enrollments')->where('course_class_id', $class->id)->where('student_id', $studentId)->exists();
    }

    public function validate(array $row, ImportContext $context): array
    {
        $errors = $this->requireColumns($row, ['kode_periode', 'kode_prodi', 'kode_mk', 'kelas', 'nim']);

        if ($errors !== []) {
            return $errors;
        }
        if ($error = $context->programError($row['kode_prodi'])) {
            return ['kode_prodi' => $error];
        }
        if (! $this->findClass($context, $row['kode_periode'], $row['kode_prodi'], $row['kode_mk'], $row['kelas'])) {
            $errors['kelas'] = 'Kelas belum dibuka pada periode tersebut';
        }
        if (! Student::query()->where('nim', $row['nim'])->exists()) {
            $errors['nim'] = 'NIM tidak ditemukan';
        }

        return $errors;
    }

    public function persist(array $row, ImportContext $context, bool $updateExisting): string
    {
        $class = $this->findClass($context, $row['kode_periode'], $row['kode_prodi'], $row['kode_mk'], $row['kelas']);
        $studentId = Student::query()->where('nim', $row['nim'])->value('id');
        $result = $class->students()->syncWithoutDetaching([$studentId]);

        return $result['attached'] === [] ? 'skipped' : 'created';
    }

    private function findClass(ImportContext $context, string $periodCode, string $programCode, string $courseCode, string $classCode): ?CourseClass
    {
        $program = $context->program($programCode);
        $period = $context->period($periodCode);
        $course = $program ? $context->course($program, $courseCode) : null;

        return $course && $period
            ? CourseClass::query()->where('course_id', $course->id)->where('academic_period_id', $period->id)->where('code', $classCode)->first()
            : null;
    }
}
