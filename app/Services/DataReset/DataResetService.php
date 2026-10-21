<?php

namespace App\Services\DataReset;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pengosongan data per fitur (khusus Superadmin).
 *
 * Akibat pengosongan dihitung langsung dari relasi database (foreign key) ditambah relasi polimorfik aplikasi:
 * - CASCADE/RESTRICT → baris terkait ikut dihapus (anak dihapus lebih dulu agar RESTRICT tidak menghalangi);
 * - SET NULL → baris terkait tetap ada, hanya tautannya yang dilepas.
 */
class DataResetService
{
    /**
     * Kolom berkas di disk `local` yang ikut dihapus bersama barisnya.
     */
    private const FILE_COLUMNS = ['evidence_versions' => 'file_path', 'import_jobs' => 'file_path'];

    private const CHUNK = 1000;

    private const TABLE_LABELS = [
        'academic_periods' => 'Periode akademik',
        'accreditation_assessments' => 'Penilaian akreditasi',
        'accreditation_bodies' => 'Lembaga akreditasi',
        'accreditation_criteria' => 'Kriteria akreditasi',
        'accreditation_indicators' => 'Indikator akreditasi',
        'accreditation_instrument_versions' => 'Versi instrumen akreditasi',
        'accreditation_instruments' => 'Instrumen akreditasi',
        'accreditation_periods' => 'Periode akreditasi',
        'action_plans' => 'Rencana aksi',
        'action_tasks' => 'Tugas rencana aksi',
        'answer_scales' => 'Skala jawaban',
        'audit_answers' => 'Jawaban audit',
        'audit_auditor' => 'Penugasan auditor',
        'audit_logs' => 'Log audit',
        'audit_programs' => 'Program AMI',
        'auditors' => 'Auditor',
        'audits' => 'Audit',
        'class_enrollments' => 'Peserta kelas',
        'classification_schemes' => 'Skema klasifikasi',
        'corrective_actions' => 'Tindakan perbaikan',
        'course_classes' => 'Kelas',
        'courses' => 'Mata kuliah',
        'evidence' => 'Dokumen bukti',
        'evidence_categories' => 'Kategori dokumen',
        'evidence_mappings' => 'Tautan dokumen bukti',
        'evidence_versions' => 'Versi berkas dokumen',
        'faculties' => 'Fakultas',
        'finding_severities' => 'Tingkat temuan',
        'findings' => 'Temuan',
        'import_errors' => 'Baris galat impor',
        'import_jobs' => 'Riwayat impor',
        'institutions' => 'Profil lembaga',
        'instrument_question_options' => 'Opsi jawaban instrumen',
        'instrument_questions' => 'Butir instrumen',
        'instrument_sections' => 'Bagian instrumen',
        'instrument_versions' => 'Versi instrumen',
        'instruments' => 'Instrumen',
        'lecturers' => 'Dosen',
        'model_has_permissions' => 'Hak akses khusus pengguna',
        'model_has_roles' => 'Peran pengguna',
        'notifications' => 'Notifikasi',
        'quality_standards' => 'Standar mutu',
        'recommendations' => 'Rekomendasi',
        'response_answers' => 'Jawaban Monev',
        'responses' => 'Respons Monev',
        'root_cause_categories' => 'Kategori akar masalah',
        'scientific_work_members' => 'Anggota karya ilmiah',
        'scientific_works' => 'Karya ilmiah',
        'score_classifications' => 'Klasifikasi skor',
        'sessions' => 'Sesi login',
        'students' => 'Mahasiswa',
        'study_program_survey' => 'Cakupan prodi kegiatan Monev',
        'study_programs' => 'Program studi',
        'survey_participations' => 'Status pengisian Monev',
        'surveys' => 'Kegiatan Monev',
        'teaching_assignments' => 'Penugasan mengajar',
        'units' => 'Unit kerja',
        'users' => 'Akun pengguna',
        'verifications' => 'Riwayat verifikasi',
    ];

    /**
     * @var array<string, list<array<string, mixed>>>|null
     */
    private ?array $relations = null;

    /**
     * @var array<string, true>
     */
    private array $tablesWithId = [];

