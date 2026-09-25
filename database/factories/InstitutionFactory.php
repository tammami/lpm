<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Institut '.fake()->company(),
            'short_name' => strtoupper(fake()->lexify('????')),
            'is_active' => true,
        ];
    }
}
