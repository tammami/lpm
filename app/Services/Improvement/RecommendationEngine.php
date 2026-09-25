<?php

namespace App\Services\Improvement;

use App\Enums\AccreditationPeriodStatus;
use App\Enums\Priority;
use App\Enums\RecommendationOrigin;
use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Enums\UserRole;
use App\Models\AccreditationPeriod;
use App\Models\Finding;
use App\Models\Recommendation;
use App\Models\StudyProgram;
use App\Models\Survey;
use App\Models\User;
use App\Services\Accreditation\ReadinessCalculator;
use App\Services\Analytics\MonevAnalytics;
use App\Services\Monev\SurveyProgress;
use App\Services\Settings;
use Illuminate\Support\Collection;

/**
 * Mesin rekomendasi berbasis aturan (BRD §45): skor, indikator, ambang, tren, temuan, dan tenggat akreditasi.
 * Hasilnya berupa kandidat yang ditinjau LPM sebelum dijadikan rekomendasi resmi.
 */
class RecommendationEngine
{
    private const LOW_RESPONSE_RATE = 60.0;

    private const DECLINE = 0.15;

    /**
     * Periode akreditasi yang batas pengajuannya ≤ 120 hari dan kesiapannya < 80% diusulkan untuk dipercepat.
     */
    private const ACCREDITATION_HORIZON = 120;

    private const ACCREDITATION_READY = 80.0;

    public function __construct(private SurveyProgress $progress) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function candidates(User $user): Collection
    {
        $scope = $user->accessibleStudyProgramIds();
        $candidates = collect()
            ->merge($this->fromMonev($scope))
            ->merge($this->fromFindings($user))
            ->merge($this->fromAccreditation($scope))
            ->merge($this->fromAccreditationGaps($scope));

        $existing = Recommendation::query()->whereIn('rule_key', $candidates->pluck('rule_key'))->pluck('rule_key')->flip();

        return $candidates
            ->map(fn (array $candidate): array => [...$candidate, 'exists' => isset($existing[$candidate['rule_key']])])
            ->sortBy(fn (array $c) => [$c['exists'] ? 1 : 0, ['high' => 0, 'medium' => 1, 'low' => 2][$c['priority']]])
            ->values();
    }

