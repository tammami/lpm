<?php

namespace App\Services\Research;

use App\Enums\ScientificWorkCategory;
use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Models\ScientificWork;
use App\Models\ScientificWorkMember;
use Illuminate\Support\Collection;

/**
 * Hitungan rekap karya dosen persis seperti rumus template LPM (Rekap LPM, Rekap per Prodi, Rekap per Dosen),
 * ditambah keterlibatan mahasiswa. Karya tidak valid/duplikat tidak dihitung.
 * Karya diharapkan sudah memuat relasi `studentMembers`.
 */
final class WorkRecap
{
    /**
     * Kolom rekap per prodi/dosen sesuai template, lalu kolom tambahan aplikasi.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'total' => 'Total Karya',
        'journal' => 'Artikel Jurnal',
        'proceeding' => 'Prosiding',
        'book' => 'Buku',
        'other' => 'Karya Lainnya',
        'sinta' => 'Sinta',
        'scopus' => 'Scopus',
        'wos' => 'WoS',
        'ip' => 'HKI',
        'valid' => 'Valid LPM',
        'with_students' => 'Karya Melibatkan Mahasiswa',
        'students' => 'Mahasiswa Terlibat',
    ];

    /**
     * Karya yang dihitung dalam rekap: selain yang dinyatakan tidak valid atau duplikat.
     *
     * @param  Collection<int, ScientificWork>  $works
     * @return Collection<int, ScientificWork>
     */
    public static function counted(Collection $works): Collection
    {
        return $works->filter(fn (ScientificWork $work): bool => $work->status->isCounted())->values();
    }

    /**
     * @param  Collection<int, ScientificWork>  $works  karya yang dihitung (lihat counted())
     * @return array<string, int>
     */
    public static function counts(Collection $works): array
    {
        $counts = array_fill_keys(array_keys(self::COLUMNS), 0);

        foreach ($works as $work) {
            $counts['total']++;
            $counts['journal'] += (int) $work->type->isJournal();
            $counts['proceeding'] += (int) $work->type->isProceeding();
            $counts['book'] += (int) ($work->type->category() === ScientificWorkCategory::Book);
            // Seperti template: "Karya Lainnya" mencakup HKI, dan HKI juga dihitung tersendiri.
            $counts['other'] += (int) ($work->type->category() === ScientificWorkCategory::Other);
            $counts['ip'] += (int) $work->type->isIntellectualProperty();
            $counts['valid'] += (int) ($work->status === ScientificWorkStatus::Verified);
            $counts['with_students'] += (int) $work->studentMembers->isNotEmpty();

            if ($family = $work->indexation?->family()) {
                if (isset($counts[$family])) {
                    $counts[$family]++;
                }
            }
        }

        $counts['students'] = self::studentCount($works);

        return $counts;
    }

    /**
     * Jumlah mahasiswa berbeda yang terlibat (mahasiswa yang sama di beberapa karya dihitung sekali).
     *
     * @param  Collection<int, ScientificWork>  $works
     */
    public static function studentCount(Collection $works): int
    {
        return $works->flatMap(fn (ScientificWork $work) => $work->studentMembers)
            ->map(fn (ScientificWorkMember $member): string => $member->student_id
                ? "id:{$member->student_id}"
                : 'nama:'.mb_strtolower(trim($member->name)).'|'.($member->nim ?? ''))
            ->unique()
            ->count();
    }

    /**
     * Baris "Rekap LPM": [label, nilai] ringkasan dan rincian akreditasi/indeksasi.
     *
     * @param  Collection<int, ScientificWork>  $all  seluruh karya, termasuk yang tidak valid/duplikat
     * @return array{summary: list<array{0: string, 1: int|string}>, indexation: list<array{0: string, 1: int}>}
     */
    public static function lpm(Collection $all): array
    {
        $works = self::counted($all);
        $counts = self::counts($works);
        $byIndexation = $works->countBy(fn (ScientificWork $work): string => $work->indexation?->value ?? '');

        return [
            'summary' => [
                ['Total seluruh karya', $counts['total']],
                ['Artikel jurnal', $counts['journal']],
                ['Prosiding', $counts['proceeding']],
                ['Buku akademik', $counts['book']],
                ['Karya lainnya', $counts['other']],
                ['Publikasi Sinta', $counts['sinta']],
                ['Publikasi Scopus', $counts['scopus']],
                ['Publikasi Web of Science', $counts['wos']],
                ['HKI', $counts['ip']],
                ['Karya valid terverifikasi LPM', $counts['valid']],
                ['Belum diverifikasi', $works->where('status', ScientificWorkStatus::Pending)->count()],
                ['Tidak valid / duplikat (tidak dihitung)', $all->count() - $works->count()],
                ['Karya melibatkan mahasiswa', $counts['with_students']],
                ['Persentase karya melibatkan mahasiswa', $counts['total'] ? round($counts['with_students'] / $counts['total'] * 100, 1).'%' : '—'],
                ['Mahasiswa terlibat', $counts['students']],
            ],
            'indexation' => array_map(
                fn (ScientificWorkIndexation $case): array => [self::indexationLabel($case), (int) ($byIndexation[$case->value] ?? 0)],
                ScientificWorkIndexation::summaryCases(),
            ),
        ];
    }

    /**
     * Label ringkas seperti kolom "Ringkasan Akreditasi/Indeksasi" (mis. "WoS SCIE").
     */
    public static function indexationLabel(ScientificWorkIndexation $case): string
    {
        return str_starts_with($case->value, 'wos_') ? 'WoS '.$case->rank() : $case->label();
    }
}
