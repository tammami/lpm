<?php

namespace Database\Factories;

use App\Enums\SuggestionCategory;
use App\Enums\SuggestionStatus;
use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Suggestion>
 */
class SuggestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sender_role' => 'mahasiswa',
            'is_anonymous' => false,
            'category' => SuggestionCategory::Facility,
            'subject' => 'Perbaikan '.fake()->words(2, true),
            'message' => fake()->paragraph(),
            'status' => SuggestionStatus::New,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(['is_anonymous' => true, 'study_program_id' => null]);
    }
}
