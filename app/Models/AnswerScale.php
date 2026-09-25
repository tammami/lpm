<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Preset skala jawaban (Likert 4, Likert 5, Kepatuhan AMI, dll.) yang dapat diterapkan ke pertanyaan.
 */
#[Fillable(['name', 'question_type', 'options', 'description', 'is_active'])]
class AnswerScale extends Model
{
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
