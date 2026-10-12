<?php

namespace App\Services\Research;

use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Statistik karya dosen dalam rentang tahun: produktivitas per prodi, sebaran jenis/indeksasi/pendanaan,
 * keterlibatan mahasiswa, dan daftar dosen yang belum memiliki karya.
 *
 * Karya bersama dikreditkan ke setiap dosen yang terlibat (pemilik + dosen rekan), tetapi dihitung
 * sekali untuk prodi dan institusi. Karya tidak valid/duplikat tidak dihitung.
 */
class ResearchAnalytics
{
    /**
     * Kelompok jenis yang saling lepas, untuk grafik tahunan bertumpuk.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'journal' => 'Jurnal',
        'proceeding' => 'Prosiding',
        'book' => 'Buku & book chapter',
        'ip' => 'HKI',
        'other' => 'Karya lain',
    ];

    /** Target keterlibatan mahasiswa pada contoh instrumen akreditasi (≥ 25% penelitian melibatkan mahasiswa). */
    public const STUDENT_TARGET = 25;

    /**
     * @param  array{from: int, to: int, study_program_id: ?int, employment_status: ?string, verified_only: bool}  $filters
     * @return array<string, mixed>
     */
    public function analyze(User $user, array $filters): array
    {
        $lecturers = Lecturer::query()
            ->visibleTo($user)
            ->where('is_active', true)
            ->when($filters['study_program_id'], fn ($q, $id) => $q->where('study_program_id', $id))
            ->when($filters['employment_status'], fn ($q, $status) => $q->where('employment_status', $status))
            ->with('studyProgram:id,name,degree,code')
            ->orderBy('name')
            ->get(['id', 'name', 'front_title', 'back_title', 'nidn', 'nuptk', 'study_program_id', 'academic_rank', 'employment_status']);

        $lecturerIds = $lecturers->modelKeys();

        $works = ScientificWork::query()
            ->creditedTo($lecturerIds)
            ->whereBetween('year', [$filters['from'], $filters['to']])
            ->when($filters['verified_only'], fn ($q) => $q->where('status', ScientificWorkStatus::Verified))
            ->whereNotIn('status', ScientificWorkStatus::rejectedValues())
            ->with(['lecturerMembers:id,scientific_work_id,kind,lecturer_id', 'studentMembers:id,scientific_work_id,kind,student_id,name,nim'])
            ->get(['id', 'lecturer_id', 'type', 'year', 'indexation', 'source', 'funding', 'status']);

        $byLecturer = $this->creditByLecturer($works, $lecturerIds);

        // Tahun karya terakhir di luar rentang, agar terlihat apakah dosen "pernah" berkarya.
        $history = $this->creditByLecturer(
            ScientificWork::query()->creditedTo($lecturerIds)->whereNotIn('status', ScientificWorkStatus::rejectedValues())->with('lecturerMembers:id,scientific_work_id,kind,lecturer_id')->get(['id', 'lecturer_id', 'year']),
            $lecturerIds,
        );

        $lecturerRows = $lecturers->map(function (Lecturer $lecturer) use ($byLecturer, $history): array {
            $credited = $byLecturer->get($lecturer->id, collect());
            $recap = WorkRecap::counts($credited);

            return [
                'id' => $lecturer->id,
                'name' => $lecturer->full_name,
                'nidn' => $lecturer->nidn,
                'nuptk' => $lecturer->nuptk,
                'study_program_id' => $lecturer->study_program_id,
                'study_program' => $lecturer->studyProgram?->full_name,
                'study_program_code' => $lecturer->studyProgram?->code,
                'academic_rank' => $lecturer->academic_rank,
                'employment_status' => $lecturer->employment_status,
                'works' => $credited->count(),
                'groups' => $this->groupCounts($credited),
                'recap' => $recap,
                'last_year' => $history->get($lecturer->id)?->max('year'),
                'all_time' => $history->get($lecturer->id)?->count() ?? 0,
            ];
        });

        $productive = $lecturerRows->where('works', '>', 0)->count();
        $recap = WorkRecap::counts($works);
        $years = range($filters['from'], $filters['to']);

        return [
            'summary' => [
                'lecturers' => $lecturers->count(),
                'productive' => $productive,
                'without' => $lecturers->count() - $productive,
                'rate' => $lecturers->count() ? round($productive / $lecturers->count() * 100, 1) : null,
                'works' => $works->count(),
                'per_lecturer' => $lecturers->count() ? round($lecturerRows->sum('works') / $lecturers->count(), 2) : null,
                'sinta' => $recap['sinta'],
                'scopus' => $recap['scopus'],
                'wos' => $recap['wos'],
                'verified' => $recap['valid'],
                'with_students' => $recap['with_students'],
                'student_rate' => $works->count() ? round($recap['with_students'] / $works->count() * 100, 1) : null,
                'students' => $recap['students'],
                'student_target' => self::STUDENT_TARGET,
            ],
            'groups' => collect(self::GROUPS)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all(),
            'byYear' => array_map(fn (int $year): array => [
                'year' => (string) $year,
                ...$this->groupCounts($works->where('year', $year)),
                'total' => $works->where('year', $year)->count(),
                'lecturers' => $byLecturer->filter(fn (Collection $credited) => $credited->contains('year', $year))->count(),
            ], $years),
            'byType' => $this->distribution($works, fn (ScientificWork $w) => $w->type->value, collect(ScientificWorkType::cases())->mapWithKeys(fn (ScientificWorkType $t) => [$t->value => $t->shortLabel()])->all()),
            'byIndexation' => array_map(fn (ScientificWorkIndexation $case): array => [
                'key' => $case->value,
                'label' => WorkRecap::indexationLabel($case),
                'family' => $case->family(),
                'count' => $works->where('indexation', $case)->count(),
            ], ScientificWorkIndexation::summaryCases()),
            'byFunding' => $this->distribution($works, fn (ScientificWork $w) => $w->funding, ScientificWork::FUNDINGS),
            'bySource' => $this->distribution($works, fn (ScientificWork $w) => $w->source, ScientificWork::SOURCES),
            'byProgram' => $lecturerRows->groupBy('study_program_id')->map(function (Collection $rows) use ($byLecturer): array {
                $programWorks = $rows->flatMap(fn (array $row) => $byLecturer->get($row['id'], collect()))->unique('id')->values();
                $productive = $rows->where('works', '>', 0)->count();
                $recap = WorkRecap::counts($programWorks);

                return [
                    'id' => $rows->first()['study_program_id'],
                    'name' => $rows->first()['study_program'],
                    'lecturers' => $rows->count(),
                    'productive' => $productive,
                    'rate' => round($productive / $rows->count() * 100, 1),
                    'works' => $programWorks->count(),
                    'per_lecturer' => round($rows->sum('works') / $rows->count(), 2),
                    'recap' => $recap,
                    'student_rate' => $programWorks->count() ? round($recap['with_students'] / $programWorks->count() * 100, 1) : null,
                ];
            })->sortBy('rate')->values()->all(),
            'lecturers' => $lecturerRows->sortByDesc('works')->values()->all(),
        ];
    }

