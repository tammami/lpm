<?php

namespace App\Services\Accreditation;

use App\Enums\AccreditationVersionStatus;
use App\Models\AccreditationCriterion;
use App\Models\AccreditationInstrument;
use App\Models\AccreditationInstrumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Spreadsheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Instrumen akreditasi LAM: versi, pohon kriteria → indikator, impor/ekspor, dan transisi status.
 * Versi yang berlaku terkunci; perubahan dilakukan pada versi draf baru hasil salinan.
 */
class AccreditationInstruments
{
    public const IMPORT_HEADERS = ['kode_kriteria', 'kriteria', 'bobot_kriteria', 'induk', 'kode_indikator', 'indikator', 'target', 'bukti', 'bobot', 'esensial'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $user): AccreditationInstrument
    {
        return DB::transaction(function () use ($attributes, $user): AccreditationInstrument {
            $instrument = AccreditationInstrument::query()->create($attributes);
            $instrument->versions()->create([
                'version' => '1.0',
                'status' => AccreditationVersionStatus::Draft,
                'scale_max' => 4,
                'grade_thresholds' => AccreditationInstrumentVersion::DEFAULT_THRESHOLDS,
                'created_by' => $user->id,
            ]);

            return $instrument;
        });
    }

    public function clone(AccreditationInstrumentVersion $source, User $user): AccreditationInstrumentVersion
    {
        return DB::transaction(function () use ($source, $user): AccreditationInstrumentVersion {
            $latest = $source->instrument->versions()->reorder()->orderByDesc('id')->first();
            [$major, $minor] = array_map('intval', explode('.', $latest->version.'.0'));

            $version = $source->instrument->versions()->create([
                'version' => $major.'.'.($minor + 1),
                'status' => AccreditationVersionStatus::Draft,
                'scale_max' => $source->scale_max,
                'grade_thresholds' => $source->grade_thresholds,
                'notes' => "Diturunkan dari versi {$source->version}.",
                'created_by' => $user->id,
            ]);

            $map = [];
            $criteria = $source->criteria()->reorder()->orderByRaw('parent_id is not null')->orderBy('sort_order')->with('indicators')->get();

            foreach ($criteria as $criterion) {
                $copy = $version->criteria()->create([
                    ...$criterion->only(['code', 'title', 'description', 'weight', 'sort_order']),
                    'parent_id' => $criterion->parent_id ? ($map[$criterion->parent_id] ?? null) : null,
                ]);
                $map[$criterion->id] = $copy->id;

                foreach ($criterion->indicators as $indicator) {
                    $copy->indicators()->create([
                        ...$indicator->only(['code', 'statement', 'target', 'evidence_hint', 'weight', 'is_essential', 'sort_order']),
                        'instrument_version_id' => $version->id,
                    ]);
                }
            }

            AuditLogger::log('created', 'accreditation', $version, "Membuat versi {$version->version} instrumen {$source->instrument->code}");

            return $version;
        });
    }

    /**
     * @return list<string>
     */
    public function publishIssues(AccreditationInstrumentVersion $version): array
    {
        $issues = [];
        $roots = $version->criteria()->whereNull('parent_id')->get();

        if ($roots->isEmpty()) {
            $issues[] = 'Belum ada kriteria.';
        }

        if (! $version->indicators()->exists()) {
            $issues[] = 'Belum ada indikator.';
        }

        $empty = $roots->filter(fn (AccreditationCriterion $c): bool => ! $version->indicators()->whereIn('criterion_id', $this->subtreeIds($version, $c))->exists());
        if ($empty->isNotEmpty()) {
            $issues[] = 'Kriteria tanpa indikator: '.$empty->pluck('code')->implode(', ').'.';
        }

        $weights = round((float) $roots->sum('weight'), 2);
        if ($roots->isNotEmpty() && $weights > 0 && abs($weights - 100) > 0.01) {
            $issues[] = "Total bobot kriteria {$weights}% — harus 100% (atau kosongkan seluruh bobot agar dianggap setara).";
        }

        return $issues;
    }

    public function transition(AccreditationInstrumentVersion $version, AccreditationVersionStatus $to): void
    {
        $allowed = match ($version->status) {
            AccreditationVersionStatus::Draft => [AccreditationVersionStatus::Published],
            AccreditationVersionStatus::Published => [AccreditationVersionStatus::Archived],
            AccreditationVersionStatus::Archived => [AccreditationVersionStatus::Published],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Perubahan status tidak diizinkan.']);
        }

        if ($to === AccreditationVersionStatus::Published && $issues = $this->publishIssues($version)) {
            throw ValidationException::withMessages(['status' => implode(' ', $issues)]);
        }

        $version->update(['status' => $to, 'published_at' => $to === AccreditationVersionStatus::Published ? ($version->published_at ?? now()) : $version->published_at]);
    }