    /**
     * @param  list<int>|null  $scope
     * @return list<array<string, mixed>>
     */
    private function fromMonev(?array $scope): array
    {
        $surveys = Survey::query()
            ->where('mode', SurveyMode::TeachingEvaluation)
            ->whereIn('status', [SurveyStatus::Closed, SurveyStatus::Archived])
            ->with(['instrumentVersion', 'academicPeriod', 'studyPrograms'])
            ->orderByDesc('starts_at')
            ->take(2)
            ->get();

        $latest = $surveys->first();

        if (! $latest) {
            return [];
        }

        $previous = $surveys->get(1);
        $threshold = (float) Settings::get('monev.low_score_threshold', 3.0);
        $period = $latest->academicPeriod?->name ?? $latest->title;
        $candidates = [];

        $programs = StudyProgram::query()->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))->get()->keyBy('id');
        $rates = collect($this->progress->byStudyProgram($latest, $scope))->keyBy('study_program_id');
        $previousScores = $previous ? collect(MonevAnalytics::for($previous, $scope)->byStudyProgram())->keyBy('id') : collect();

        foreach ($programs as $program) {
            $analytics = MonevAnalytics::for($latest, [$program->id]);
            $low = collect($analytics->byQuestion())->filter(fn (array $q): bool => $q['score'] !== null && $q['score'] < $threshold)->sortBy('score');

            if ($low->isNotEmpty()) {
                $list = $low->take(3)->map(fn (array $q): string => ($q['indicator'] ?? $q['code'])." ({$this->fmt($q['score'])})")->implode(', ');
                $candidates[] = $this->candidate(
                    "monev:{$latest->id}:program:{$program->id}:low",
                    "Perbaikan indikator pembelajaran di {$program->full_name}",
                    "Susun rencana aksi untuk indikator dengan skor di bawah ambang pada Monev {$period}: {$list}.",
                    "{$low->count()} butir < {$this->fmt($threshold)}; terendah {$this->fmt($low->first()['score'])}.",
                    RecommendationOrigin::Monev,
                    $latest,
                    $program,
                    $low->first()['indicator'] ?? null,
                    $low->first()['score'] < $threshold - 0.5 ? Priority::High : Priority::Medium,
                );
            }

            $rate = $rates->get($program->id)['rate'] ?? null;
            if ($rate !== null && $rate < self::LOW_RESPONSE_RATE) {
                $candidates[] = $this->candidate(
                    "monev:{$latest->id}:program:{$program->id}:rate",
                    "Tingkatkan partisipasi Monev di {$program->full_name}",
                    'Lakukan sosialisasi dan pengingat terstruktur (dosen PA, grup kelas) agar hasil Monev representatif.',
                    "Response rate {$this->fmt($rate, 1)}% < ".self::LOW_RESPONSE_RATE.'%.',
                    RecommendationOrigin::Monev,
                    $latest,
                    $program,
                    'Response rate',
                    Priority::Low,
                );
            }

            $current = collect($analytics->byStudyProgram())->firstWhere('id', $program->id)['score'] ?? null;
            $before = $previousScores->get($program->id)['score'] ?? null;
            if ($current !== null && $before !== null && $before - $current >= self::DECLINE) {
                $candidates[] = $this->candidate(
                    "monev:{$latest->id}:program:{$program->id}:trend",
                    "Evaluasi penurunan mutu pembelajaran di {$program->full_name}",
                    'Skor rata-rata menurun dibanding periode sebelumnya. Lakukan analisis penyebab bersama dosen dan tetapkan langkah perbaikan.',
                    "Skor turun dari {$this->fmt($before)} menjadi {$this->fmt($current)}.",
                    RecommendationOrigin::System,
                    $latest,
                    $program,
                    'Tren skor',
                    Priority::High,
                );
            }

            foreach ($analytics->byLecturer() as $lecturer) {
                if ($lecturer['score'] !== null && $lecturer['score'] < $threshold && $lecturer['sufficient']) {
                    $candidates[] = $this->candidate(
                        "monev:{$latest->id}:lecturer:{$lecturer['id']}",
                        "Pendampingan pembelajaran: {$lecturer['name']}",
                        'Fasilitasi pendampingan/peer teaching dan pelatihan metode pembelajaran berdasarkan umpan balik mahasiswa.',
                        "Skor dosen {$this->fmt($lecturer['score'])} < {$this->fmt($threshold)} (n={$lecturer['responses']}).",
                        RecommendationOrigin::Monev,
                        $latest,
                        $program,
                        'Kinerja pembelajaran dosen',
                        Priority::Medium,
                    );
                }
            }
        }

        return $candidates;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromFindings(User $user): array
    {
        return Finding::query()->visibleTo($user, $user->can('ami.view'))->overdue()->with('pic:id,name')->get()
            ->map(fn (Finding $finding): array => [
                ...$this->candidate(
                    "ami:finding:{$finding->id}:overdue",
                    "Percepat tindak lanjut temuan {$finding->code}",
                    "Diperlukan tindak lanjut oleh PIC ({$finding->pic?->name}) sebelum batas waktu: \"{$finding->title}\".",
                    'Melewati tenggat '.$finding->due_date?->translatedFormat('d M Y').'.',
                    RecommendationOrigin::Ami,
                    $finding,
                    $finding->study_program_id ? StudyProgram::query()->find($finding->study_program_id) : null,
                    'Tindak lanjut temuan',
                    Priority::High,
                ),
                'target_name' => $finding->auditee_name,
                'pic_user_id' => $finding->pic_user_id,
            ])
            ->all();
    }

    /**
     * @param  list<int>|null  $scope
     * @return list<array<string, mixed>>
     */
    private function fromAccreditation(?array $scope): array
    {
        $days = (int) Settings::get('accreditation.expiry_warning_days', 180);

        return StudyProgram::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('id', $scope))
            ->whereNotNull('accreditation_valid_until')
            ->whereDate('accreditation_valid_until', '<=', now()->addDays($days))
            ->with('accreditationBody:id,code')
            ->get()
            ->map(fn (StudyProgram $program): array => $this->candidate(
                "accreditation:{$program->id}:{$program->accreditation_valid_until->toDateString()}",
                "Persiapan reakreditasi {$program->full_name}",
                'Bentuk tim akreditasi, petakan kebutuhan dokumen pada instrumen '.($program->accreditationBody?->code ?? 'LAM').', dan lengkapi bukti yang belum tersedia.',
                'Akreditasi berakhir '.$program->accreditation_valid_until->translatedFormat('d F Y').' ('.(int) now()->diffInDays($program->accreditation_valid_until, false).' hari lagi).',
                RecommendationOrigin::Accreditation,
                null,
                $program,
                'Masa berlaku akreditasi',
                Priority::High,
            ))
            ->all();
    }

    /**
     * Periode akreditasi yang mendekati batas pengajuan tetapi dokumennya belum siap.
     *
     * @param  list<int>|null  $scope
     * @return list<array<string, mixed>>
     */
    private function fromAccreditationGaps(?array $scope): array
    {
        return AccreditationPeriod::query()
            ->where('status', AccreditationPeriodStatus::Preparing)
            ->when($scope !== null, fn ($q) => $q->whereIn('study_program_id', $scope))
            ->whereNotNull('submission_deadline')
            ->whereDate('submission_deadline', '<=', now()->addDays(self::ACCREDITATION_HORIZON))
            ->with(['studyProgram', 'version'])
            ->get()
            ->map(function (AccreditationPeriod $period): ?array {
                $summary = ReadinessCalculator::for($period)->summary();

                if ($summary['readiness'] >= self::ACCREDITATION_READY && $summary['essential_unmet'] === 0) {
                    return null;
                }

                return [
                    ...$this->candidate(
                        "accreditation:period:{$period->id}:gaps:{$period->submission_deadline->toDateString()}",
                        "Percepat pemenuhan dokumen akreditasi {$period->studyProgram->full_name}",
                        "Lengkapi {$summary['gap']} indikator tanpa bukti dan {$summary['essential_unmet']} syarat perlu yang belum terpenuhi sebelum batas pengajuan. Gunakan daftar gap pada periode {$period->code} sebagai checklist kerja.",
                        'Kesiapan dokumen '.$this->fmt($summary['readiness'], 0).'%; batas pengajuan '.$period->submission_deadline->translatedFormat('d M Y').'.',
                        RecommendationOrigin::Accreditation,
                        $period,
                        $period->studyProgram,
                        'Kesiapan dokumen akreditasi',
                        $summary['essential_unmet'] > 0 ? Priority::High : Priority::Medium,
                    ),
                    'pic_user_id' => $period->pic_user_id,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function candidate(string $key, string $title, string $description, string $rationale, RecommendationOrigin $origin, ?object $source, ?StudyProgram $program, ?string $indicator, Priority $priority): array
    {
        $kaprodi = $program
            ? User::query()->role(UserRole::AdminProdi->value)->where('study_program_id', $program->id)->where('is_active', true)->value('id')
            : null;

        return [
            'rule_key' => $key,
            'title' => $title,
            'description' => $description,
            'rationale' => $rationale,
            'origin' => $origin->value,
            'origin_label' => $origin->label(),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'target_type' => $program ? 'study_program' : 'institution',
            'target_id' => $program?->id,
            'target_name' => $program?->full_name ?? 'Institusi',
            'study_program_id' => $program?->id,
            'faculty_id' => $program?->faculty_id,
            'indicator' => $indicator,
            'priority' => $priority->value,
            'priority_label' => $priority->label(),
            'pic_user_id' => $kaprodi,
        ];
    }

    private function fmt(float $value, int $digits = 2): string
    {
        return number_format($value, $digits, ',', '.');
    }
}