    /**
     * @return array<string, Dataset>
     */
    public function datasets(): array
    {
        $accountNote = 'Akun yang juga memiliki peran lain (mis. Kaprodi atau Auditor) tetap dipertahankan; hanya tautannya yang dilepas.';

        $datasets = [
            new Dataset('faculties', 'Fakultas', 'Seluruh data fakultas.', ['faculties' => null]),
            new Dataset('study-programs', 'Program studi', 'Seluruh program studi.', ['study_programs' => null]),
            new Dataset('units', 'Unit kerja', 'Seluruh unit kerja.', ['units' => null]),
            new Dataset('academic-periods', 'Periode akademik', 'Seluruh periode akademik (semester).', ['academic_periods' => null]),
            new Dataset('accreditation-bodies', 'Lembaga akreditasi', 'Seluruh lembaga akreditasi.', ['accreditation_bodies' => null]),
            new Dataset('courses', 'Mata kuliah', 'Seluruh mata kuliah.', ['courses' => null]),
            new Dataset('classes', 'Kelas', 'Seluruh kelas beserta peserta dan dosen pengampunya.', ['course_classes' => null]),
            new Dataset('students', 'Mahasiswa', 'Seluruh data mahasiswa beserta akun login mereka.', [
                'students' => null,
                'users' => fn (Builder $query) => $query
                    ->whereIn('id', DB::table('students')->whereNotNull('user_id')->select('user_id'))
                    ->whereNotIn('id', $this->usersWithOtherRoles(UserRole::Mahasiswa)),
            ], [$accountNote]),
            new Dataset('lecturers', 'Dosen', 'Seluruh data dosen beserta akun login mereka.', [
                'lecturers' => null,
                'users' => fn (Builder $query) => $query
                    ->whereIn('id', DB::table('lecturers')->whereNotNull('user_id')->select('user_id'))
                    ->whereNotIn('id', $this->usersWithOtherRoles(UserRole::Dosen)),
            ], [$accountNote]),
            new Dataset('users', 'Pengguna', 'Seluruh akun pengguna kecuali Superadmin.', [
                'users' => fn (Builder $query) => $query->whereNotIn('id', $this->superadmins()),
            ], [
                'Akun Superadmin tidak ikut dihapus.',
                'Data dosen dan mahasiswa tetap ada, tetapi tidak lagi memiliki akun login. Buatkan kembali lewat menu Dosen/Mahasiswa atau Impor Data.',
            ]),
            new Dataset('instruments', 'Instrumen', 'Seluruh instrumen Monev dan AMI beserta semua versinya.', ['instruments' => null]),
            new Dataset('surveys', 'Kegiatan Monev', 'Seluruh kegiatan Monev dan survei beserta isiannya.', ['surveys' => null]),
            new Dataset('survey-responses', 'Isian Monev', 'Seluruh jawaban responden dan status pengisian. Kegiatan Monev-nya tetap ada.', [
                'responses' => null,
                'survey_participations' => null,
            ], ['Kegiatan yang masih dibuka dapat diisi ulang oleh semua responden.']),
            new Dataset('audit-programs', 'Program AMI', 'Seluruh program AMI beserta audit di dalamnya.', ['audit_programs' => null]),
            new Dataset('audits', 'Audit', 'Seluruh audit beserta jawaban dan penugasan auditornya.', ['audits' => null]),
            new Dataset('auditors', 'Auditor', 'Seluruh daftar auditor. Akun penggunanya tetap ada.', ['auditors' => null]),
            new Dataset('findings', 'Temuan', 'Seluruh temuan AMI beserta tindakan perbaikannya.', ['findings' => null]),
            new Dataset('quality-standards', 'Standar mutu', 'Seluruh standar mutu.', ['quality_standards' => null]),
            new Dataset('finding-references', 'Referensi temuan', 'Seluruh tingkat temuan dan kategori akar masalah.', [
                'finding_severities' => null,
                'root_cause_categories' => null,
            ]),
            new Dataset('evidence', 'Dokumen bukti', 'Seluruh dokumen bukti beserta berkasnya di penyimpanan.', ['evidence' => null]),
            new Dataset('evidence-categories', 'Kategori dokumen', 'Seluruh kategori dokumen bukti.', ['evidence_categories' => null]),
            new Dataset('recommendations', 'Rekomendasi', 'Seluruh rekomendasi beserta rencana aksinya.', ['recommendations' => null]),
            new Dataset('action-plans', 'Rencana aksi', 'Seluruh rencana aksi beserta tugasnya. Rekomendasinya tetap ada.', ['action_plans' => null]),
            new Dataset('accreditation-instruments', 'Instrumen akreditasi', 'Seluruh instrumen akreditasi beserta kriteria dan indikatornya.', ['accreditation_instruments' => null]),
            new Dataset('accreditation-periods', 'Periode akreditasi', 'Seluruh periode akreditasi beserta penilaiannya.', ['accreditation_periods' => null]),
            new Dataset('scientific-works', 'Karya ilmiah', 'Seluruh karya ilmiah dosen beserta anggotanya.', ['scientific_works' => null]),
            new Dataset('imports', 'Riwayat impor', 'Seluruh riwayat impor beserta berkas unggahannya. Data hasil impor tetap ada.', ['import_jobs' => null]),
            new Dataset('notifications', 'Notifikasi', 'Notifikasi seluruh pengguna, bukan hanya milik Anda.', ['notifications' => null]),
            new Dataset('audit-logs', 'Log audit', 'Seluruh catatan log audit.', ['audit_logs' => null], [
                'Satu catatan baru tentang pengosongan ini tetap ditambahkan sebagai jejak.',
            ]),
        ];

        return collect($datasets)->keyBy('key')->all();
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return array_map(fn (Dataset $dataset): string => $dataset->label, $this->datasets());
    }

