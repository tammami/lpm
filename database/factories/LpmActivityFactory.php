<?php

namespace Database\Factories;

use App\Enums\LpmActivityCategory;
use App\Enums\LpmActivityStatus;
use App\Models\LpmActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LpmActivity>
 */
class LpmActivityFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfDay()->addDays(fake()->numberBetween(-60, 60));

        return [
            'code' => 'PK-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'year' => $start->year,
            'title' => 'Workshop '.fake()->words(3, true),
            'category' => LpmActivityCategory::Training,
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDay()->toDateString(),
            'pic' => fake()->name(),
            'status' => LpmActivityStatus::Planned,
        ];
    }
}
