<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Backup\DatabaseBackup;
use App\Services\DataReset\DataResetService;
use App\Services\DataReset\Dataset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Kosongkan data satu fitur (khusus Superadmin), didahului cadangan database otomatis.
 */
class DataResetController extends Controller
{
    public function show(string $dataset, DataResetService $resets): JsonResponse
    {
        return response()->json($resets->preview($this->dataset($dataset, $resets)));
    }

    public function destroy(Request $request, string $dataset, DataResetService $resets, DatabaseBackup $backups): RedirectResponse
    {
        $target = $this->dataset($dataset, $resets);

        $confirmation = (string) $request->validate(['confirmation' => ['required', 'string']])['confirmation'];
        if (mb_strtoupper(trim($confirmation)) !== $target->phrase()) {
            throw ValidationException::withMessages(['confirmation' => "Ketik “{$target->phrase()}” persis untuk melanjutkan."]);
        }

        try {
            $backup = $backups->create();
        } catch (Throwable $exception) {
            report($exception);
            $this->failWith('Data tidak dikosongkan karena cadangan database gagal dibuat. '.$exception->getMessage());
        }

        $summary = $resets->empty($target);

        AuditLogger::log('emptied', 'system', null, "Mengosongkan data {$target->label}", null, [
            'backup' => $backup,
            'deleted' => collect($summary['deleted'])->mapWithKeys(fn (array $row): array => [$row['label'] => $row['count']])->all(),
            'files' => $summary['files'],
        ]);

        $this->toast(sprintf(
            'Data %s dikosongkan: %s baris dihapus. Cadangan sebelum pengosongan: %s.',
            $target->label,
            number_format($summary['total'], 0, ',', '.'),
            $backup,
        ));

        return back();
    }

    private function dataset(string $key, DataResetService $resets): Dataset
    {
        $dataset = $resets->find($key);
        abort_if($dataset === null, 404);

        return $dataset;
    }
}
