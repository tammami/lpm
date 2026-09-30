<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Backup\DatabaseBackup;
use App\Services\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(Request $request, DatabaseBackup $backups): Response
    {
        return Inertia::render('system/backups', [
            'backups' => array_map(fn (array $backup): array => [
                'name' => $backup['name'],
                'size' => $backup['size'],
                'created_at' => $backup['created_at']->toIso8601String(),
            ], $backups->all()),
            'directory' => $backups->directory(),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'keepFiles' => (int) Settings::get('backup.keep_files', 30),
            'schedule' => 'Setiap hari pukul 01.00',
            'canRestore' => $request->user()->can('backups.restore'),
        ]);
    }

    public function store(DatabaseBackup $backups): RedirectResponse
    {
        try {
            $name = $backups->create();
        } catch (Throwable $exception) {
            report($exception);
            $this->failWith('Cadangan gagal dibuat. '.$exception->getMessage());
        }

        AuditLogger::log('backed_up', 'backup', null, "Cadangan manual: {$name}");
        $this->toast("Cadangan {$name} berhasil dibuat.");

        return back();
    }

    public function download(string $backup, DatabaseBackup $backups): BinaryFileResponse
    {
        $path = $backups->path($backup);
        abort_if($path === null, 404, 'File cadangan tidak ditemukan.');

        AuditLogger::log('downloaded', 'backup', null, "Mengunduh cadangan: {$backup}");

        return response()->download($path, $backup, ['Content-Type' => 'application/gzip']);
    }

    public function restore(Request $request, string $backup, DatabaseBackup $backups): RedirectResponse
    {
        abort_if($backups->path($backup) === null, 404, 'File cadangan tidak ditemukan.');

        $database = config('database.connections.'.config('database.default').'.database');

        $request->validate(
            ['confirmation' => ['required', 'string', Rule::in([$database])]],
            ['confirmation.required' => 'Ketik nama database untuk melanjutkan.', 'confirmation.in' => 'Nama database tidak cocok.'],
        );

        try {
            $safety = $backups->restore($backup);
        } catch (Throwable $exception) {
            report($exception);
            $this->failWith($exception->getMessage());
        }

        // Akun yang sedang masuk bisa saja belum ada di data hasil pemulihan.
        rescue(fn () => AuditLogger::log('restored', 'backup', null, "Memulihkan database dari {$backup}. Kondisi sebelumnya: {$safety}"));
        $this->toast("Database dipulihkan dari {$backup}. Kondisi sebelumnya tersimpan sebagai {$safety}.");

        return back();
    }

    public function destroy(string $backup, DatabaseBackup $backups): RedirectResponse
    {
        abort_unless($backups->delete($backup), 404, 'File cadangan tidak ditemukan.');

        AuditLogger::log('deleted', 'backup', null, "Menghapus cadangan: {$backup}");
        $this->toast('File cadangan dihapus.');

        return back();
    }
}
