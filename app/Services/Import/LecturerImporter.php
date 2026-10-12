<?php

namespace App\Services\Import;

use App\Http\Controllers\MasterData\LecturerController;
use App\Models\Lecturer;
use App\Services\AccountProvisioner;
use Illuminate\Database\Eloquent\Collection;

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
        return 'Data dosen per homebase prodi beserta akun login (username & kata sandi awal = NIDN, atau NUPTK bila belum ber-NIDN).';
    }

    public function columns(): array
    {
        return [
            ['key' => 'nidn', 'label' => 'NIDN/NIDK', 'required' => false, 'example' => '0812345678', 'note' => '10 digit. Isi NIDN/NIDK atau NUPTK — minimal salah satu.', 'text' => true],
            ['key' => 'nuptk', 'label' => 'NUPTK', 'required' => false, 'example' => '', 'note' => '16 digit. Wajib bila dosen belum memiliki NIDN/NIDK. Kolom template sudah berformat Teks; bila memakai file sendiri, format kolom sebagai Teks agar digit terakhir tidak berubah.', 'text' => true],
            ['key' => 'nama', 'label' => 'Nama', 'required' => true, 'example' => 'Ahmad Fauzi', 'note' => 'Nama tanpa gelar.'],
            ['key' => 'gelar_depan', 'label' => 'Gelar Depan', 'required' => false, 'example' => 'Dr.', 'note' => ''],
            ['key' => 'gelar_belakang', 'label' => 'Gelar Belakang', 'required' => false, 'example' => 'M.Pd.', 'note' => ''],
            ['key' => 'kode_prodi', 'label' => 'Kode Prodi', 'required' => true, 'example' => 'TMTK', 'note' => 'Homebase dosen.'],
            ['key' => 'jabatan', 'label' => 'Jabatan Fungsional', 'required' => false, 'example' => 'Lektor', 'note' => implode(', ', LecturerController::RANKS)],
            ['key' => 'status_kepegawaian', 'label' => 'Status Kepegawaian', 'required' => false, 'example' => 'tetap', 'note' => 'tetap atau tidak_tetap.'],
            ['key' => 'nip', 'label' => 'NIP/NIY', 'required' => false, 'example' => '', 'note' => '', 'text' => true],
            ['key' => 'jenis_kelamin', 'label' => 'Jenis Kelamin', 'required' => false, 'example' => 'L', 'note' => 'L atau P.'],
            ['key' => 'email', 'label' => 'Email', 'required' => false, 'example' => 'ahmad@iaia.ac.id', 'note' => ''],
            ['key' => 'no_hp', 'label' => 'No HP', 'required' => false, 'example' => '', 'note' => ''],
        ];
    }

    public function normalize(array $row): array
    {
        foreach (['nidn', 'nuptk'] as $column) {
            if (is_string($row[$column] ?? null)) {
                $row[$column] = preg_replace('/\s+/', '', $row[$column]) ?: null;
            }
        }

        if (isset($row['nidn']) && ctype_digit((string) $row['nidn']) && strlen((string) $row['nidn']) === 9) {
            $row['nidn'] = '0'.$row['nidn'];
        }

        return $row;
    }

    /**
     * Kunci gabungan NIDN/NIDK + NUPTK, mis. "nidn:0812345678|nuptk:7350765666130123".
     */
    public function key(array $row): ?string
    {
        $parts = array_filter(['nidn' => $row['nidn'] ?? null, 'nuptk' => $row['nuptk'] ?? null], fn ($value): bool => $value !== null && $value !== '');

        return $parts === [] ? null : implode('|', array_map(fn (string $column, mixed $value): string => "{$column}:{$value}", array_keys($parts), $parts));
    }

    public function exists(string $key, ImportContext $context): bool
    {
        $numbers = collect(explode('|', $key))->mapWithKeys(function (string $part): array {
            [$column, $value] = explode(':', $part, 2);

            return [$column => $value];
        });

        return $this->matches($numbers->all())->isNotEmpty();
    }

    public function validate(array $row, ImportContext $context): array
    {
        $errors = $this->requireColumns($row, ['nama', 'kode_prodi']);

        if (empty($row['nidn']) && empty($row['nuptk'])) {
            $errors['nidn'] = 'Isi NIDN/NIDK atau NUPTK (minimal salah satu)';
        }
        if (! empty($row['nidn']) && ! preg_match('/^\d{8,12}$/', (string) $row['nidn'])) {
            $errors['nidn'] = 'NIDN/NIDK harus berupa 8–12 digit angka';
        }
        if (! empty($row['nuptk'])) {
            $error = $this->impreciseNumberError($row['nuptk'], 'NUPTK')
                ?? (preg_match('/^\d{16}$/', (string) $row['nuptk']) ? null : 'NUPTK harus 16 digit angka');

            if ($error) {
                $errors['nuptk'] = $error;
            }
        }
        if (! isset($errors['nidn']) && ! isset($errors['nuptk']) && $this->matches($row)->count() > 1) {
            $errors['nuptk'] = 'NIDN/NIDK dan NUPTK ini tercatat pada dua dosen berbeda';
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
        $existing = $this->matches($row)->first();

        if ($existing && ! $updateExisting) {
            return 'skipped';
        }

        $lecturer = $existing ?? new Lecturer;

        // Nomor yang kosong di file tidak menghapus nomor yang sudah tercatat.
        $lecturer->fill(array_filter(['nidn' => $row['nidn'] ?? null, 'nuptk' => $row['nuptk'] ?? null]));
        $lecturer->fill([
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
        ])->save();

        $this->accounts->forLecturer($lecturer);

        return $existing ? 'updated' : 'created';
    }

    /**
     * Dosen yang sudah tercatat dengan NIDN/NIDK atau NUPTK baris ini.
     *
     * @param  array<string, mixed>  $row
     * @return Collection<int, Lecturer>
     */
    private function matches(array $row): Collection
    {
        $nidn = $row['nidn'] ?? null;
        $nuptk = $row['nuptk'] ?? null;

        if (! $nidn && ! $nuptk) {
            return new Collection;
        }

        return Lecturer::query()
            ->where(fn ($query) => $query
                ->when($nidn, fn ($q) => $q->orWhere('nidn', $nidn))
                ->when($nuptk, fn ($q) => $q->orWhere('nuptk', $nuptk)))
            ->get();
    }
}
