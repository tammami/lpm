<?php

namespace Database\Factories;

use App\Enums\RespondentType;
use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Models\AcademicPeriod;
use App\Models\InstrumentVersion;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Survey>
 */
class SurveyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('EVP-####-????')),
            'title' => 'Monev '.fake()->words(2, true),
            'instrument_version_id' => InstrumentVersion::factory()->published()->withLikertQuestions(),
            'academic_period_id' => AcademicPeriod::factory(),
            'mode' => SurveyMode::TeachingEvaluation,
            'respondent_type' => RespondentType::Mahasiswa,
            'is_anonymous' => true,
            'min_responses' => 3,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
            'status' => SurveyStatus::Active,
        ];
    }
}
