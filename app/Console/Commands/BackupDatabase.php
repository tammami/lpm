<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('backup:database')]
#[Description('Cadangkan database ke folder penyimpanan dan hapus cadangan lama')]
class BackupDatabase extends Command
{
    public function handle(DatabaseBackup $backups): int
    {
        try {
            $name = $backups->create();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Cadangan gagal: '.$exception->getMessage());

            return self::FAILURE;
        }

        AuditLogger::log('backed_up', 'backup', null, "Cadangan otomatis: {$name}");
        $this->info("Cadangan tersimpan: {$backups->directory()}".DIRECTORY_SEPARATOR.$name);

        return self::SUCCESS;
    }
}
