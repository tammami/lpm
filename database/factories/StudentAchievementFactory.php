<?php

namespace Database\Factories;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementStatus;
use App\Models\Student;
use App\Models\StudentAchievement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentAchievement>
 */
class StudentAchievementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'title' => 'Lomba '.fake()->words(3, true),
            'category' => AchievementCategory::Academic,
            'level' => AchievementLevel::Provincial,
            'achievement' => 'Juara 1',
            'participation' => 'individual',
            'organizer' => 'Dinas Pendidikan '.fake()->city(),
            'achieved_on' => now()->subMonth()->toDateString(),
            'evidence_url' => 'https://contoh.ac.id/sertifikat/'.fake()->unique()->numberBetween(100, 99999),
            'status' => AchievementStatus::Pending,
        ];
    }
}
