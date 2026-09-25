<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'code' => strtoupper(fake()->unique()->lexify('U???')),
            'name' => 'Unit '.fake()->word(),
            'type' => 'unit',
            'is_active' => true,
        ];
    }
}
