<?php

namespace Database\Factories;

use App\Models\CourseClass;
use App\Models\Lecturer;
use App\Models\TeachingAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingAssignment>
 */
class TeachingAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_class_id' => CourseClass::factory(),
            'lecturer_id' => Lecturer::factory(),
            'role' => 'koordinator',
        ];
    }
}
