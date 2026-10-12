<?php

namespace App\Services\Research;

use App\Models\Institution;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\ScientificWorkMember;
use Illuminate\Support\Collection;

/**
 * Isi workbook ekspor dengan susunan template "Data Karya Dosen" LPM: Rekap LPM, Data Karya Dosen,
 * Rekap per Prodi, Rekap per Dosen. Nilai rekap dihitung aplikasi (bukan rumus Excel).
 */
final class WorkWorkbook
{
    /**
     * 28 kolom template sesuai urutannya, lalu kolom tambahan aplikasi.
     *
     * @var list<string>
     */
    public const DATA_HEADERS = [
        'No.', 'Nama Dosen', 'NIDN/NIDK', 'Program Studi', 'Fakultas/Unit', 'Jenis Karya', 'Subjenis Karya', 'Judul Karya',
        'Tahun', 'Peran Dosen', 'Jumlah Penulis', 'Nama Jurnal/Penerbit/Media', 'Volume/No./Edisi', 'ISSN/ISBN', 'DOI',
        'Indeksasi/Akreditasi', 'Peringkat/Kuartil', 'Nomor HKI', 'Status HKI', 'Link Publikasi', 'Link Bukti', 'Sumber Dana',
        'Relevansi dengan Prodi', 'Relevansi Roadmap Penelitian', 'Status Verifikasi LPM', 'Tanggal Verifikasi', 'Verifikator',
        'Catatan Verifikasi',
        'NUPTK', 'Dosen Rekan IAIA', 'Mahasiswa Terlibat', 'Jumlah Mahasiswa', 'Penulis Lain (Luar IAIA)', 'Asal Kegiatan',
    ];

    /**
     * Relasi yang perlu dimuat pada karya sebelum memanggil sheets().
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return [
            'lecturer.studyProgram.faculty:id,name',
            'verifier:id,name',
            'members.lecturer:id,name,front_title,back_title,nidn,nuptk',
            'studentMembers',
        ];
    }

    /**
     * @param  Collection<int, ScientificWork>  $works  karya hasil filter, dengan relasi dari relations()
     * @param  Collection<int, Lecturer>  $lecturers  dosen untuk Rekap per Dosen (termasuk yang belum berkarya)
     * @return list<array{title: string, headers: list<string>, rows: iterable<list<mixed>>, widths: list<float>}>
     */
    public function sheets(Collection $works, Collection $lecturers): array
    {
        $lpm = WorkRecap::lpm($works);
        $institution = Institution::current();
        $recapHeaders = array_values(WorkRecap::COLUMNS);

        $creditIds = fn (ScientificWork $work): array => array_unique([
            $work->lecturer_id,
            ...$work->members->where('kind', ScientificWorkMember::LECTURER)->pluck('lecturer_id')->all(),
        ]);

        $counted = WorkRecap::counted($works);

        $byLecturer = $lecturers->mapWithKeys(fn (Lecturer $lecturer): array => [
            $lecturer->id => $counted->filter(fn (ScientificWork $work): bool => in_array($lecturer->id, $creditIds($work), true))->values(),
        ]);

        $programs = $lecturers->groupBy('study_program_id')
            ->map(fn (Collection $rows) => [
                'name' => $rows->first()->studyProgram?->full_name ?? '—',
                'works' => $rows->flatMap(fn (Lecturer $lecturer) => $byLecturer[$lecturer->id])->unique('id')->values(),
            ])
            ->sortBy('name');

        return [
            [
                'title' => 'Rekap LPM',
                'headers' => ['Rekapitulasi Karya Dosen', 'Jumlah'],
                'rows' => [
                    ['LPM '.($institution?->name ?? config('app.name')), null],
                    ['Dicetak '.now()->translatedFormat('j F Y H:i'), null],
                    [null, null],
                    ...$lpm['summary'],
                    [null, null],
                    ['Ringkasan Akreditasi/Indeksasi', 'Jumlah'],
                    ...$lpm['indexation'],
                ],
                'widths' => [44, 14],
            ],
            [
                'title' => 'Data Karya Dosen',
                'headers' => self::DATA_HEADERS,
                'rows' => $works->values()->map(fn (ScientificWork $work, int $index): array => $this->dataRow($work, $index + 1)),
                'widths' => [6, 30, 14, 30, 28, 24, 26, 60, 8, 20, 10, 34, 18, 18, 26, 26, 14, 18, 18, 36, 36, 18, 12, 14, 20, 14, 22, 36, 20, 36, 40, 10, 36, 20],
            ],
            [
                'title' => 'Rekap per Prodi',
                'headers' => ['Program Studi', ...$recapHeaders],
                'rows' => $programs->map(fn (array $program): array => [$program['name'], ...array_values(WorkRecap::counts($program['works']))])->values(),
                'widths' => [36, ...array_fill(0, count($recapHeaders), 12)],
            ],
            [
                'title' => 'Rekap per Dosen',
                'headers' => ['Nama Dosen', 'NIDN/NIDK', 'Program Studi', ...$recapHeaders, 'NUPTK'],
                'rows' => $lecturers->map(fn (Lecturer $lecturer): array => [
                    $lecturer->full_name,
                    $lecturer->nidn,
                    $lecturer->studyProgram?->full_name,
                    ...array_values(WorkRecap::counts($byLecturer[$lecturer->id])),
                    $lecturer->nuptk,
                ])->values(),
                'widths' => [32, 14, 30, ...array_fill(0, count($recapHeaders), 12), 20],
            ],
        ];
    }

    /**
     * @return list<mixed>
     */
    private function dataRow(ScientificWork $work, int $number): array
    {
        $lecturer = $work->lecturer;
        $yesNo = fn (?bool $value): ?string => $value === null ? null : ($value ? 'Ya' : 'Tidak');

        return [
            $number,
            $lecturer->full_name,
            $lecturer->nidn,
            $lecturer->studyProgram?->full_name,
            $lecturer->studyProgram?->faculty?->name,
            $work->type->category()->label(),
            $work->type->label(),
            $work->title,
            $work->year,
            ScientificWork::AUTHOR_ROLES[$work->author_role] ?? $work->author_role,
            $work->author_count,
            $work->venue,
            $work->volume,
            $work->identifier,
            $work->doi,
            $work->indexation?->label(),
            $work->indexation?->rank(),
            $work->ip_number,
            $work->ip_status ? (ScientificWork::IP_STATUSES[$work->ip_status] ?? $work->ip_status) : 'Tidak Berlaku',
            $work->url,
            $work->evidence_url,
            ScientificWork::FUNDINGS[$work->funding] ?? $work->funding,
            $yesNo($work->relevant_to_program),
            $yesNo($work->relevant_to_roadmap),
            $work->status->label(),
            $work->verified_at?->format('Y-m-d'),
            $work->verifier?->name,
            $work->verification_note,
            $lecturer->nuptk,
            $work->members->where('kind', ScientificWorkMember::LECTURER)
                ->map(fn (ScientificWorkMember $member): string => self::person($member->lecturer?->full_name ?? $member->name, $member->lecturer?->identifier))
                ->implode('; ') ?: null,
            $work->studentMembers->map(fn (ScientificWorkMember $member): string => self::person($member->name, $member->nim))->implode('; ') ?: null,
            $work->studentMembers->count(),
            $work->co_authors,
            ScientificWork::SOURCES[$work->source] ?? $work->source,
        ];
    }

    /**
     * "Nama (nomor)" — format yang juga dibaca kembali oleh impor.
     */
    public static function person(string $name, ?string $number): string
    {
        return $number ? "{$name} ({$number})" : $name;
    }
}
