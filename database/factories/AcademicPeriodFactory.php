<?php

namespace Database\Factories;

use App\Enums\AcademicSemester;
use App\Models\AcademicPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicPeriod>
 */
class AcademicPeriodFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2015, 2060);

        return [
            'code' => "{$year}1",
            'name' => "Ganjil {$year}/".($year + 1),
            'academic_year' => "{$year}/".($year + 1),
            'semester' => AcademicSemester::Ganjil,
            'starts_on' => "{$year}-09-01",
            'ends_on' => ($year + 1).'-01-31',
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }
}
