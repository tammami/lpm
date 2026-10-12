<?php

namespace App\Services\Import;

use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\Student;
use App\Services\Research\ScientificWorks;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Impor sheet "Data Karya Dosen" dari template Excel LPM (atau dari file ekspor aplikasi).
 * Validasi isian memakai aturan yang sama dengan formulir karya ilmiah.
 */
class ScientificWorkImporter extends Importer
{
    /** Kolom isian aplikasi → kolom template, untuk memetakan pesan validasi. */
    private const FIELD_COLUMNS = [
        'title' => 'judul', 'type' => 'subjenis', 'year' => 'tahun', 'venue' => 'media', 'indexation' => 'indeksasi',
        'author_role' => 'peran', 'author_count' => 'jumlah_penulis', 'co_authors' => 'penulis_lain', 'source' => 'asal_kegiatan',
        'funding' => 'sumber_dana', 'relevant_to_program' => 'relevansi_prodi', 'relevant_to_roadmap' => 'relevansi_roadmap',
        'identifier' => 'issn', 'doi' => 'doi', 'volume' => 'volume', 'url' => 'link_publikasi', 'evidence_url' => 'link_bukti',
        'ip_number' => 'nomor_hki', 'ip_status' => 'status_hki', 'lecturers' => 'dosen_rekan', 'students' => 'mahasiswa',
    ];

    /** @var Collection<int, Lecturer>|null */
    private ?Collection $lecturers = null;

    public function __construct(private ScientificWorks $works) {}

    public function type(): string
    {
        return 'karya_ilmiah';
    }

    public function label(): string
    {
        return 'Karya Ilmiah Dosen';
    }

    public function description(): string
    {
        return 'Sheet "Data Karya Dosen" dari template Excel LPM. Dosen dicocokkan lewat NIDN/NIDK, NUPTK, atau nama; mahasiswa & dosen rekan ditandai lewat kolom tambahan.';
    }

    public function sheetName(): ?string
    {
        return 'Data Karya Dosen';
    }

