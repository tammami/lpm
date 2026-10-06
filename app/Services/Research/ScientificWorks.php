<?php

namespace App\Services\Research;

use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\ScientificWork;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Validasi, penyimpanan, dan bentuk data karya ilmiah yang dipakai halaman pengelola maupun dosen.
 */
class ScientificWorks
{
    /**
     * Validasi isian formulir. `lecturer_id` divalidasi terpisah oleh pemanggil.
     *
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
    {
        $type = ScientificWorkType::tryFrom((string) $request->input('type'));
        $ranks = $type?->ranks() ?? [];

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'type' => ['required', Rule::enum(ScientificWorkType::class)],
            'year' => ['required', 'integer', 'min:1980', 'max:'.(now()->year + 1)],
            'venue' => ['required', 'string', 'max:255'],
            'rank' => $ranks === [] ? ['nullable'] : ['required', Rule::in(array_keys($ranks))],
            'author_role' => ['required', Rule::in(array_keys(ScientificWork::AUTHOR_ROLES))],
            'co_authors' => ['nullable', 'string', 'max:2000'],
            'source' => ['required', Rule::in(array_keys(ScientificWork::SOURCES))],
            'funding' => ['required', Rule::in(array_keys(ScientificWork::FUNDINGS))],
            'identifier' => ['nullable', 'string', 'max:100'],
            'volume' => ['nullable', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|10\.\d{4,9}\/)\S+$/i'],
        ], [
            'rank.required' => 'Pilih peringkat/indeksasi jurnal.',
            'venue.required' => 'Isi nama jurnal, penerbit, atau kegiatan.',
            'url.regex' => 'Isi tautan lengkap (https://…) atau DOI (10.xxxx/…).',
        ]);

        // Peringkat hanya berlaku untuk jenis tertentu; buang sisa pilihan jenis sebelumnya.
        $validated['rank'] = $ranks === [] ? null : $validated['rank'];

        return $validated;
    }

    /**
     * Simpan karya baru atau perubahan. Perubahan oleh non-verifikator mengembalikan status ke "menunggu verifikasi".
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?ScientificWork $work, array $data, User $user): ScientificWork
    {
        if (! $work) {
            return ScientificWork::query()->create([...$data, 'status' => ScientificWorkStatus::Pending, 'created_by' => $user->id]);
        }

        $work->fill($data);

        if ($work->isDirty() && ! $user->can('research.verify')) {
            $work->fill(['status' => ScientificWorkStatus::Pending, 'verified_by' => null, 'verified_at' => null]);
        }

        $work->save();

        return $work;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ScientificWork $work): array
    {
        return [
            ...$work->only(['id', 'lecturer_id', 'title', 'year', 'venue', 'rank', 'author_role', 'co_authors', 'source', 'funding', 'identifier', 'volume', 'url', 'verification_note']),
            'type' => $work->type->value,
            'type_label' => $work->type->label(),
            'rank_label' => $work->rank ? (ScientificWorkType::allRanks()[$work->rank] ?? $work->rank) : null,
            'author_role_label' => ScientificWork::AUTHOR_ROLES[$work->author_role] ?? $work->author_role,
            'source_label' => ScientificWork::SOURCES[$work->source] ?? $work->source,
            'funding_label' => ScientificWork::FUNDINGS[$work->funding] ?? $work->funding,
            'status' => $work->status->value,
            'status_label' => $work->status->label(),
            'lecturer' => $work->relationLoaded('lecturer') ? $work->lecturer?->full_name : null,
            'study_program' => $work->relationLoaded('lecturer') ? $work->lecturer?->studyProgram?->full_name : null,
            'verifier' => $work->relationLoaded('verifier') ? $work->verifier?->name : null,
            'verified_at' => $work->verified_at?->toIso8601String(),
            'updated_at' => $work->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Opsi formulir (jenis beserta peringkatnya, peran penulis, sumber, pendanaan).
     *
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'types' => array_map(fn (ScientificWorkType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'journal' => $type->isJournal(),
                'ranks' => ScientificWork::options($type->ranks()),
            ], ScientificWorkType::cases()),
            'authorRoles' => ScientificWork::options(ScientificWork::AUTHOR_ROLES),
            'sources' => ScientificWork::options(ScientificWork::SOURCES),
            'fundings' => ScientificWork::options(ScientificWork::FUNDINGS),
            'statuses' => ScientificWorkStatus::options(),
        ];
    }
}
