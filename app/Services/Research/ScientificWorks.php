<?php

namespace App\Services\Research;

use App\Enums\ScientificWorkCategory;
use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\ScientificWorkMember;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ScientificWorkTaggedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi, penyimpanan, dan bentuk data karya ilmiah yang dipakai halaman pengelola, dosen, dan impor.
 */
class ScientificWorks
{
    private const LINK = '/^https?:\/\/\S+$/i';

    private const DOI = '/^10\.\d{4,9}\/\S+$/';

    /**
     * Validasi isian formulir. `lecturer_id` (pemilik) divalidasi terpisah oleh pemanggil.
     *
     * @return array<string, mixed> atribut karya beserta `lecturers` (id dosen rekan) dan `students` (mahasiswa terlibat)
     */
    public function validate(Request $request, int $ownerId): array
    {
        $request->merge(['doi' => self::normalizeDoi($request->input('doi'))]);

        return $this->validateData($request->all(), $ownerId);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  bool  $requireLink  impor data lama yang sudah dinilai LPM boleh tanpa tautan
     * @return array<string, mixed>
     */
    public function validateData(array $input, int $ownerId, bool $requireLink = true): array
    {
        $type = ScientificWorkType::tryFrom((string) ($input['type'] ?? ''));

        $validator = validator($input, [
            'title' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::enum(ScientificWorkType::class)],
            'year' => ['required', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'venue' => [$type && $type->category() !== ScientificWorkCategory::Other ? 'required' : 'nullable', 'string', 'max:255'],
            'indexation' => $type?->usesIndexation() ? ['required', Rule::enum(ScientificWorkIndexation::class)] : ['nullable'],
            'author_role' => ['required', Rule::in(array_keys(ScientificWork::AUTHOR_ROLES))],
            'author_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'co_authors' => ['nullable', 'string', 'max:2000'],
            'source' => ['required', Rule::in(array_keys(ScientificWork::SOURCES))],
            'funding' => ['required', Rule::in(array_keys(ScientificWork::FUNDINGS))],
            'relevant_to_program' => ['nullable', 'boolean'],
            'relevant_to_roadmap' => ['nullable', 'boolean'],
            'identifier' => ['nullable', 'string', 'max:100'],
            'doi' => ['nullable', 'string', 'max:150', 'regex:'.self::DOI],
            'volume' => ['nullable', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:1000', 'regex:'.self::LINK],
            'evidence_url' => ['nullable', ...($requireLink ? ['required_without_all:url,doi'] : []), 'string', 'max:1000', 'regex:'.self::LINK],
            'ip_number' => ['nullable', 'string', 'max:100'],
            'ip_status' => $type?->isIntellectualProperty() ? ['required', Rule::in(array_keys(ScientificWork::IP_STATUSES))] : ['nullable'],
            'lecturers' => ['nullable', 'array', 'max:'.ScientificWork::MAX_MEMBERS],
            'lecturers.*' => ['integer', 'distinct', Rule::exists('lecturers', 'id'), Rule::notIn([$ownerId])],
            'students' => ['nullable', 'array', 'max:'.ScientificWork::MAX_MEMBERS],
            'students.*.student_id' => ['nullable', 'integer', 'distinct', Rule::exists('students', 'id')],
            'students.*.name' => ['required_without:students.*.student_id', 'nullable', 'string', 'max:255'],
            'students.*.nim' => ['nullable', 'string', 'max:20'],
        ], [
            'indexation.required' => 'Pilih indeksasi/akreditasi jurnal atau prosiding.',
            'venue.required' => 'Isi nama jurnal, penerbit, atau media.',
            'ip_status.required' => 'Pilih status HKI.',
            'url.regex' => 'Isi tautan lengkap yang diawali https://',
            'evidence_url.regex' => 'Isi tautan lengkap yang diawali https://',
            'evidence_url.required_without_all' => 'Isi minimal satu tautan: Link Publikasi, DOI, atau Link Bukti.',
            'doi.regex' => 'DOI diawali 10.xxxx/ (mis. 10.31004/abc.v5i2.123).',
            'lecturers.*.not_in' => 'Pemilik karya tidak perlu ditandai sebagai dosen rekan.',
            'lecturers.*.distinct' => 'Dosen rekan ditandai lebih dari sekali.',
            'students.*.distinct' => 'Mahasiswa ditandai lebih dari sekali.',
            'students.*.name.required_without' => 'Isi nama mahasiswa.',
            'lecturers.max' => 'Maksimal '.ScientificWork::MAX_MEMBERS.' dosen rekan.',
            'students.max' => 'Maksimal '.ScientificWork::MAX_MEMBERS.' mahasiswa.',
        ], [
            'author_count' => 'jumlah penulis',
            'evidence_url' => 'link bukti',
            'url' => 'link publikasi',
        ]);

        $validator->after(function (Validator $validator) use ($input): void {
            $members = count($input['lecturers'] ?? []) + count($input['students'] ?? []);

            if (($input['author_role'] ?? null) === 'single' && $members > 0) {
                $validator->errors()->add('author_role', 'Penulis tunggal tidak dapat menandai dosen rekan atau mahasiswa.');
            }
            if (filled($input['author_count'] ?? null) && (int) $input['author_count'] < $members + 1) {
                $validator->errors()->add('author_count', 'Jumlah penulis minimal '.($members + 1).' (Anda + penulis yang ditandai).');
            }
        });

        $validated = $validator->validate();

        // Kolom yang tidak berlaku untuk subjenis ini dibuang agar tidak tersisa dari pilihan sebelumnya.
        $validated['indexation'] = $type->usesIndexation() ? $validated['indexation'] : ScientificWorkIndexation::NotApplicable->value;
        if (! $type->isIntellectualProperty()) {
            $validated['ip_number'] = null;
            $validated['ip_status'] = null;
        }

        $validated['lecturers'] = array_values(array_map('intval', $validated['lecturers'] ?? []));
        $validated['students'] = array_values(array_map(fn (array $student): array => [
            'student_id' => isset($student['student_id']) ? (int) $student['student_id'] : null,
            'name' => isset($student['name']) ? trim($student['name']) : null,
            'nim' => isset($student['nim']) ? trim($student['nim']) ?: null : null,
        ], $validated['students'] ?? []));

        return $validated;
    }

    /**
     * Simpan karya baru atau perubahan beserta penulis yang ditandai. Perubahan oleh non-verifikator
     * mengembalikan status ke "belum diverifikasi". Dosen rekan yang baru ditandai diberi notifikasi.
     *
     * @param  array<string, mixed>  $data  hasil validate()/validateData() ditambah `lecturer_id`
     */
    public function save(?ScientificWork $work, array $data, User $user, bool $notify = true): ScientificWork
    {
        $lecturerIds = Arr::pull($data, 'lecturers', []);
        $students = Arr::pull($data, 'students', []);

        [$work, $added] = DB::transaction(function () use ($work, $data, $user, $lecturerIds, $students): array {
            if (! $work) {
                $work = ScientificWork::query()->create([...$data, 'status' => ScientificWorkStatus::Pending, 'created_by' => $user->id]);

                return [$work, $this->syncMembers($work, $lecturerIds, $students)['added']];
            }

            $work->fill($data);
            $members = $this->syncMembers($work, $lecturerIds, $students);

            if (($work->isDirty() || $members['changed']) && ! $user->can('research.verify')) {
                $work->fill(['status' => ScientificWorkStatus::Pending, 'verified_by' => null, 'verified_at' => null]);
            }

            $work->save();

            return [$work, $members['added']];
        });

        if ($notify) {
            $this->notifyTagged($work, $added, $user);
        }

        return $work;
    }

    /**
     * Ganti daftar dosen rekan & mahasiswa. Nama mahasiswa dari master data disalin agar tetap tampil
     * meski data mahasiswanya kelak dihapus.
     *
     * @param  list<int>  $lecturerIds
     * @param  list<array{student_id: ?int, name: ?string, nim: ?string}>  $students
     * @return array{changed: bool, added: list<int>} `added` = id dosen rekan yang baru ditandai
     */
    public function syncMembers(ScientificWork $work, array $lecturerIds, array $students): array
    {
        $lecturers = Lecturer::query()->whereKey($lecturerIds)->get()->keyBy('id');
        $masters = Student::query()->whereKey(array_filter(array_column($students, 'student_id')))->get()->keyBy('id');

        $rows = collect($lecturerIds)
            ->filter(fn (int $id): bool => $lecturers->has($id))
            ->values()
            ->map(fn (int $id, int $position): array => [
                'kind' => ScientificWorkMember::LECTURER,
                'lecturer_id' => $id,
                'student_id' => null,
                'name' => $lecturers[$id]->full_name,
                'nim' => null,
                'position' => $position,
            ])
            ->concat(collect($students)->values()->map(function (array $student, int $position) use ($masters): array {
                $master = $student['student_id'] ? $masters->get($student['student_id']) : null;

                return [
                    'kind' => ScientificWorkMember::STUDENT,
                    'lecturer_id' => null,
                    'student_id' => $master?->id,
                    'name' => $master?->name ?? (string) $student['name'],
                    'nim' => $master?->nim ?? $student['nim'],
                    'position' => $position,
                ];
            }));

        $existing = $work->members()->get();
        $signature = fn (Collection $members): array => $members
            ->map(fn ($member): string => implode('|', [$member['kind'], $member['lecturer_id'], $member['student_id'], $member['name'], $member['nim']]))
            ->sort()->values()->all();

        if ($signature($existing->map->only(['kind', 'lecturer_id', 'student_id', 'name', 'nim'])) === $signature($rows)) {
            // Isi sama (urutan diabaikan) — tidak perlu ditulis ulang.
            return ['changed' => false, 'added' => []];
        }

        $before = $existing->where('kind', ScientificWorkMember::LECTURER)->pluck('lecturer_id')->all();
        $work->members()->delete();
        $work->members()->createMany($rows->all());

        return [
            'changed' => true,
            'added' => array_values(array_diff($rows->where('kind', ScientificWorkMember::LECTURER)->pluck('lecturer_id')->all(), $before)),
        ];
    }

    /**
     * @param  list<int>  $lecturerIds
     */
    private function notifyTagged(ScientificWork $work, array $lecturerIds, User $actor): void
    {
        if ($lecturerIds === []) {
            return;
        }

        $taggedBy = $work->loadMissing('lecturer')->lecturer?->full_name ?? $actor->name;

        Lecturer::query()->whereKey($lecturerIds)->with('user')->get()
            ->pluck('user')->filter()
            ->reject(fn (User $user): bool => $user->is($actor))
            ->each(fn (User $user) => $user->notify(new ScientificWorkTaggedNotification($work, $taggedBy)));
    }

    /**
     * Karya lain dengan DOI sama, atau judul (tanpa tanda baca/huruf besar) dan tahun sama — bahan cek duplikat bagi verifikator.
     *
     * @return list<array{id: int, title: string, lecturer: ?string, status_label: string}>
     */
    public function possibleDuplicates(ScientificWork $work, User $user): array
    {
        $title = self::normalizedTitle($work->title);

        return ScientificWork::query()
            ->visibleTo($user)
            ->whereKeyNot($work->id)
            ->where(fn (Builder $query) => $query
                ->when($work->doi, fn (Builder $q) => $q->orWhere('doi', $work->doi))
                ->orWhere(fn (Builder $q) => $q->where('year', $work->year)->where('title', 'like', '%'.Str::limit(Str::squish($work->title), 40, '').'%')))
            ->with('lecturer:id,name,front_title,back_title')
            ->limit(10)
            ->get()
            ->filter(fn (ScientificWork $other): bool => ($work->doi && $other->doi === $work->doi) || self::normalizedTitle($other->title) === $title)
            ->map(fn (ScientificWork $other): array => [
                'id' => $other->id,
                'title' => $other->title,
                'lecturer' => $other->lecturer?->full_name,
                'status_label' => $other->status->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ScientificWork $work): array
    {
        $members = $work->relationLoaded('members') ? $work->members : collect();

        return [
            ...$work->only([
                'id', 'lecturer_id', 'title', 'year', 'venue', 'author_role', 'author_count', 'co_authors', 'source', 'funding',
                'relevant_to_program', 'relevant_to_roadmap', 'identifier', 'doi', 'volume', 'url', 'evidence_url',
                'ip_number', 'ip_status', 'verification_note',
            ]),
            'type' => $work->type->value,
            'type_label' => $work->type->label(),
            'category_label' => $work->type->category()->label(),
            'indexation' => $work->indexation?->value,
            'indexation_label' => $work->indexation?->label(),
            'indexation_family' => $work->indexation?->family(),
            'rank_label' => $work->indexation?->rank(),
            'author_role_label' => ScientificWork::AUTHOR_ROLES[$work->author_role] ?? $work->author_role,
            'source_label' => ScientificWork::SOURCES[$work->source] ?? $work->source,
            'funding_label' => ScientificWork::FUNDINGS[$work->funding] ?? $work->funding,
            'ip_status_label' => $work->ip_status ? (ScientificWork::IP_STATUSES[$work->ip_status] ?? $work->ip_status) : null,
            'status' => $work->status->value,
            'status_label' => $work->status->label(),
            'lecturer' => $work->relationLoaded('lecturer') ? $work->lecturer?->full_name : null,
            'study_program' => $work->relationLoaded('lecturer') ? $work->lecturer?->studyProgram?->full_name : null,
            'lecturers' => $members->where('kind', ScientificWorkMember::LECTURER)->map(fn (ScientificWorkMember $member): array => [
                'id' => $member->lecturer_id,
                'name' => $member->lecturer?->full_name ?? $member->name,
                'identifier' => $member->lecturer?->identifier,
                'study_program' => $member->lecturer?->studyProgram?->full_name,
            ])->values()->all(),
            'students' => $members->where('kind', ScientificWorkMember::STUDENT)->map(fn (ScientificWorkMember $member): array => [
                'student_id' => $member->student_id,
                'name' => $member->name,
                'nim' => $member->nim,
                'study_program' => $member->student?->studyProgram?->full_name,
            ])->values()->all(),
            'verifier' => $work->relationLoaded('verifier') ? $work->verifier?->name : null,
            'verified_at' => $work->verified_at?->toIso8601String(),
            'updated_at' => $work->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Relasi yang dibutuhkan payload().
     *
     * @return list<string>
     */
    public static function payloadRelations(): array
    {
        return [
            'lecturer.studyProgram:id,name,degree',
            'verifier:id,name',
            'members.lecturer.studyProgram:id,name,degree',
            'members.student.studyProgram:id,name,degree',
        ];
    }

    /**
     * Opsi formulir: subjenis (berikut jenis & kolom yang berlaku), indeksasi, peran, pendanaan, HKI.
     *
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'types' => array_map(fn (ScientificWorkType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'group' => $type->category()->label(),
                'indexed' => $type->usesIndexation(),
                'ip' => $type->isIntellectualProperty(),
                'category' => $type->category()->value,
            ], ScientificWorkType::cases()),
            'categories' => ScientificWorkCategory::options(),
            'indexations' => ScientificWorkIndexation::groupedOptions(),
            'indexationFamilies' => ScientificWork::options(ScientificWorkIndexation::FAMILIES),
            'authorRoles' => ScientificWork::options(ScientificWork::AUTHOR_ROLES),
            'sources' => ScientificWork::options(ScientificWork::SOURCES),
            'fundings' => ScientificWork::options(ScientificWork::FUNDINGS),
            'ipStatuses' => ScientificWork::options(ScientificWork::IP_STATUSES),
            'statuses' => ScientificWorkStatus::options(),
        ];
    }

    /**
     * DOI boleh ditempel sebagai tautan doi.org; yang disimpan hanya "10.xxxx/…".
     */
    public static function normalizeDoi(mixed $doi): ?string
    {
        $doi = trim((string) $doi);

        return $doi === '' ? null : preg_replace('#^(https?://)?(dx\.)?doi\.org/|^doi:\s*#i', '', $doi);
    }

    public static function normalizedTitle(string $title): string
    {
        return (string) Str::of($title)->lower()->replaceMatches('/[^\pL\pN]+/u', ' ')->squish();
    }
}
