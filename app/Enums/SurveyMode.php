<?php

namespace App\Enums;

enum SurveyMode: string
{
    use HasOptions;

    case TeachingEvaluation = 'teaching_evaluation';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::TeachingEvaluation => 'Evaluasi Pembelajaran (per dosen & kelas)',
            self::General => 'Survei Umum (sekali per responden)',
        };
    }
}
