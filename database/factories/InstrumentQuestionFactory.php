<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentQuestion>
 */
class InstrumentQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instrument_section_id' => InstrumentSection::factory(),
            'instrument_version_id' => fn (array $attributes) => InstrumentSection::query()->find($attributes['instrument_section_id'])->instrument_version_id,
            'code' => strtoupper(fake()->bothify('A#')),
            'label' => fake()->sentence(),
            'type' => QuestionType::Text,
            'is_scored' => false,
            'weight' => 1,
            'sort_order' => 1,
        ];
    }
}
