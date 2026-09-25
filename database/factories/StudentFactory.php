<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'study_program_id' => StudyProgram::factory(),
            'nim' => fake()->unique()->numerify('25######'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'entry_year' => 2025,
            'semester' => 3,
            'status' => StudentStatus::Aktif,
        ];
    }
}
