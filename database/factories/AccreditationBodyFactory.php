<?php

namespace Database\Factories;

use App\Models\AccreditationBody;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccreditationBody>
 */
class AccreditationBodyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'LAM'.strtoupper(fake()->unique()->lexify('???')),
            'name' => 'Lembaga Akreditasi '.fake()->word(),
            'is_active' => true,
        ];
    }
}
