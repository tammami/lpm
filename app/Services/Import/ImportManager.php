<?php

namespace App\Services\Import;

use App\Models\ImportJob;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Spreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Alur impor: unggah → parse → validasi → pratinjau → konfirmasi → impor → ringkasan.
 */
class ImportManager
{
    /**
     * @var array<string, class-string<Importer>>
     */
    private const IMPORTERS = [
        'mahasiswa' => StudentImporter::class,
        'dosen' => LecturerImporter::class,
        'mata_kuliah' => CourseImporter::class,
        'penugasan_mengajar' => TeachingAssignmentImporter::class,
        'peserta_kelas' => EnrollmentImporter::class,
        'butir_instrumen' => InstrumentQuestionImporter::class,
    ];

    private const PREVIEW_ROWS = 25;

    /**
     * @return array<string, Importer>
     */
    public function importers(): array
    {
        return array_map(fn (string $class): Importer => app($class), self::IMPORTERS);
    }

    public function importer(string $type): Importer
    {
        abort_unless(isset(self::IMPORTERS[$type]), 404, 'Jenis impor tidak dikenal.');

        return app(self::IMPORTERS[$type]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function upload(UploadedFile $file, string $type, User $user, array $options = []): ImportJob
    {
        $job = ImportJob::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'file_path' => $file->storeAs('imports', now()->format('Ymd_His').'_'.$user->id.'.'.$file->getClientOriginalExtension(), 'local'),
            'original_name' => $file->getClientOriginalName(),
            'status' => 'uploaded',
            'options' => $options,
        ]);

        $this->analyze($job);

        return $job->refresh();
    }

    /**
     * Parse & validasi seluruh baris, simpan ringkasan, error, dan pratinjau.
     */
    public function analyze(ImportJob $job): void
    {
        $importer = $this->importer($job->type);
        $context = new ImportContext($job->user, $job->options ?? []);
        $job->errors()->delete();

        try {
            [$header, $rows] = $this->mappedRows($job, $importer);
        } catch (Throwable $exception) {
            $job->update(['status' => 'failed', 'failure_message' => $exception->getMessage()]);

            return;
        }

        $missing = collect($importer->columns())->where('required', true)->pluck('key')->diff($header)->values();

        if ($missing->isNotEmpty()) {
            $labels = collect($importer->columns())->whereIn('key', $missing)->pluck('label')->implode(', ');
            $job->update(['status' => 'failed', 'failure_message' => "Kolom wajib tidak ditemukan: {$labels}. Gunakan template yang disediakan."]);

            return;
        }

        $counts = ['total' => 0, 'valid' => 0, 'invalid' => 0, 'duplicate' => 0];
        $seen = [];
        $errors = [];
        $preview = [];

        foreach ($rows as $number => $row) {
            $counts['total']++;
            $rowErrors = $importer->validate($row, $context);
            $key = $importer->key($row);
            $status = 'new';

            if ($rowErrors === [] && $key !== null && isset($seen[$key])) {
                $rowErrors['_row'] = "Duplikat dengan baris {$seen[$key]} pada file";
            }

            if ($rowErrors !== []) {
                $counts['invalid']++;
                $status = 'invalid';

                foreach ($rowErrors as $column => $message) {
                    $errors[] = [
                        'import_job_id' => $job->id,
                        'row_number' => $number,
                        'column' => $column === '_row' ? null : $column,
                        'value' => $column === '_row' ? null : mb_substr((string) ($row[$column] ?? ''), 0, 250),
                        'message' => $message,
                        'severity' => 'error',
                    ];
                }
            } else {
                $counts['valid']++;

                if ($key !== null && $importer->exists($key, $context)) {
                    $counts['duplicate']++;
                    $status = 'existing';
                }
            }

            if ($key !== null) {
                $seen[$key] ??= $number;
            }

            if (count($preview) < self::PREVIEW_ROWS) {
                $preview[] = ['row' => $number, 'status' => $status, 'values' => $row];
            }
        }

        foreach (array_chunk($errors, 500) as $chunk) {
            DB::table('import_errors')->insert($chunk);
        }

        $job->update([
            'status' => 'validated',
            'total_rows' => $counts['total'],
            'valid_rows' => $counts['valid'],
            'invalid_rows' => $counts['invalid'],
            'duplicate_rows' => $counts['duplicate'],
            'preview' => ['columns' => $header, 'rows' => $preview],
            'validated_at' => now(),
            'failure_message' => $counts['total'] === 0 ? 'File tidak berisi baris data.' : null,
        ]);
    }

    /**
     * Impor baris valid. Baris tidak valid dilewati.
     */
    public function run(ImportJob $job): void
    {
        $importer = $this->importer($job->type);
        $context = new ImportContext($job->user, $job->options ?? []);
        $update = (bool) ($job->options['update_existing'] ?? true);
        $imported = 0;
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $job->update(['status' => 'importing']);

        try {
            [, $rows] = $this->mappedRows($job, $importer);
            $invalid = $job->errors()->pluck('row_number')->flip();

            DB::transaction(function () use ($rows, $invalid, $importer, $context, $update, &$imported, &$result, $job): void {
                foreach ($rows as $number => $row) {
                    if (isset($invalid[$number])) {
                        continue;
                    }

                    $outcome = $importer->persist($row, $context, $update);
                    $result[$outcome]++;

                    if ($outcome !== 'skipped') {
                        $imported++;
                    }

                    if ($imported % 200 === 0) {
                        $job->updateQuietly(['imported_rows' => $imported]);
                    }
                }
            });

            $job->update([
                'status' => 'completed',
                'imported_rows' => $imported,
                'completed_at' => now(),
                'options' => [...($job->options ?? []), 'result' => $result],
            ]);

            AuditLogger::log('imported', 'import', $job, "Impor {$importer->label()}: {$result['created']} baru, {$result['updated']} diperbarui, {$result['skipped']} dilewati", userId: $job->user_id);
        } catch (Throwable $exception) {
            report($exception);
            $job->update(['status' => 'failed', 'failure_message' => 'Impor gagal dan dibatalkan seluruhnya: '.$exception->getMessage()]);
        }
    }

    /**
     * @return array{0: list<string>, 1: \Generator<int, array<string, mixed>>}
     */
    private function mappedRows(ImportJob $job, Importer $importer): array
    {
        $path = Storage::disk('local')->path($job->file_path);
        $reader = Spreadsheet::read($path);

        $headerRow = $reader->current();
        $labelsToKeys = collect($importer->columns())
            ->flatMap(fn (array $column): array => [Spreadsheet::headerKey($column['label']) => $column['key'], $column['key'] => $column['key']]);
        $header = array_map(fn ($cell): string => $labelsToKeys[Spreadsheet::headerKey($cell)] ?? Spreadsheet::headerKey($cell), $headerRow ?? []);
        $reader->next();

        $rows = (function () use ($reader, $header, $importer): \Generator {
            while ($reader->valid()) {
                $number = $reader->key();
                $values = $reader->current();
                $reader->next();

                if (array_filter($values, fn ($value) => $value !== null && $value !== '') === []) {
                    continue;
                }

                $row = [];
                foreach ($header as $index => $key) {
                    $row[$key] = $values[$index] ?? null;
                }

                yield $number => $importer->normalize($row);
            }
        })();

        return [$header, $rows];
    }
}
