<?php

namespace App\Events;

use App\Models\Survey;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Kegiatan Monev pertama kali dibuka untuk responden.
 */
class SurveyOpened
{
    use Dispatchable, SerializesModels;

    public function __construct(public Survey $survey) {}
}
