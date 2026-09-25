<?php

namespace App\Http\Controllers\System;

use App\Enums\InstrumentVersionStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessImport;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Models\InstrumentVersion;
use App\Services\Import\Importer;
use App\Services\Import\ImportManager;
use App\Support\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    private const SYNC_LIMIT = 1500;

    public function index(Request $request, ImportManager $manager): Response
    {
        $user = $request->user();

        $jobs = ImportJob::query()
            ->with('user:id,name')
            ->when(! $user->isInstitutionWide(), fn ($q) => $q->where('user_id', $user->id))
            ->latest()
            ->paginate(10)
            ->through(fn (ImportJob $job): array => $this->jobPayload($job, $manager));

        return Inertia::render('imports/index', [
            'importers' => collect($manager->importers())->map(fn (Importer $importer): array => [
                'type' => $importer->type(),
                'label' => $importer->label(),
                'description' => $importer->description(),
                'columns' => $importer->columns(),
            ])->values()->all(),
            'jobs' => $jobs,
            'draftVersions' => InstrumentVersion::query()->where('status', InstrumentVersionStatus::Draft)->with('instrument:id,name')->get()
                ->map(fn (InstrumentVersion $version): array => ['value' => $version->id, 'label' => "{$version->instrument->name} — v{$version->version} (draf)"])->all(),
        ]);
    }

    public function template(string $type, ImportManager $manager): StreamedResponse
    {
        $importer = $manager->importer($type);
        $columns = $importer->columns();

        return Spreadsheet::download(
            $importer->templateFilename(),
            array_map(fn (array $column): string => $column['label'].($column['required'] ? '*' : ''), $columns),
            [array_column($columns, 'example')],
            array_map(fn (array $column): float => max(14, mb_strlen($column['label']) + 6), $columns),
            $importer->label(),
            collect($columns)->mapWithKeys(fn (array $column): array => [
                $column['label'].($column['required'] ? ' (wajib)' : '') => $column['note'] ?: '—',
            ])->all(),
        );
    }

    public function store(Request $request, ImportManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys($manager->importers()))],
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
            'instrument_version_id' => ['nullable', 'required_if:type,butir_instrumen', Rule::exists('instrument_versions', 'id')->where('status', 'draft')],
        ], ['file.mimes' => 'Gunakan file Excel (.xlsx) atau CSV.', 'instrument_version_id.required_if' => 'Pilih versi instrumen tujuan (berstatus draf).']);

        $job = $manager->upload($request->file('file'), $validated['type'], $request->user(), array_filter([
            'instrument_version_id' => $validated['instrument_version_id'] ?? null,
        ]));

        return redirect()->route('imports.show', $job);
    }

    public function show(Request $request, ImportJob $import, ImportManager $manager): Response
    {
        $this->authorizeJob($request, $import);
        $importer = $manager->importer($import->type);

        return Inertia::render('imports/show', [
            'job' => $this->jobPayload($import->load('user:id,name'), $manager),
            'columns' => $importer->columns(),
            'preview' => $import->preview,
            'errors' => $import->errors()->limit(300)->get()->map(fn (ImportError $error): array => $error->only(['row_number', 'column', 'value', 'message']))->all(),
            'errorCount' => $import->errors()->count(),
        ]);
    }

    public function confirm(Request $request, ImportJob $import): RedirectResponse
    {
        $this->authorizeJob($request, $import);

        if ($import->status !== 'validated' || $import->valid_rows === 0) {
            $this->failWith('Impor ini tidak dapat dijalankan. Periksa kembali hasil validasi.');
        }

        $import->update(['options' => [...($import->options ?? []), 'update_existing' => $request->boolean('update_existing', true)]]);

        if ($import->total_rows <= self::SYNC_LIMIT) {
            ProcessImport::dispatchSync($import);
            $import->refresh();
            $this->toast($import->status === 'completed' ? "{$import->imported_rows} baris berhasil diimpor." : 'Impor gagal. Lihat keterangan.', $import->status === 'completed' ? 'success' : 'error');
        } else {
            $import->update(['status' => 'queued']);
            ProcessImport::dispatch($import);
            $this->toast('File besar sedang diproses di latar belakang. Halaman ini akan diperbarui otomatis.', 'info');
        }

        return redirect()->route('imports.show', $import);
    }

    public function errors(Request $request, ImportJob $import): StreamedResponse
    {
        $this->authorizeJob($request, $import);

        return Spreadsheet::download(
            "error_impor_{$import->id}.xlsx",
            ['Baris', 'Kolom', 'Nilai', 'Keterangan'],
            $import->errors()->cursor()->map(fn (ImportError $error): array => [$error->row_number, $error->column, $error->value, $error->message]),
            [10, 20, 30, 60],
            'Error',
        );
    }

    public function destroy(Request $request, ImportJob $import): RedirectResponse
    {
        $this->authorizeJob($request, $import);

        if (in_array($import->status, ['importing', 'queued'], true)) {
            $this->failWith('Impor sedang berjalan.');
        }

        Storage::disk('local')->delete($import->file_path);
        $import->delete();
        $this->toast('Riwayat impor dihapus.');

        return redirect()->route('imports.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function jobPayload(ImportJob $job, ImportManager $manager): array
    {
        return [
            ...$job->only(['id', 'type', 'original_name', 'status', 'total_rows', 'valid_rows', 'invalid_rows', 'duplicate_rows', 'imported_rows', 'failure_message']),
            'type_label' => $manager->importer($job->type)->label(),
            'user' => $job->user?->name,
            'result' => $job->options['result'] ?? null,
            'update_existing' => $job->options['update_existing'] ?? true,
            'created_at' => $job->created_at?->toIso8601String(),
            'completed_at' => $job->completed_at?->toIso8601String(),
        ];
    }

    private function authorizeJob(Request $request, ImportJob $job): void
    {
        abort_unless($job->user_id === $request->user()->id || $request->user()->isInstitutionWide(), 403);
    }
}
