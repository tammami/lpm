<?php

namespace Database\Factories;

use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScientificWork>
 */
class ScientificWorkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lecturer_id' => Lecturer::factory(),
            'title' => rtrim(fake()->sentence(8), '.'),
            'type' => ScientificWorkType::JournalNational,
            'year' => now()->year,
            'venue' => 'Jurnal '.fake()->words(2, true),
            'indexation' => ScientificWorkIndexation::NotAccredited,
            'author_role' => 'first',
            'source' => 'research',
            'funding' => 'self',
            'url' => 'https://jurnal.contoh.ac.id/'.fake()->unique()->numberBetween(1000, 99999),
            'status' => ScientificWorkStatus::Pending,
        ];
    }

    public function indexed(ScientificWorkIndexation $indexation = ScientificWorkIndexation::Sinta3): static
    {
        return $this->state(['indexation' => $indexation]);
    }
}