    public function columns(): array
    {
        $types = implode(', ', array_map(fn (ScientificWorkType $type): string => $type->label(), ScientificWorkType::cases()));

        return [
            ['key' => 'no', 'label' => 'No.', 'required' => false, 'example' => '1', 'note' => 'Nomor urut — diabaikan.'],
            ['key' => 'nama_dosen', 'label' => 'Nama Dosen', 'required' => false, 'example' => 'Ahmad Fauzi', 'note' => 'Dipakai bila NIDN/NIDK dan NUPTK kosong; nama harus persis sama dengan data dosen.'],
            ['key' => 'nidn', 'label' => 'NIDN/NIDK', 'required' => false, 'example' => '0812345678', 'note' => 'NIDN/NIDK dosen pemilik karya.', 'text' => true],
            ['key' => 'program_studi', 'label' => 'Program Studi', 'required' => false, 'example' => '', 'note' => 'Diabaikan — prodi mengikuti homebase dosen.'],
            ['key' => 'fakultas', 'label' => 'Fakultas/Unit', 'required' => false, 'example' => '', 'note' => 'Diabaikan.'],
            ['key' => 'jenis_karya', 'label' => 'Jenis Karya', 'required' => false, 'example' => 'Artikel & Publikasi Ilmiah', 'note' => 'Diabaikan — ditentukan otomatis dari Subjenis Karya.'],
            ['key' => 'subjenis', 'label' => 'Subjenis Karya', 'required' => true, 'example' => 'Jurnal Nasional', 'note' => $types],
            ['key' => 'judul', 'label' => 'Judul Karya', 'required' => true, 'example' => 'Pembelajaran Matematika Berbasis Masalah', 'note' => ''],
            ['key' => 'tahun', 'label' => 'Tahun', 'required' => true, 'example' => (string) now()->year, 'note' => ''],
            ['key' => 'peran', 'label' => 'Peran Dosen', 'required' => false, 'example' => 'Penulis Pertama', 'note' => implode(', ', ScientificWork::AUTHOR_ROLES).'. Kosong = Lainnya.'],
            ['key' => 'jumlah_penulis', 'label' => 'Jumlah Penulis', 'required' => false, 'example' => '3', 'note' => ''],
            ['key' => 'media', 'label' => 'Nama Jurnal/Penerbit/Media', 'required' => false, 'example' => 'Jurnal Pendidikan Matematika', 'note' => 'Wajib untuk artikel/prosiding/buku.'],
            ['key' => 'volume', 'label' => 'Volume/No./Edisi', 'required' => false, 'example' => 'Vol. 5 No. 2', 'note' => ''],
            ['key' => 'issn', 'label' => 'ISSN/ISBN', 'required' => false, 'example' => '', 'note' => ''],
            ['key' => 'doi', 'label' => 'DOI', 'required' => false, 'example' => '', 'note' => '10.xxxx/… atau tautan doi.org.'],
            ['key' => 'indeksasi', 'label' => 'Indeksasi/Akreditasi', 'required' => false, 'example' => 'Sinta 4', 'note' => 'Wajib untuk jurnal & prosiding. '.implode(', ', array_map(fn (ScientificWorkIndexation $case): string => $case->label(), ScientificWorkIndexation::cases()))],
            ['key' => 'peringkat', 'label' => 'Peringkat/Kuartil', 'required' => false, 'example' => '', 'note' => 'Diabaikan — ditentukan otomatis dari Indeksasi/Akreditasi.'],
            ['key' => 'nomor_hki', 'label' => 'Nomor HKI', 'required' => false, 'example' => '', 'note' => ''],
            ['key' => 'status_hki', 'label' => 'Status HKI', 'required' => false, 'example' => '', 'note' => implode(', ', ScientificWork::IP_STATUSES).', Tidak Berlaku.'],
            ['key' => 'link_publikasi', 'label' => 'Link Publikasi', 'required' => false, 'example' => 'https://jurnal.contoh.ac.id/artikel/123', 'note' => 'Minimal satu dari Link Publikasi, DOI, atau Link Bukti (kecuali karya yang sudah dinilai LPM).'],
            ['key' => 'link_bukti', 'label' => 'Link Bukti', 'required' => false, 'example' => '', 'note' => 'Tautan folder/berkas bukti (Google Drive, dll.).'],
            ['key' => 'sumber_dana', 'label' => 'Sumber Dana', 'required' => false, 'example' => 'Mandiri', 'note' => implode(', ', ScientificWork::FUNDINGS).'. Kosong = Lainnya.'],
            ['key' => 'relevansi_prodi', 'label' => 'Relevansi dengan Prodi', 'required' => false, 'example' => 'Ya', 'note' => 'Ya atau Tidak.'],
            ['key' => 'relevansi_roadmap', 'label' => 'Relevansi Roadmap Penelitian', 'required' => false, 'example' => 'Ya', 'note' => 'Ya atau Tidak.'],
            ['key' => 'status', 'label' => 'Status Verifikasi LPM', 'required' => false, 'example' => 'Belum Diverifikasi', 'note' => implode(', ', array_map(fn (ScientificWorkStatus $status): string => $status->label(), ScientificWorkStatus::cases())).'. Kosong = Belum diverifikasi. Hanya dipakai bila pengimpor berhak memverifikasi (Admin LPM).'],
            ['key' => 'tanggal_verifikasi', 'label' => 'Tanggal Verifikasi', 'required' => false, 'example' => '', 'note' => ''],
            ['key' => 'verifikator', 'label' => 'Verifikator', 'required' => false, 'example' => '', 'note' => 'Dicatat pada catatan verifikasi.'],
            ['key' => 'catatan', 'label' => 'Catatan Verifikasi', 'required' => false, 'example' => '', 'note' => ''],
            ['key' => 'nuptk', 'label' => 'NUPTK', 'required' => false, 'example' => '', 'note' => 'Kolom tambahan: NUPTK dosen pemilik karya (16 digit).', 'text' => true],
            ['key' => 'dosen_rekan', 'label' => 'Dosen Rekan IAIA', 'required' => false, 'example' => '', 'note' => 'Kolom tambahan: dosen IAIA lain penulis bersama, dipisah titik koma. Tulis NIDN/NUPTK, atau "Nama (NIDN)".'],
            ['key' => 'mahasiswa', 'label' => 'Mahasiswa Terlibat', 'required' => false, 'example' => 'Siti Aminah (2511001); Budi', 'note' => 'Kolom tambahan: mahasiswa yang ikut menulis, dipisah titik koma. Tulis "Nama (NIM)", NIM saja, atau nama saja.'],
            ['key' => 'jumlah_mahasiswa', 'label' => 'Jumlah Mahasiswa', 'required' => false, 'example' => '', 'note' => 'Diabaikan — dihitung dari Mahasiswa Terlibat.'],
            ['key' => 'penulis_lain', 'label' => 'Penulis Lain (Luar IAIA)', 'required' => false, 'example' => '', 'note' => 'Kolom tambahan: penulis dari luar IAIA.'],
            ['key' => 'asal_kegiatan', 'label' => 'Asal Kegiatan', 'required' => false, 'example' => 'Hasil penelitian', 'note' => implode(', ', ScientificWork::SOURCES).'. Kosong = Hasil penelitian.'],
        ];
    }

