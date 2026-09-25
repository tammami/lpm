<?php

namespace App\Jobs;

use App\Models\ImportJob;
use App\Services\Import\ImportManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Menjalankan impor besar di latar belakang agar pengguna tidak menunggu request HTTP.
 */
class ProcessImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public ImportJob $importJob) {}

    public function handle(ImportManager $manager): void
    {
        $manager->run($this->importJob);
    }
}
