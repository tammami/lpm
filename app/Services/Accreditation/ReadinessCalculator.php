<?php

namespace App\Services\Accreditation;

use App\Enums\EvidenceStatus;
use App\Models\AccreditationCriterion;
use App\Models\AccreditationIndicator;
use App\Models\AccreditationPeriod;
use App\Models\EvidenceMapping;
use Illuminate\Support\Collection;

/**
 * Kesiapan akreditasi per periode.
 *
 * - Indikator "siap" bila memiliki minimal satu bukti terverifikasi & masih berlaku pada periode ini,
 *   "sebagian" bila buktinya masih menunggu verifikasi, dan "gap" bila belum ada bukti.
 * - Kesiapan dokumen = (siap + ½ sebagian) / total indikator.
 * - Estimasi skor (skala 0–400) = Σ bobot kriteria × rata-rata tertimbang skor penilaian diri / skala maks.
 *   Indikator yang belum dinilai tidak dihitung; peringkat estimasi baru ditampilkan bila cakupan penilaian ≥ 50%.
 */
class ReadinessCalculator
{
    public const MAX_SCORE = 400;

    /**
     * Skor penilaian diri minimal agar indikator esensial (syarat perlu) dianggap terpenuhi.
     */
    public const ESSENTIAL_MIN = 2.0;

    /**
     * Cakupan penilaian diri minimal (%) agar peringkat estimasi ditampilkan.
     */
    public const MIN_COVERAGE = 50.0;

    /** @var Collection<int, AccreditationCriterion> */
    private Collection $criteria;

    /** @var Collection<int, AccreditationIndicator> */
    private Collection $indicators;

    /** @var array<int, array<string, mixed>> */
    private array $states = [];

    /** @var array<int, int>|null */
    private ?array $rootMap = null;

    public function __construct(private AccreditationPeriod $period)
    {
        $period->loadMissing('version');
        $this->criteria = AccreditationCriterion::query()->where('instrument_version_id', $period->instrument_version_id)->orderBy('sort_order')->get();
        $this->indicators = AccreditationIndicator::query()->where('instrument_version_id', $period->instrument_version_id)->orderBy('sort_order')->get();
        $this->resolveStates();
    }

    public static function for(AccreditationPeriod $period): self
    {
        return new self($period);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $states = collect($this->states);
        $total = $states->count();
        $ready = $states->where('status', 'ready')->count();
        $partial = $states->where('status', 'partial')->count();
        $assessed = $states->whereNotNull('self_score')->count();
        $score = $this->estimatedScore();
        $coverage = $total ? round($assessed / $total * 100, 1) : 0.0;

        return [
            'total' => $total,
            'ready' => $ready,
            'partial' => $partial,
            'gap' => $total - $ready - $partial,
            'readiness' => $total ? round(($ready + $partial * 0.5) / $total * 100, 1) : 0.0,
            'assessed' => $assessed,
            'coverage' => $coverage,
            'estimated_score' => $score,
            'estimated_grade' => $coverage >= self::MIN_COVERAGE ? $this->period->version->gradeFor($score) : null,
            'essential_unmet' => $states->filter(fn (array $s): bool => $s['is_essential'] && ! $s['essential_met'])->count(),
        ];
    }

