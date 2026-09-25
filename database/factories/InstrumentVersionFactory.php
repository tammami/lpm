<?php

namespace Database\Factories;

use App\Enums\InstrumentVersionStatus;
use App\Enums\QuestionType;
use App\Enums\ScoringMethod;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentVersion>
 */
class InstrumentVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instrument_id' => Instrument::factory(),
            'version' => '1.0',
            'version_number' => 1,
            'status' => InstrumentVersionStatus::Draft,
            'scoring_method' => ScoringMethod::Average,
            'scale_min' => 1,
            'scale_max' => 4,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => InstrumentVersionStatus::Published, 'published_at' => now()]);
    }

    /**
     * Isi versi dengan satu bagian berisi butir Likert 1–4 (bobot sesuai daftar) dan satu komentar.
     *
     * @param  list<float>  $weights
     */
    public function withLikertQuestions(array $weights = [1, 1, 1]): static
    {
        return $this->afterCreating(function (InstrumentVersion $version) use ($weights): void {
            $section = $version->sections()->create(['code' => 'A', 'title' => 'Bagian A', 'sort_order' => 1]);

            foreach ($weights as $index => $weight) {
                $question = $section->questions()->create([
                    'instrument_version_id' => $version->id,
                    'code' => 'A'.($index + 1),
                    'label' => 'Pernyataan '.($index + 1),
                    'type' => QuestionType::Likert,
                    'weight' => $weight,
                    'sort_order' => $index + 1,
                ]);

                foreach ([1, 2, 3, 4] as $score) {
                    $question->options()->create(['label' => "Skor {$score}", 'value' => (string) $score, 'score' => $score, 'sort_order' => $score]);
                }
            }

            $section->questions()->create([
                'instrument_version_id' => $version->id,
                'code' => 'A'.(count($weights) + 1),
                'label' => 'Komentar',
                'type' => QuestionType::LongText,
                'is_required' => false,
                'is_scored' => false,
                'sort_order' => count($weights) + 1,
            ]);
        });
    }
}
