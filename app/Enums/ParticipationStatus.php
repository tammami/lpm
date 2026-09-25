<?php

namespace App\Enums;

enum ParticipationStatus: string
{
    use HasOptions;

    case Submitted = 'submitted';
    case Reopened = 'reopened';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Terkirim',
            self::Reopened => 'Dibuka kembali',
        };
    }
}
