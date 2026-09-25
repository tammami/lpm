<?php

namespace Database\Factories;

use App\Models\InstrumentSection;
use App\Models\InstrumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstrumentSection>
 */
class InstrumentSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instrument_version_id' => InstrumentVersion::factory(),
            'code' => 'A',
            'title' => 'Bagian '.fake()->word(),
            'sort_order' => 1,
        ];
    }
}
