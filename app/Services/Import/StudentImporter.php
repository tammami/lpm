<?php

namespace App\Services\Import;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\AccountProvisioner;

class StudentImporter extends Importer
{
    public function __construct(private AccountProvisioner $accounts) {}

    public function type(): string
    {
        return 'mahasiswa';
    }

    public function label(): string
    {
        return 'Mahasiswa';
    }

    public function description(): string
    {
        return 'Data mahasiswa beserta akun login (username & kata sandi awal = NIM).';
    }

    public function columns(): array
    {
        return [
            ['key' => 'nim', 'label' => 'NIM', 'required' => true, 'example' => '2511001', 'note' => 'Nomor Induk Mahasiswa, unik.'],
            ['key' => 'nama', 'label' => 'Nama', 'required' => true, 'example' => 'Siti Aminah', 'note' => 'Nama lengkap.'],
            ['key' => 'kode_prodi', 'label' => 'Kode Prodi', 'required' => true, 'example' => 'TMTK', 'note' => 'Sesuai master Program Studi.'],
            ['key' => 'angkatan', 'label' => 'Angkatan', 'required' => true, 'example' => '2025', 'note' => 'Tahun masuk (4 digit).'],
            ['key' => 'semester', 'label' => 'Semester', 'required' => false, 'example' => '3', 'note' => 'Semester berjalan (1–14).'],
            ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'required' => false, 'example' => 'P', 'note' => 'L atau P.'],
            ['key' => 'email', 'label' => 'Email', 'required' => false, 'example' => 'siti@student.ac.id', 'note' => 'Untuk notifikasi & reset kata sandi.'],
            ['key' => 'no_hp', 'label' => 'No HP', 'required' => false, 'example' => '081234567890', 'note' => ''],
            ['key' => 'status', 'label' => 'Status', 'required' => false, 'example' => 'aktif', 'note' => 'aktif, cuti, non_aktif, lulus, keluar. Default aktif.'],
        ];
    }

    public function key(array $row): ?string
    {
        return $row['nim'] ?? null;
    }

    public function exists(string $key, ImportContext $context): bool
    {
        return Student::query()->where('nim', $key)->exists();
    }

    public function validate(array $row, ImportContext $context): array
    {
        $errors = $this->requireColumns($row, ['nim', 'nama', 'kode_prodi', 'angkatan']);

        if (! empty($row['kode_prodi']) && $error = $context->programError($row['kode_prodi'])) {
            $errors['kode_prodi'] = $error;
        }
        if (! empty($row['angkatan']) && (! ctype_digit((string) $row['angkatan']) || (int) $row['angkatan'] < 1990 || (int) $row['angkatan'] > 2100)) {
            $errors['angkatan'] = 'Angkatan harus tahun 4 digit';
        }
        if (! empty($row['semester']) && (! ctype_digit((string) $row['semester']) || (int) $row['semester'] < 1 || (int) $row['semester'] > 14)) {
            $errors['semester'] = 'Semester harus 1–14';
        }
        if (! empty($row['jenis_kelamin']) && ! in_array(strtoupper((string) $row['jenis_kelamin']), ['L', 'P'], true)) {
            $errors['jenis_kelamin'] = 'Isi L atau P';
        }
        if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid';
        }
        if (! empty($row['status']) && ! StudentStatus::tryFrom(strtolower((string) $row['status']))) {
            $errors['status'] = 'Status tidak dikenal';
        }

        return $errors;
    }

    public function persist(array $row, ImportContext $context, bool $updateExisting): string
    {
        $existing = Student::query()->where('nim', $row['nim'])->first();

        if ($existing && ! $updateExisting) {
            return 'skipped';
        }

        $student = Student::query()->updateOrCreate(['nim' => $row['nim']], [
            'study_program_id' => $context->program($row['kode_prodi'])->id,
            'name' => $row['nama'],
            'entry_year' => (int) $row['angkatan'],
            'semester' => (int) ($row['semester'] ?? 1),
            'gender' => isset($row['jenis_kelamin']) ? strtoupper((string) $row['jenis_kelamin']) : null,
            'email' => $row['email'] ?? null,
            'phone' => $row['no_hp'] ?? null,
            'status' => StudentStatus::tryFrom(strtolower((string) ($row['status'] ?? 'aktif'))) ?? StudentStatus::Aktif,
        ]);

        $this->accounts->forStudent($student);

        return $existing ? 'updated' : 'created';
    }
}