    /**
     * Karya per dosen: pemilik dan dosen rekan yang ditandai sama-sama mendapat kredit.
     *
     * @param  Collection<int, ScientificWork>  $works
     * @param  list<int>  $lecturerIds
     * @return Collection<int, Collection<int, ScientificWork>>
     */
    private function creditByLecturer(Collection $works, array $lecturerIds): Collection
    {
        $allowed = array_flip($lecturerIds);
        $credit = [];

        foreach ($works as $work) {
            $ids = array_unique([$work->lecturer_id, ...$work->lecturerMembers->pluck('lecturer_id')->all()]);

            foreach ($ids as $id) {
                if (isset($allowed[$id])) {
                    $credit[$id][] = $work;
                }
            }
        }

        return collect($credit)->map(fn (array $credited) => collect($credited));
    }

    /**
     * @param  Collection<int, ScientificWork>  $works
     * @return array<string, int>
     */
    private function groupCounts(Collection $works): array
    {
        $counts = array_fill_keys(array_keys(self::GROUPS), 0);

        foreach ($works as $work) {
            $counts[$work->type->recapGroup()]++;
        }

        return $counts;
    }

    /**
     * @param  Collection<int, ScientificWork>  $works
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string, count: int, percent: float}>
     */
    private function distribution(Collection $works, callable $key, array $labels, bool $keepEmpty = false): array
    {
        $counts = $works->countBy($key);
        $total = max(1, $works->count());

        return collect($labels)
            ->map(fn (string $label, string $value): array => [
                'key' => $value,
                'label' => $label,
                'count' => (int) ($counts[$value] ?? 0),
                'percent' => round(($counts[$value] ?? 0) / $total * 100, 1),
            ])
            ->when(! $keepEmpty, fn (Collection $rows) => $rows->where('count', '>', 0))
            ->values()
            ->all();
    }
}