    public function find(string $key): ?Dataset
    {
        return $this->datasets()[$key] ?? null;
    }

    /**
     * Ringkasan akibat pengosongan, tanpa mengubah data.
     *
     * @return array<string, mixed>
     */
    public function preview(Dataset $dataset): array
    {
        return $this->summarize($dataset, $this->plan($dataset));
    }

    /**
     * Kosongkan data beserta seluruh akibatnya dalam satu transaksi.
     *
     * @return array<string, mixed>
     */
    public function empty(Dataset $dataset): array
    {
        $plan = $this->plan($dataset);
        $summary = $this->summarize($dataset, $plan);
        $files = $this->files($plan);

        DB::transaction(function () use ($plan): void {
            // Tautan polimorfik tidak punya foreign key, jadi dilepas manual.
            foreach ($plan['nulls'] as $entry) {
                foreach ($entry['manual'] as [$relation, $ids]) {
                    foreach (array_chunk(array_diff($ids, array_keys($plan['ids'][$entry['table']] ?? [])), self::CHUNK) as $chunk) {
                        DB::table($entry['table'])->whereIn('id', $chunk)->update(array_fill_keys($relation['null_columns'], null));
                    }
                }
            }

            foreach ($this->deletionOrder($plan) as $table) {
                foreach ($plan['pivots'][$table] ?? [] as $condition) {
                    $condition(DB::table($table))->delete();
                }

                foreach (array_chunk(array_keys($plan['ids'][$table] ?? []), self::CHUNK) as $chunk) {
                    DB::table($table)->whereIn('id', $chunk)->delete();
                }
            }
        });

        Storage::disk('local')->delete($files);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forget('app.branding');

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{key: string, label: string, description: string, phrase: string, notes: list<string>, deleted: list<array{table: string, label: string, count: int, primary: bool}>, detached: list<array{label: string, parent: string, count: int}>, files: int, total: int}
     */
    private function summarize(Dataset $dataset, array $plan): array
    {
        $tables = array_unique([...array_keys($dataset->roots), ...array_keys($plan['ids']), ...array_keys($plan['pivots'])]);

        $deleted = collect($tables)
            ->map(fn (string $table): array => [
                'table' => $table,
                'label' => $this->label($table),
                'count' => isset($plan['pivots'][$table])
                    ? DB::table($table)->where(function (Builder $query) use ($plan, $table): void {
                        foreach ($plan['pivots'][$table] as $condition) {
                            $query->orWhere(fn (Builder $branch) => $condition($branch));
                        }
                    })->count()
                    : count($plan['ids'][$table] ?? []),
                'primary' => array_key_exists($table, $dataset->roots),
            ])
            ->filter(fn (array $row): bool => $row['primary'] || $row['count'] > 0)
            ->sortBy([['primary', 'desc'], ['count', 'desc']])
            ->values();

        $detached = collect($plan['nulls'])
            ->map(fn (array $entry): array => [
                'label' => $this->label($entry['table']),
                'parent' => $this->label($entry['parent']),
                'count' => count($this->detachedIds($plan, $entry)),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortByDesc('count')
            ->values();

        $notes = $dataset->notes;
        $orphans = $this->orphanedAccounts($plan);
        if ($orphans > 0) {
            $notes[] = sprintf('%s akun login milik dosen/mahasiswa yang terhapus tidak ikut dihapus. Hapus lewat menu Pengguna bila tidak dipakai lagi.', number_format($orphans, 0, ',', '.'));
        }

        return [
            'key' => $dataset->key,
            'label' => $dataset->label,
            'description' => $dataset->description,
            'phrase' => $dataset->phrase(),
            'notes' => $notes,
            'deleted' => $deleted->all(),
            'detached' => $detached->all(),
            'files' => count($this->files($plan)),
            'total' => $deleted->sum('count'),
        ];
    }

    /**
     * Telusuri seluruh baris terdampak secara melebar, mulai dari tabel utama dataset.
     * Setiap baris hanya ditelusuri sekali, sehingga relasi bercabang tidak dihitung berulang.
     *
     * @return array{ids: array<string, array<int|string, true>>, pivots: array<string, list<Closure>>, nulls: array<string, array<string, mixed>>, edges: array<string, array<string, true>>}
     */
    private function plan(Dataset $dataset): array
    {
        $plan = ['ids' => [], 'pivots' => [], 'nulls' => [], 'edges' => []];
        $queue = [];

        $schedule = function (string $table, array $ids) use (&$plan, &$queue): void {
            $new = array_values(array_filter($ids, fn ($id): bool => ! isset($plan['ids'][$table][$id])));
            foreach ($new as $id) {
                $plan['ids'][$table][$id] = true;
            }
            $plan['ids'][$table] ??= [];
            if ($new !== []) {
                $queue[] = [$table, $new];
            }
        };

        foreach ($dataset->roots as $table => $scope) {
            $schedule($table, DB::table($table)->when($scope, fn (Builder $query) => $query->where($scope))->pluck('id')->all());
        }

        while ($queue !== []) {
            [$table, $ids] = array_shift($queue);
            foreach ($this->relations()[$table] ?? [] as $relation) {
                $child = $relation['table'];
                $morph = fn (Builder $query): Builder => $relation['morph_type'] === null ? $query : $query->where($relation['morph_column'], $relation['morph_type']);

                foreach (array_chunk($ids, self::CHUNK) as $chunk) {
                    if (! $this->hasId($child)) {
                        $plan['pivots'][$child][] = fn (Builder $query): Builder => $morph($query)->whereIn($relation['column'], $chunk);
                        $plan['edges'][$table][$child] = true;

                        continue;
                    }

                    $childIds = $morph(DB::table($child))->whereIn($relation['column'], $chunk)->pluck('id')->all();
                    if ($childIds === []) {
                        continue;
                    }

                    if ($relation['rule'] === 'delete') {
                        if ($child !== $table) {
                            $plan['edges'][$table][$child] = true;
                        }
                        $schedule($child, $childIds);

                        continue;
                    }

                    // Beberapa kolom ke induk yang sama (mis. pembuat & PIC) digabung agar barisnya tidak terhitung dua kali.
                    $key = "{$child}.{$table}";
                    $plan['nulls'][$key] ??= ['table' => $child, 'parent' => $table, 'ids' => [], 'manual' => []];
                    foreach ($childIds as $id) {
                        $plan['nulls'][$key]['ids'][$id] = true;
                    }
                    if ($relation['automatic'] === false) {
                        $plan['nulls'][$key]['manual'][] = [$relation, $childIds];
                    }
                }
            }
        }

        return $plan;
    }

    /**
     * Baris yang tautannya dilepas, tidak termasuk yang toh ikut dihapus.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $entry
     * @return list<int|string>
     */
    private function detachedIds(array $plan, array $entry): array
    {
        return array_keys(array_diff_key($entry['ids'], $plan['ids'][$entry['table']] ?? []));
    }

    /**
     * Akun login dosen/mahasiswa yang datanya terhapus tetapi akunnya tetap ada.
     *
     * @param  array<string, mixed>  $plan
     */
    private function orphanedAccounts(array $plan): int
    {
        $userIds = collect(['students', 'lecturers'])
            ->flatMap(fn (string $table) => collect(array_chunk(array_keys($plan['ids'][$table] ?? []), self::CHUNK))
                ->flatMap(fn (array $chunk) => DB::table($table)->whereIn('id', $chunk)->whereNotNull('user_id')->pluck('user_id')))
            ->unique();

        return $userIds->reject(fn (int $id): bool => isset($plan['ids']['users'][$id]))->count();
    }

    /**
     * Urutan hapus: anak lebih dulu, induk terakhir, agar RESTRICT tidak menghalangi.
     *
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    private function deletionOrder(array $plan): array
    {
        $order = [];
        $visited = [];

        $visit = function (string $table) use (&$visit, &$order, &$visited, $plan): void {
            if (isset($visited[$table])) {
                return;
            }

            $visited[$table] = true;
            foreach (array_keys($plan['edges'][$table] ?? []) as $child) {
                $visit($child);
            }
            $order[] = $table;
        };

        foreach ([...array_keys($plan['ids']), ...array_keys($plan['pivots'])] as $table) {
            $visit($table);
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    private function files(array $plan): array
    {
        return collect(self::FILE_COLUMNS)
            ->flatMap(fn (string $column, string $table) => collect(array_chunk(array_keys($plan['ids'][$table] ?? []), self::CHUNK))
                ->flatMap(fn (array $chunk) => DB::table($table)->whereIn('id', $chunk)->whereNotNull($column)->pluck($column)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function hasId(string $table): bool
    {
        $this->relations();

        return isset($this->tablesWithId[$table]);
    }

    /**
     * Relasi anak per tabel induk: foreign key database ditambah relasi polimorfik aplikasi.
     *
     * @return array<string, list<array{table: string, column: string, references: string, rule: string, automatic: bool, null_columns: list<string>, morph_column: string|null, morph_type: string|null}>>
     */
    private function relations(): array
    {
        if ($this->relations !== null) {
            return $this->relations;
        }

        $relations = [];
        $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE as k')
            ->join('information_schema.REFERENTIAL_CONSTRAINTS as r', function ($join): void {
                $join->on('r.CONSTRAINT_NAME', '=', 'k.CONSTRAINT_NAME')->on('r.CONSTRAINT_SCHEMA', '=', 'k.CONSTRAINT_SCHEMA');
            })
            ->where('k.TABLE_SCHEMA', DB::getDatabaseName())
            ->whereNotNull('k.REFERENCED_TABLE_NAME')
            ->get(['k.TABLE_NAME as child', 'k.COLUMN_NAME as column', 'k.REFERENCED_TABLE_NAME as parent', 'k.REFERENCED_COLUMN_NAME as references', 'r.DELETE_RULE as rule']);

        $this->tablesWithId = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())->where('COLUMN_NAME', 'id')->where('COLUMN_KEY', 'PRI')
            ->pluck('TABLE_NAME')->flip()->map(fn (): bool => true)->all();

        foreach ($foreignKeys as $key) {
            $relations[$key->parent][] = [
                'table' => $key->child,
                'column' => $key->column,
                'references' => $key->references,
                'rule' => $key->rule === 'SET NULL' ? 'null' : 'delete',
                'automatic' => true,
                'null_columns' => [$key->column],
                'morph_column' => null,
                'morph_type' => null,
            ];
        }

        $morphTables = collect(Relation::morphMap())
            ->mapWithKeys(fn (string $class, string $alias): array => [(new $class)->getTable() => $alias])
            ->put('users', (new User)->getMorphClass());

        $morphs = [
            ['evidence_mappings', 'mappable', 'delete'],
            ['verifications', 'verifiable', 'delete'],
            ['notifications', 'notifiable', 'delete'],
            ['model_has_roles', 'model', 'delete'],
            ['model_has_permissions', 'model', 'delete'],
            ['recommendations', 'source', 'null'],
        ];

        foreach ($morphTables as $parent => $type) {
            foreach ($morphs as [$child, $name, $rule]) {
                $relations[$parent][] = [
                    'table' => $child,
                    'column' => "{$name}_id",
                    'references' => 'id',
                    'rule' => $rule,
                    'automatic' => false,
                    'null_columns' => ["{$name}_type", "{$name}_id"],
                    'morph_column' => "{$name}_type",
                    'morph_type' => $type,
                ];
            }
        }

        $relations['users'][] = [
            'table' => 'sessions', 'column' => 'user_id', 'references' => 'id', 'rule' => 'delete', 'automatic' => false,
            'null_columns' => ['user_id'], 'morph_column' => null, 'morph_type' => null,
        ];

        return $this->relations = $relations;
    }

    private function label(string $table): string
    {
        return self::TABLE_LABELS[$table] ?? str_replace('_', ' ', ucfirst($table));
    }

    private function superadmins(): Builder
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('roles.name', UserRole::Superadmin->value)
            ->select('model_has_roles.model_id');
    }

    /**
     * Pengguna yang memiliki peran selain `$role`; akunnya tidak ikut dihapus.
     */
    private function usersWithOtherRoles(UserRole $role): Builder
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('roles.name', '!=', $role->value)
            ->select('model_has_roles.model_id');
    }
}
