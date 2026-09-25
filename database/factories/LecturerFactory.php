<?php

namespace Database\Factories;

use App\Models\Lecturer;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lecturer>
 */
class LecturerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'study_program_id' => StudyProgram::factory(),
            'nidn' => fake()->unique()->numerify('08########'),
            'name' => fake()->name(),
            'back_title' => 'M.Pd.',
            'email' => fake()->unique()->safeEmail(),
            'employment_status' => 'tetap',
            'is_active' => true,
        ];
    }
}
