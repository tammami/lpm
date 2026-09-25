<?php

namespace App\Services\Monev;

use App\Enums\QuestionType;
use App\Enums\ScoringMethod;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentVersion;

/**
 * Perhitungan skor butir dan skor respons sesuai formula yang tercatat pada versi instrumen (BR-008).
 */
class ScoreCalculator
{
    /**
     * Skor satu butir berdasarkan jawaban mentah. `null` berarti tidak dihitung.
     *
     * @param  int|list<int>|float|string|null  $value
     */
    public function answerScore(InstrumentQuestion $question, InstrumentVersion $version, int|array|float|string|null $value): ?float
    {
        if (! $question->countsTowardScore() || $value === null || $value === '' || $value === []) {
            return null;
        }

        return match ($question->type) {
            QuestionType::Likert, QuestionType::SingleChoice, QuestionType::YesNo => $question->options->firstWhere('id', (int) $value)?->score,
            QuestionType::MultipleChoice => $this->average(
                $question->options->whereIn('id', array_map('intval', (array) $value))->pluck('score')->filter(fn ($score) => $score !== null)->all(),
            ),
            QuestionType::Rating => $this->normalize((float) $value, 1, (float) ($question->settings['max_rating'] ?? 5), $version),
            QuestionType::Numeric => $question->min_score !== null && $question->max_score !== null
                ? $this->normalize((float) $value, $question->min_score, $question->max_score, $version)
                : null,
            default => null,
        };
    }

    /**
     * Skor respons dari kumpulan skor butir.
     *
     * @param  list<array{score: float|null, weight: float}>  $items
     */
    public function responseScore(array $items, ScoringMethod $method): ?float
    {
        $scored = array_values(array_filter($items, fn (array $item): bool => $item['score'] !== null));

        if ($scored === []) {
            return null;
        }

        if ($method === ScoringMethod::Weighted) {
            $weights = array_sum(array_column($scored, 'weight'));

            if ($weights <= 0) {
                return null;
            }

            return round(array_sum(array_map(fn (array $item): float => $item['score'] * $item['weight'], $scored)) / $weights, 3);
        }

        return round(array_sum(array_column($scored, 'score')) / count($scored), 3);
    }

    /**
     * @param  list<float|int>  $values
     */
    private function average(array $values): ?float
    {
        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * Normalisasi linear nilai mentah ke skala versi instrumen.
     */
    private function normalize(float $value, float $min, float $max, InstrumentVersion $version): ?float
    {
        if ($max <= $min) {
            return null;
        }

        $ratio = max(0, min(1, ($value - $min) / ($max - $min)));

        return round($version->scale_min + $ratio * ($version->scale_max - $version->scale_min), 3);
    }
}