    /**
     * Template LPM berisi 500 baris yang hanya bernomor urut — bukan baris data.
     */
    public function isBlankRow(array $row): bool
    {
        return parent::isBlankRow(Arr::except($row, ['no', 'jumlah_mahasiswa', 'jenis_karya', 'peringkat']));
    }

    public function normalize(array $row): array
    {
        foreach (['nidn', 'nuptk'] as $column) {
            if (is_string($row[$column] ?? null)) {
                $row[$column] = preg_replace('/\s+/', '', $row[$column]) ?: null;
            }
        }

        return $row;
    }

    public function key(array $row): ?string
    {
        $owner = $row['nuptk'] ?? $row['nidn'] ?? (isset($row['nama_dosen']) ? self::normalizedName((string) $row['nama_dosen']) : null);

        return $owner && ! empty($row['judul']) && ! empty($row['tahun'])
            ? implode('|', [$owner, ScientificWorks::normalizedTitle((string) $row['judul']), $row['tahun']])
            : null;
    }

    public function exists(string $key, ImportContext $context): bool
    {
        [$owner, $title, $year] = explode('|', $key, 3);
        $lecturer = ctype_digit($owner) ? $this->lecturerByNumber($owner) : $this->lecturerByName($owner);

        return $lecturer !== null && $this->existingWork($lecturer, $title, (int) $year) !== null;
    }

    public function validate(array $row, ImportContext $context): array
    {
        $errors = $this->requireColumns($row, ['subjenis', 'judul', 'tahun']);

        foreach (['nidn' => 'NIDN/NIDK', 'nuptk' => 'NUPTK'] as $column => $label) {
            if (! empty($row[$column]) && $error = $this->impreciseNumberError($row[$column], $label)) {
                $errors[$column] = $error;
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        try {
            $lecturer = $this->owner($row, $context);
            $this->attributes($row, $lecturer, $context);
        } catch (ImportRowException $exception) {
            return [$exception->column => $exception->getMessage()];
        } catch (ValidationException $exception) {
            return collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $field): array => [self::FIELD_COLUMNS[Str::before($field, '.')] ?? '_row' => $messages[0]])
                ->all();
        }

        return [];
    }

    public function persist(array $row, ImportContext $context, bool $updateExisting): string
    {
        $lecturer = $this->owner($row, $context);
        $data = $this->attributes($row, $lecturer, $context);
        $existing = $this->existingWork($lecturer, ScientificWorks::normalizedTitle((string) $row['judul']), (int) $data['year']);

        if ($existing && ! $updateExisting) {
            return 'skipped';
        }

        $work = $this->works->save($existing, [...$data, 'lecturer_id' => $lecturer->id], $context->user, notify: false);
        $work->forceFill($this->verification($row, $context))->save();

        return $existing ? 'updated' : 'created';
    }

    /**
     * Dosen pemilik karya: NUPTK, NIDN/NIDK, lalu nama persis. Harus dalam cakupan pengimpor.
     *
     * @param  array<string, mixed>  $row
     */
    private function owner(array $row, ImportContext $context): Lecturer
    {
        $lecturer = match (true) {
            ! empty($row['nuptk']) => $this->lecturerByNumber((string) $row['nuptk']),
            ! empty($row['nidn']) => $this->lecturerByNumber((string) $row['nidn']),
            ! empty($row['nama_dosen']) => $this->lecturerByName((string) $row['nama_dosen']),
            default => throw new ImportRowException('nidn', 'Isi NIDN/NIDK, NUPTK, atau Nama Dosen'),
        };

        if (! $lecturer) {
            $column = ! empty($row['nuptk']) ? 'nuptk' : (! empty($row['nidn']) ? 'nidn' : 'nama_dosen');

            throw new ImportRowException($column, $column === 'nama_dosen'
                ? 'Dosen dengan nama ini tidak ditemukan (atau ada lebih dari satu). Isi NIDN/NUPTK-nya.'
                : 'Dosen tidak ditemukan. Impor data dosen terlebih dahulu.');
        }

        if (! $context->user->canAccessStudyProgram($lecturer->study_program_id)) {
            throw new ImportRowException('nama_dosen', 'Dosen di luar cakupan akses Anda');
        }

        return $lecturer;
    }

