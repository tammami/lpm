<?php

namespace App\Services\Research;

use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Statistik karya ilmiah dosen dalam rentang tahun: produktivitas per prodi, sebaran jenis/peringkat/pendanaan,
 * dan daftar dosen yang belum memiliki karya.
 */
class ResearchAnalytics
{
    /**
     * Kelompok jenis untuk grafik tahunan bertumpuk.
     *
     * @var array<string, string>
     */
    public const GROUPS = [
        'journal' => 'Jurnal',
        'proceeding' => 'Prosiding',
        'book' => 'Buku & bab buku',
        'ip' => 'HKI',
        'other' => 'Karya lain',
    ];

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
            ->get(['id', 'name', 'front_title', 'back_title', 'nidn', 'study_program_id', 'academic_rank', 'employment_status']);

        $works = ScientificWork::query()
            ->whereIn('lecturer_id', $lecturers->modelKeys())
            ->whereBetween('year', [$filters['from'], $filters['to']])
            ->when($filters['verified_only'], fn ($q) => $q->where('status', ScientificWorkStatus::Verified))
            ->get(['id', 'lecturer_id', 'type', 'year', 'rank', 'source', 'funding', 'status']);

        $byLecturer = $works->groupBy('lecturer_id');

        // Tahun karya terakhir di luar rentang, agar terlihat apakah dosen "pernah" berkarya.
        $lastYears = ScientificWork::query()->whereIn('lecturer_id', $lecturers->modelKeys())
            ->groupBy('lecturer_id')->selectRaw('lecturer_id, max(year) as last_year, count(*) as total')
            ->get()->keyBy('lecturer_id');

        $lecturerRows = $lecturers->map(fn (Lecturer $lecturer): array => [
            'id' => $lecturer->id,
            'name' => $lecturer->full_name,
            'nidn' => $lecturer->nidn,
            'study_program_id' => $lecturer->study_program_id,
            'study_program' => $lecturer->studyProgram?->full_name,
            'study_program_code' => $lecturer->studyProgram?->code,
            'academic_rank' => $lecturer->academic_rank,
            'employment_status' => $lecturer->employment_status,
            'works' => $byLecturer->get($lecturer->id)?->count() ?? 0,
            'groups' => $this->groupCounts($byLecturer->get($lecturer->id) ?? collect()),
            'last_year' => $lastYears->get($lecturer->id)?->last_year,
            'all_time' => (int) ($lastYears->get($lecturer->id)?->total ?? 0),
        ]);

        $productive = $lecturerRows->where('works', '>', 0)->count();
        $years = range($filters['from'], $filters['to']);

        return [
            'summary' => [
                'lecturers' => $lecturers->count(),
                'productive' => $productive,
                'without' => $lecturers->count() - $productive,
                'rate' => $lecturers->count() ? round($productive / $lecturers->count() * 100, 1) : null,
                'works' => $works->count(),
                'per_lecturer' => $lecturers->count() ? round($works->count() / $lecturers->count(), 2) : null,
                'reputable' => $works->filter(fn (ScientificWork $w) => in_array($w->type, [ScientificWorkType::JournalAccredited, ScientificWorkType::JournalReputable], true))->count(),
                'verified' => $works->where('status', ScientificWorkStatus::Verified)->count(),
            ],
            'groups' => collect(self::GROUPS)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all(),
            'byYear' => array_map(fn (int $year): array => [
                'year' => (string) $year,
                ...$this->groupCounts($works->where('year', $year)),
                'total' => $works->where('year', $year)->count(),
                'lecturers' => $works->where('year', $year)->pluck('lecturer_id')->unique()->count(),
            ], $years),
            'byType' => $this->distribution($works, fn (ScientificWork $w) => $w->type->value, collect(ScientificWorkType::cases())->mapWithKeys(fn (ScientificWorkType $t) => [$t->value => $t->shortLabel()])->all()),
            'byRank' => $this->distribution($works->whereNotNull('rank'), fn (ScientificWork $w) => $w->rank, ScientificWorkType::allRanks(), keepEmpty: true),
            'byFunding' => $this->distribution($works, fn (ScientificWork $w) => $w->funding, ScientificWork::FUNDINGS),
            'bySource' => $this->distribution($works, fn (ScientificWork $w) => $w->source, ScientificWork::SOURCES),
            'byProgram' => $lecturerRows->groupBy('study_program_id')->map(function (Collection $rows) use ($works): array {
                $ids = $rows->pluck('id')->all();
                $programWorks = $works->whereIn('lecturer_id', $ids);
                $productive = $rows->where('works', '>', 0)->count();

                return [
                    'id' => $rows->first()['study_program_id'],
                    'name' => $rows->first()['study_program'],
                    'lecturers' => $rows->count(),
                    'productive' => $productive,
                    'rate' => round($productive / $rows->count() * 100, 1),
                    'works' => $programWorks->count(),
                    'per_lecturer' => round($programWorks->count() / $rows->count(), 2),
                    'reputable' => $programWorks->filter(fn (ScientificWork $w) => in_array($w->type, [ScientificWorkType::JournalAccredited, ScientificWorkType::JournalReputable], true))->count(),
                ];
            })->sortBy('rate')->values()->all(),
            'lecturers' => $lecturerRows->sortByDesc('works')->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, ScientificWork>  $works
     * @return array<string, int>
     */
    private function groupCounts(Collection $works): array
    {
        $counts = array_fill_keys(array_keys(self::GROUPS), 0);

        foreach ($works as $work) {
            $counts[self::group($work->type)]++;
        }

        return $counts;
    }

    public static function group(ScientificWorkType $type): string
    {
        return match (true) {
            $type->isJournal() => 'journal',
            in_array($type, [ScientificWorkType::ProceedingNational, ScientificWorkType::ProceedingInternational], true) => 'proceeding',
            in_array($type, [ScientificWorkType::Book, ScientificWorkType::BookChapter], true) => 'book',
            $type === ScientificWorkType::IntellectualProperty => 'ip',
            default => 'other',
        };
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