    /**
     * Rekap per kriteria utama (sub-kriteria digabung ke induknya).
     *
     * @return list<array<string, mixed>>
     */
    public function byCriterion(): array
    {
        $scaleMax = $this->period->version->scale_max ?: 4;

        return $this->roots()->map(function (AccreditationCriterion $criterion) use ($scaleMax): array {
            $states = collect($this->statesUnder($criterion));
            $total = $states->count();
            $ready = $states->where('status', 'ready')->count();
            $partial = $states->where('status', 'partial')->count();
            $average = $this->weightedAverage($states);

            return [
                'id' => $criterion->id,
                'code' => $criterion->code,
                'title' => $criterion->title,
                'weight' => $criterion->weight,
                'total' => $total,
                'ready' => $ready,
                'partial' => $partial,
                'gap' => $total - $ready - $partial,
                'readiness' => $total ? round(($ready + $partial * 0.5) / $total * 100, 1) : null,
                'self_average' => $average !== null ? round($average, 2) : null,
                'self_percent' => $average !== null ? round($average / $scaleMax * 100, 1) : null,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>> indicator_id => status
     */
    public function states(): array
    {
        return $this->states;
    }

    /**
     * Indikator yang belum siap, urut kriteria — bahan gap analysis & checklist dokumen.
     *
     * @return list<array<string, mixed>>
     */
    public function gaps(): array
    {
        $order = $this->criteria->pluck('sort_order', 'id');
        $rootOf = $this->rootMap();

        return $this->indicators
            ->filter(fn (AccreditationIndicator $i): bool => $this->states[$i->id]['status'] !== 'ready' || ($i->is_essential && ! $this->states[$i->id]['essential_met']))
            ->sortBy(fn (AccreditationIndicator $i): array => [$order[$rootOf[$i->criterion_id]] ?? 0, $order[$i->criterion_id] ?? 0, $i->sort_order])
            ->map(fn (AccreditationIndicator $i): array => [
                'id' => $i->id,
                'code' => $i->code,
                'statement' => $i->statement,
                'evidence_hint' => $i->evidence_hint,
                'criterion' => $this->criteria->firstWhere('id', $rootOf[$i->criterion_id])?->code,
                'is_essential' => $i->is_essential,
                ...$this->states[$i->id],
            ])
            ->values()
            ->all();
    }

    public function estimatedScore(): ?float
    {
        $scaleMax = $this->period->version->scale_max ?: 4;
        $weighted = 0.0;
        $weights = 0.0;

        foreach ($this->roots() as $criterion) {
            $average = $this->weightedAverage(collect($this->statesUnder($criterion)));

            if ($average === null) {
                continue;
            }

            $weight = $criterion->weight > 0 ? $criterion->weight : 1;
            $weighted += $weight * ($average / $scaleMax);
            $weights += $weight;
        }

        return $weights > 0 ? round($weighted / $weights * self::MAX_SCORE, 1) : null;
    }

    private function resolveStates(): void
    {
        $mappings = EvidenceMapping::query()
            ->where('mappable_type', (new AccreditationIndicator)->getMorphClass())
            ->where('context_id', $this->period->id)
            ->whereIn('mappable_id', $this->indicators->pluck('id'))
            ->with('evidence:id,status,valid_until')
            ->get()
            ->groupBy('mappable_id');

        $assessments = $this->period->assessments()->get()->keyBy('indicator_id');

        foreach ($this->indicators as $indicator) {
            $evidence = ($mappings[$indicator->id] ?? collect())->pluck('evidence')->filter();
            $valid = $evidence->filter(fn ($e): bool => $e->status === EvidenceStatus::Verified && ! $e->isExpired())->count();
            $pending = $evidence->filter(fn ($e): bool => $e->status === EvidenceStatus::Pending)->count();
            $score = $assessments[$indicator->id]->self_score ?? null;
            $status = $valid > 0 ? 'ready' : ($pending > 0 ? 'partial' : 'gap');

            $this->states[$indicator->id] = [
                'status' => $status,
                'evidence_count' => $evidence->count(),
                'verified_count' => $valid,
                'self_score' => $score,
                'weight' => $indicator->weight,
                'is_essential' => $indicator->is_essential,
                'essential_met' => ! $indicator->is_essential || ($status === 'ready' && $score !== null && $score >= self::ESSENTIAL_MIN),
            ];
        }
    }

    /**
     * @return Collection<int, AccreditationCriterion>
     */
    private function roots(): Collection
    {
        return $this->criteria->whereNull('parent_id')->values();
    }

    /**
     * @return array<int, int> criterion_id => root criterion_id
     */
    private function rootMap(): array
    {
        if ($this->rootMap !== null) {
            return $this->rootMap;
        }

        $parents = $this->criteria->pluck('parent_id', 'id');
        $map = [];

        foreach ($parents as $id => $parent) {
            $root = $id;
            $guard = 0;

            while ($parents[$root] ?? null) {
                $root = $parents[$root];

                if (++$guard > 10) {
                    break;
                }
            }

            $map[$id] = $root;
        }

        return $this->rootMap = $map;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statesUnder(AccreditationCriterion $root): array
    {
        $rootOf = $this->rootMap();

        return $this->indicators
            ->filter(fn (AccreditationIndicator $i): bool => ($rootOf[$i->criterion_id] ?? null) === $root->id)
            ->map(fn (AccreditationIndicator $i): array => $this->states[$i->id])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $states
     */
    private function weightedAverage(Collection $states): ?float
    {
        $scored = $states->whereNotNull('self_score');
        $weights = $scored->sum(fn (array $s): float => $s['weight'] > 0 ? $s['weight'] : 1);

        return $weights > 0
            ? $scored->sum(fn (array $s): float => $s['self_score'] * ($s['weight'] > 0 ? $s['weight'] : 1)) / $weights
            : null;
    }
}