    /**
     * Ubah isian template menjadi data karya yang tervalidasi aturan formulir.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     *
     * @throws ValidationException|ImportRowException
     */
    private function attributes(array $row, Lecturer $owner, ImportContext $context): array
    {
        $type = $this->option($row['subjenis'] ?? null, collect(ScientificWorkType::cases())->mapWithKeys(fn (ScientificWorkType $t): array => [$t->value => $t->label()])->all(), 'subjenis', 'Subjenis karya');
        $ipStatus = $this->option($row['status_hki'] ?? null, [...ScientificWork::IP_STATUSES, '' => 'Tidak Berlaku'], 'status_hki', 'Status HKI');

        $input = [
            'title' => isset($row['judul']) ? Str::squish((string) $row['judul']) : null,
            'type' => $type,
            'year' => $row['tahun'] ?? null,
            'venue' => $row['media'] ?? null,
            'indexation' => $this->option($row['indeksasi'] ?? null, collect(ScientificWorkIndexation::cases())->mapWithKeys(fn (ScientificWorkIndexation $i): array => [$i->value => $i->label()])->all(), 'indeksasi', 'Indeksasi/akreditasi'),
            'author_role' => $this->option($row['peran'] ?? null, ScientificWork::AUTHOR_ROLES, 'peran', 'Peran dosen') ?? 'other',
            'author_count' => $row['jumlah_penulis'] ?? null,
            'co_authors' => $row['penulis_lain'] ?? null,
            'source' => $this->option($row['asal_kegiatan'] ?? null, ScientificWork::SOURCES, 'asal_kegiatan', 'Asal kegiatan') ?? 'research',
            'funding' => $this->option($row['sumber_dana'] ?? null, ScientificWork::FUNDINGS, 'sumber_dana', 'Sumber dana') ?? 'other',
            'relevant_to_program' => $this->yesNo($row['relevansi_prodi'] ?? null, 'relevansi_prodi'),
            'relevant_to_roadmap' => $this->yesNo($row['relevansi_roadmap'] ?? null, 'relevansi_roadmap'),
            'identifier' => $row['issn'] ?? null,
            'doi' => ScientificWorks::normalizeDoi($row['doi'] ?? null),
            'volume' => $row['volume'] ?? null,
            'url' => $row['link_publikasi'] ?? null,
            'evidence_url' => $row['link_bukti'] ?? null,
            'ip_number' => $row['nomor_hki'] ?? null,
            'ip_status' => $ipStatus ?: (ScientificWorkType::tryFrom((string) $type)?->isIntellectualProperty() ? 'unknown' : null),
            'lecturers' => $this->coAuthors($row['dosen_rekan'] ?? null),
            'students' => $this->students($row['mahasiswa'] ?? null),
        ];

        $assessed = $this->status($row, $context) !== ScientificWorkStatus::Pending;

        return $this->works->validateData(array_map(fn ($value) => is_string($value) ? trim($value) : $value, $input), $owner->id, requireLink: ! $assessed);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function verification(array $row, ImportContext $context): array
    {
        $status = $this->status($row, $context);

        if ($status === ScientificWorkStatus::Pending) {
            return ['status' => $status, 'verified_by' => null, 'verified_at' => null, 'verification_note' => null];
        }

        $note = trim(implode(' ', array_filter([
            $row['catatan'] ?? null,
            ! empty($row['verifikator']) ? "(Verifikator: {$row['verifikator']})" : null,
        ])));

        try {
            $verifiedAt = ! empty($row['tanggal_verifikasi']) ? Carbon::parse((string) $row['tanggal_verifikasi']) : now();
        } catch (Throwable) {
            $verifiedAt = now();
        }

        return ['status' => $status, 'verified_by' => $context->user->id, 'verified_at' => $verifiedAt, 'verification_note' => $note ?: null];
    }

    /**
     * Status dari file hanya dipakai bila pengimpor berhak memverifikasi; selain itu karya menunggu verifikasi LPM.
     *
     * @param  array<string, mixed>  $row
     */
    private function status(array $row, ImportContext $context): ScientificWorkStatus
    {
        $value = $this->option($row['status'] ?? null, collect(ScientificWorkStatus::cases())->mapWithKeys(fn (ScientificWorkStatus $s): array => [$s->value => $s->label()])->all(), 'status', 'Status verifikasi');

        return $value && $context->user->can('research.verify') ? ScientificWorkStatus::from($value) : ScientificWorkStatus::Pending;
    }

    /**
     * Cocokkan isian dropdown template dengan pilihan (label atau kode, tanpa peka huruf besar/kecil).
     *
     * @param  array<string, string>  $labels
     */
    private function option(mixed $value, array $labels, string $column, string $name): ?string
    {
        $value = Str::squish((string) $value);

        if ($value === '') {
            return null;
        }

        foreach ($labels as $key => $label) {
            if (Str::lower($value) === Str::lower($label) || Str::lower($value) === Str::lower((string) $key)) {
                return (string) $key;
            }
        }

        throw new ImportRowException($column, "{$name} \"{$value}\" tidak dikenal");
    }

    private function yesNo(mixed $value, string $column): ?bool
    {
        return match (Str::lower(trim((string) $value))) {
            '' => null,
            'ya', 'y', 'yes', '1' => true,
            'tidak', 't', 'no', '0' => false,
            default => throw new ImportRowException($column, 'Isi Ya atau Tidak'),
        };
    }

    /**
     * "0812345678; Nama Dosen (7350…)" → id dosen.
     *
     * @return list<int>
     */
    private function coAuthors(mixed $value): array
    {
        return array_map(function (string $entry): int {
            $number = preg_match('/\((\d{8,16})\)\s*$/', $entry, $match) ? $match[1] : (ctype_digit($entry) ? $entry : null);
            $lecturer = $number ? $this->lecturerByNumber($number) : $this->lecturerByName($entry);

            return $lecturer?->id ?? throw new ImportRowException('dosen_rekan', "Dosen rekan \"{$entry}\" tidak ditemukan");
        }, self::entries($value));
    }

    /**
     * "Nama (NIM); NIM; Nama" → mahasiswa dari master data bila ditemukan, selain itu dicatat dengan nama saja.
     *
     * @return list<array{student_id: ?int, name: ?string, nim: ?string}>
     */
    private function students(mixed $value): array
    {
        return array_map(function (string $entry): array {
            [$name, $nim] = match (true) {
                (bool) preg_match('/^(.*)\((\w+)\)\s*$/u', $entry, $match) => [trim($match[1]), $match[2]],
                ctype_digit($entry) => [null, $entry],
                default => [$entry, null],
            };

            $matches = $nim
                ? Student::query()->where('nim', $nim)->limit(1)->get()
                : Student::query()->where('name', $name)->limit(2)->get();
            $student = $matches->count() === 1 ? $matches->first() : null;

            if (! $student && ! $name) {
                throw new ImportRowException('mahasiswa', "Mahasiswa dengan NIM {$nim} tidak ditemukan; tulis sebagai \"Nama ({$nim})\"");
            }

            return ['student_id' => $student?->id, 'name' => $student?->name ?? $name, 'nim' => $student?->nim ?? $nim];
        }, self::entries($value));
    }

    /**
     * @return list<string>
     */
    private static function entries(mixed $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;\n]+/', (string) $value) ?: [])));
    }

