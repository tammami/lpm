<?php

namespace App\Http\Controllers\Research;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Research\ResearchAnalytics;
use App\Support\Options;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Analisis karya ilmiah: siapa yang belum berkarya dan statistik riset per rentang tahun.
 */
class ResearchAnalysisController extends Controller
{
    /** Rentang bawaan mengikuti LKPS: TS-2 s.d. TS. */
    private const DEFAULT_SPAN = 2;

    public function __construct(private ResearchAnalytics $analytics) {}

    public function index(Request $request): Response
    {
        $filters = $this->filtersFrom($request);

        return Inertia::render('research/analysis', [
            ...$this->analytics->analyze($request->user(), $filters),
            'filters' => $filters,
            'studyPrograms' => Options::studyPrograms($request->user()),
            'yearOptions' => array_map(fn (int $year): array => ['value' => $year, 'label' => (string) $year], range(now()->year + 1, now()->year - 15)),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filtersFrom($request);
        $result = $this->analytics->analyze($request->user(), $filters);
        $range = $filters['from'] === $filters['to'] ? (string) $filters['from'] : "{$filters['from']}-{$filters['to']}";
        $groupLabels = array_values(ResearchAnalytics::GROUPS);
        $lecturers = collect($result['lecturers']);

        AuditLogger::log('exported', 'research', null, "Ekspor analisis karya ilmiah {$range}");

        return Spreadsheet::downloadSheets("analisis-karya-ilmiah-{$range}.xlsx", [
            [
                'title' => 'Rekap per dosen',
                'headers' => ['NIDN', 'Dosen', 'Program Studi', 'Jabatan', 'Status', ...$groupLabels, "Total {$range}", 'Karya terakhir (tahun)'],
                'rows' => $lecturers->map(fn (array $row): array => [
                    $row['nidn'], $row['name'], $row['study_program'], $row['academic_rank'], $this->employment($row['employment_status']),
                    ...array_values($row['groups']), $row['works'], $row['last_year'],
                ]),
                'widths' => [14, 32, 30, 16, 12, ...array_fill(0, count($groupLabels), 12), 12, 14],
            ],
            [
                'title' => 'Belum punya karya',
                'headers' => ['NIDN', 'Dosen', 'Program Studi', 'Jabatan', 'Status', 'Karya terakhir (tahun)', 'Total karya tercatat'],
                'rows' => $lecturers->where('works', 0)->sortBy(['study_program', 'name'])->map(fn (array $row): array => [
                    $row['nidn'], $row['name'], $row['study_program'], $row['academic_rank'], $this->employment($row['employment_status']),
                    $row['last_year'] ?? 'Belum pernah', $row['all_time'],
                ]),
                'widths' => [14, 32, 30, 16, 12, 18, 16],
            ],
            [
                'title' => 'Per program studi',
                'headers' => ['Program Studi', 'Dosen aktif', 'Dosen berkarya', 'Persentase (%)', 'Jumlah karya', 'Rata-rata per dosen', 'Jurnal SINTA/bereputasi'],
                'rows' => array_map(fn (array $row): array => [$row['name'], $row['lecturers'], $row['productive'], $row['rate'], $row['works'], $row['per_lecturer'], $row['reputable']], $result['byProgram']),
                'widths' => [32, 12, 14, 14, 12, 16, 20],
            ],
            [
                'title' => 'Per tahun',
                'headers' => ['Tahun', ...$groupLabels, 'Total', 'Dosen berkarya'],
                'rows' => array_map(fn (array $row): array => [
                    $row['year'], ...array_map(fn (string $key) => $row[$key], array_keys(ResearchAnalytics::GROUPS)), $row['total'], $row['lecturers'],
                ], $result['byYear']),
                'widths' => [10, ...array_fill(0, count($groupLabels), 14), 10, 14],
            ],
        ]);
    }

    /**
     * @return array{from: int, to: int, study_program_id: ?int, employment_status: ?string, verified_only: bool}
     */
    private function filtersFrom(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'to' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'study_program_id' => ['nullable', 'integer'],
            'employment_status' => ['nullable', Rule::in(['tetap', 'tidak_tetap'])],
            'verified_only' => ['nullable', 'boolean'],
        ]);

        $to = (int) ($validated['to'] ?? now()->year);
        $from = (int) ($validated['from'] ?? $to - self::DEFAULT_SPAN);
        [$from, $to] = [min($from, $to), max($from, $to)];
        $programId = isset($validated['study_program_id']) ? (int) $validated['study_program_id'] : null;

        if ($programId) {
            $this->authorizeStudyProgram($request, $programId);
        }

        return [
            'from' => max($from, $to - 20),
            'to' => $to,
            'study_program_id' => $programId,
            'employment_status' => $validated['employment_status'] ?? null,
            'verified_only' => (bool) ($validated['verified_only'] ?? false),
        ];
    }

    private function employment(?string $status): string
    {
        return $status === 'tidak_tetap' ? 'Tidak tetap' : 'Tetap';
    }
}
