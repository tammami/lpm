<?php

namespace Database\Factories;

use App\Models\Faculty;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'code' => strtoupper(fake()->unique()->lexify('F???')),
            'name' => 'Fakultas '.fake()->word(),
            'is_active' => true,
        ];
    }
}
