<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('backup:restore {file : Nama file cadangan di folder cadangan} {--force : Lewati konfirmasi}')]
#[Description('Pulihkan database dari file cadangan (kondisi saat ini dicadangkan lebih dulu)')]
class RestoreDatabase extends Command
{
    public function handle(DatabaseBackup $backups): int
    {
        $file = (string) $this->argument('file');
        $database = config('database.connections.'.config('database.default').'.database');

        if ($backups->path($file) === null) {
            $this->error("File cadangan tidak ditemukan di {$backups->directory()}. Pastikan nama file tidak diubah.");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Seluruh data pada database {$database} akan diganti dengan isi {$file}. Lanjutkan?")) {
            return self::FAILURE;
        }

        try {
            $safety = $backups->restore($file);
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        rescue(fn () => AuditLogger::log('restored', 'backup', null, "Memulihkan database dari {$file} lewat terminal. Kondisi sebelumnya: {$safety}"));
        $this->info("Database {$database} dipulihkan dari {$file}.");
        $this->info("Kondisi sebelum pemulihan: {$safety}");

        return self::SUCCESS;
    }
}