    /**
     * Ganti seluruh isi versi draf dari berkas XLSX/CSV (satu baris = satu indikator).
     *
     * @return array{criteria: int, indicators: int}
     */
    public function import(AccreditationInstrumentVersion $version, string $path): array
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages(['file' => 'Hanya versi draf yang dapat diimpor.']);
        }

        $headers = null;
        $rows = [];

        foreach (Spreadsheet::read($path) as $number => $values) {
            if ($headers === null) {
                $headers = array_map(fn ($h) => Spreadsheet::headerKey($h), $values);

                if (! in_array('kode_kriteria', $headers, true)) {
                    throw ValidationException::withMessages(['file' => 'Kolom "kode_kriteria" tidak ditemukan. Gunakan templat impor.']);
                }

                continue;
            }

            $row = [];
            foreach ($headers as $index => $key) {
                $row[$key] = is_string($values[$index] ?? null) ? trim($values[$index]) : ($values[$index] ?? null);
            }

            if (($row['kode_kriteria'] ?? '') === '' && ($row['kode_indikator'] ?? '') === '') {
                continue;
            }

            if (($row['kode_kriteria'] ?? '') === '') {
                throw ValidationException::withMessages(['file' => "Baris {$number}: kode_kriteria wajib diisi."]);
            }

            if (($row['kode_indikator'] ?? '') !== '' && ($row['indikator'] ?? '') === '') {
                throw ValidationException::withMessages(['file' => "Baris {$number}: pernyataan indikator {$row['kode_indikator']} kosong."]);
            }

            $rows[] = $row;
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak berisi data.']);
        }

        return DB::transaction(function () use ($version, $rows): array {
            $version->indicators()->delete();
            $version->criteria()->whereNotNull('parent_id')->delete();
            $version->criteria()->delete();

            $criteria = [];
            $order = 0;
            $indicators = 0;

            foreach ($rows as $row) {
                $code = (string) $row['kode_kriteria'];

                if (! isset($criteria[$code])) {
                    $parent = ($row['induk'] ?? '') !== '' ? ($criteria[(string) $row['induk']] ?? null) : null;
                    $criteria[$code] = $version->criteria()->create([
                        'code' => $code,
                        'title' => ($row['kriteria'] ?? '') !== '' ? $row['kriteria'] : $code,
                        'weight' => (float) ($row['bobot_kriteria'] ?? 0),
                        'parent_id' => $parent?->id,
                        'sort_order' => ++$order,
                    ]);
                }

                if (($row['kode_indikator'] ?? '') !== '') {
                    $criteria[$code]->indicators()->create([
                        'instrument_version_id' => $version->id,
                        'code' => (string) $row['kode_indikator'],
                        'statement' => $row['indikator'],
                        'target' => $row['target'] ?? null ?: null,
                        'evidence_hint' => $row['bukti'] ?? null ?: null,
                        'weight' => (float) (($row['bobot'] ?? '') !== '' ? $row['bobot'] : 1),
                        'is_essential' => in_array(strtolower((string) ($row['esensial'] ?? '')), ['1', 'ya', 'y', 'true', 'yes'], true),
                        'sort_order' => ++$indicators,
                    ]);
                }
            }

            return ['criteria' => count($criteria), 'indicators' => $indicators];
        });
    }

    /**
     * @return list<list<mixed>>
     */
    public function exportRows(AccreditationInstrumentVersion $version): array
    {
        $criteria = $version->criteria()->with(['indicators', 'parent:id,code'])->get();
        $rows = [];

        foreach ($criteria as $criterion) {
            $base = [$criterion->code, $criterion->title, $criterion->parent_id ? null : $criterion->weight, $criterion->parent?->code];

            if ($criterion->indicators->isEmpty()) {
                $rows[] = [...$base, null, null, null, null, null, null];
            }

            foreach ($criterion->indicators as $indicator) {
                $rows[] = [...$base, $indicator->code, $indicator->statement, $indicator->target, $indicator->evidence_hint, $indicator->weight, $indicator->is_essential ? 'ya' : null];
            }
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    public function subtreeIds(AccreditationInstrumentVersion $version, AccreditationCriterion $root): array
    {
        $children = $version->criteria()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [];
        $stack = [$root->id];

        while ($stack) {
            $id = array_pop($stack);
            $ids[] = $id;

            foreach ($children[$id] ?? [] as $child) {
                $stack[] = $child->id;
            }
        }

        return $ids;
    }
}
