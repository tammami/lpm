<?php

namespace App\Services\Import;

use App\Models\Course;

class CourseImporter extends Importer
{
    public function type(): string
    {
        return 'mata_kuliah';
    }

    public function label(): string
    {
        return 'Mata Kuliah';
    }

    public function description(): string
    {
        return 'Kurikulum mata kuliah per program studi.';
    }

    public function columns(): array
    {
        return [
            ['key' => 'kode_mk', 'label' => 'Kode MK', 'required' => true, 'example' => 'TMTK301', 'note' => 'Unik per prodi.'],
            ['key' => 'nama', 'label' => 'Nama', 'required' => true, 'example' => 'Matematika Diskrit', 'note' => ''],
            ['key' => 'kode_prodi', 'label' => 'Kode Prodi', 'required' => true, 'example' => 'TMTK', 'note' => ''],
            ['key' => 'sks', 'label' => 'SKS', 'required' => true, 'example' => '3', 'note' => '1–24.'],
            ['key' => 'semester', 'label' => 'Semester', 'required' => true, 'example' => '3', 'note' => '1–14.'],
            ['key' => 'sifat', 'label' => 'Sifat', 'required' => false, 'example' => 'wajib', 'note' => 'wajib atau pilihan. Default wajib.'],
        ];
    }

    public function key(array $row): ?string
    {
        return isset($row['kode_prodi'], $row['kode_mk']) ? strtoupper($row['kode_prodi'].'|'.$row['kode_mk']) : null;
    }

    public function exists(string $key, ImportContext $context): bool
    {
        [$programCode, $code] = explode('|', $key);
        $program = $context->program($programCode);

        return $program !== null && $context->course($program, $code) !== null;
    }

    public function validate(array $row, ImportContext $context): array
    {
        $errors = $this->requireColumns($row, ['kode_mk', 'nama', 'kode_prodi', 'sks', 'semester']);

        if (! empty($row['kode_prodi']) && $error = $context->programError($row['kode_prodi'])) {
            $errors['kode_prodi'] = $error;
        }
        if (! empty($row['sks']) && (! ctype_digit((string) $row['sks']) || (int) $row['sks'] < 1 || (int) $row['sks'] > 24)) {
            $errors['sks'] = 'SKS harus 1–24';
        }
        if (! empty($row['semester']) && (! ctype_digit((string) $row['semester']) || (int) $row['semester'] < 1 || (int) $row['semester'] > 14)) {
            $errors['semester'] = 'Semester harus 1–14';
        }
        if (! empty($row['sifat']) && ! in_array(strtolower((string) $row['sifat']), ['wajib', 'pilihan'], true)) {
            $errors['sifat'] = 'Isi wajib atau pilihan';
        }

        return $errors;
    }

    public function persist(array $row, ImportContext $context, bool $updateExisting): string
    {
        $program = $context->program($row['kode_prodi']);
        $existing = Course::query()->where('study_program_id', $program->id)->where('code', strtoupper($row['kode_mk']))->first();

        if ($existing && ! $updateExisting) {
            return 'skipped';
        }

        Course::query()->updateOrCreate(['study_program_id' => $program->id, 'code' => strtoupper($row['kode_mk'])], [
            'name' => $row['nama'],
            'credits' => (int) $row['sks'],
            'semester' => (int) $row['semester'],
            'type' => strtolower((string) ($row['sifat'] ?? 'wajib')),
            'is_active' => true,
        ]);

        return $existing ? 'updated' : 'created';
    }
}
