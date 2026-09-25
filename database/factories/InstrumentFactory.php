<?php

namespace Database\Factories;

use App\Enums\InstrumentType;
use App\Enums\RespondentType;
use App\Models\Instrument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Instrument>
 */
class InstrumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('INS-###??')),
            'name' => 'Instrumen '.fake()->words(2, true),
            'type' => InstrumentType::MonevPembelajaran,
            'respondent_type' => RespondentType::Mahasiswa,
        ];
    }
}
