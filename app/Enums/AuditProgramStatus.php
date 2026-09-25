<?php

namespace App\Enums;

enum AuditProgramStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Planned = 'planned';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Planned => 'Terjadwal',
            self::Ongoing => 'Berjalan',
            self::Completed => 'Selesai',
            self::Archived => 'Diarsipkan',
        };
    }
}