    private function existingWork(Lecturer $lecturer, string $normalizedTitle, int $year): ?ScientificWork
    {
        return ScientificWork::query()->where('lecturer_id', $lecturer->id)->where('year', $year)->get()
            ->first(fn (ScientificWork $work): bool => ScientificWorks::normalizedTitle($work->title) === $normalizedTitle);
    }

    private function lecturerByNumber(string $number): ?Lecturer
    {
        return $this->lecturers()->first(fn (Lecturer $lecturer): bool => $lecturer->nidn === $number || $lecturer->nuptk === $number);
    }

    /**
     * Nama persis (dengan atau tanpa gelar), hanya bila tidak ada dosen lain bernama sama.
     */
    private function lecturerByName(string $name): ?Lecturer
    {
        $name = self::normalizedName($name);
        $matches = $this->lecturers()->filter(fn (Lecturer $lecturer): bool => in_array($name, [self::normalizedName($lecturer->name), self::normalizedName($lecturer->full_name)], true));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @return Collection<int, Lecturer>
     */
    private function lecturers(): Collection
    {
        return $this->lecturers ??= Lecturer::query()->get(['id', 'study_program_id', 'nidn', 'nuptk', 'name', 'front_title', 'back_title']);
    }

    private static function normalizedName(string $name): string
    {
        return (string) Str::of($name)->lower()->replaceMatches('/[^\pL\pN]+/u', ' ')->squish();
    }
}
