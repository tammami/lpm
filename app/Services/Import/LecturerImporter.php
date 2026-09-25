<?php

namespace App\Services\Import;

use App\Http\Controllers\MasterData\LecturerController;
use App\Models\Lecturer;
use App\Services\AccountProvisioner;

class LecturerImporter extends Importer
{
    public function __construct(private AccountProvisioner $accounts) {}

    public function type(): string
    {
        return 'dosen';
    }

    public function label(): string
    {
        return 'Dosen';
    }

    public function description(): string
    {
        return 'Data dosen per homebase prodi beserta akun login (username & kata sandi awal = NIDN).';
    }

    public function columns(): array
    {
        return [
            ['key' => 'nidn', 'label' => 'NIDN', 'required' => true, 'example' => '0812345678', 'note' => '10 digit. Format sel sebagai teks agar angka 0 di depan tidak hilang.'],
            ['key' => 'nama', 'label' => 'Nama', 'required' => true, 'example' => 'Ahmad Fauzi', 'note' => 'Nama tanpa gelar.'],
            ['key' => 'gelar_depan', 'label' => 'Gelar Depan', 'required' => false, 'example' => 'Dr.', 'note' => ''],
            ['key' => 'gelar_belakang', 'label' => 'Gelar Belakang', 'required' => false, 'example' => 'M.Pd.', 'note' => ''],
            ['key' => 'kode_prodi', 'label' => 'Kode Prodi', 'required' => true, 'example' => 'TMTK', 'note' => 'Homebase dosen.'],
            ['key' => 'jabatan', 'label' => 'Jabatan Fungsional', 'required' => false, 'example' => 'Lektor', 'note' => implode(', ', LecturerController::RANKS)],
            ['key' => 'status_kepegawaian', 'label' => 'Status Kepegawaian', 'required' => false, 'example' => 'tetap', 'note' => 'tetap atau tidak_tetap.'],
            ['key' => 'nip', 'label' => 'NIP/NIY', 'required' => false, 'example' => '', 'note' => ''],
            ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'required' => false, 'example' => 'L', 'note' => 'L atau P.'],
            ['key' => 'email', 'label' => 'Email', 'required' => false, 'example' => 'ahmad@iaia.ac.id', 'note' => ''],
            ['key' => 'no_hp', 'label' => 'No HP', 'required' => false, 'example' => '', 'note' => ''],
        ];
    }

    public function normalize(array $row): array
    {
        if (isset($row['nidn']) && ctype_digit((string) $row['nidn']) && strlen((string) $row['nidn']) === 9) {
            $row['nidn'] = '0'.$row['nidn'];
        }

        return $row;
    }

    public function key(array $row): ?string
    {
        return $row['nidn'] ?? null;
    }

    public function exists(string $key, ImportContext $context): bool
    {
        return Lecturer::query()->where('nidn', $key)->exists();
    }

    public function validate(array $row, ImportContext $context): array
    {
        $errors = $this->requireColumns($row, ['nidn', 'nama', 'kode_prodi']);

        if (! empty($row['nidn']) && ! preg_match('/^\d{8,12}$/', (string) $row['nidn'])) {
            $errors['nidn'] = 'NIDN harus berupa 8–12 digit angka';
        }
        if (! empty($row['kode_prodi']) && $error = $context->programError($row['kode_prodi'])) {
            $errors['kode_prodi'] = $error;
        }
        if (! empty($row['jabatan']) && ! in_array($row['jabatan'], LecturerController::RANKS, true)) {
            $errors['jabatan'] = 'Jabatan tidak dikenal';
        }
        if (! empty($row['status_kepegawaian']) && ! in_array($row['status_kepegawaian'], ['tetap', 'tidak_tetap'], true)) {
            $errors['status_kepegawaian'] = 'Isi tetap atau tidak_tetap';
        }
        if (! empty($row['email']) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format email tidak valid';
        }

        return $errors;
    }

    public function persist(array $row, ImportContext $context, bool $updateExisting): string
    {
        $existing = Lecturer::query()->where('nidn', $row['nidn'])->first();

        if ($existing && ! $updateExisting) {
            return 'skipped';
        }

        $lecturer = Lecturer::query()->updateOrCreate(['nidn' => $row['nidn']], [
            'study_program_id' => $context->program($row['kode_prodi'])->id,
            'name' => $row['nama'],
            'front_title' => $row['gelar_depan'] ?? null,
            'back_title' => $row['gelar_belakang'] ?? null,
            'academic_rank' => $row['jabatan'] ?? null,
            'employment_status' => $row['status_kepegawaian'] ?? 'tetap',
            'nip' => $row['nip'] ?? null,
            'gender' => isset($row['jenis_kelamin']) ? strtoupper((string) $row['jenis_kelamin']) : null,
            'email' => $row['email'] ?? null,
            'phone' => $row['no_hp'] ?? null,
            'is_active' => true,
        ]);

        $this->accounts->forLecturer($lecturer);

        return $existing ? 'updated' : 'created';
    }
}
