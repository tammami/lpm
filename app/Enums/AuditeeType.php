<?php

namespace App\Enums;

enum AuditeeType: string
{
    use HasOptions;

    case Institution = 'institution';
    case Faculty = 'faculty';
    case StudyProgram = 'study_program';
    case Unit = 'unit';

    public function label(): string
    {
        return match ($this) {
            self::Institution => 'Institusi',
            self::Faculty => 'Fakultas',
            self::StudyProgram => 'Program studi',
            self::Unit => 'Unit kerja',
        };
    }
}
