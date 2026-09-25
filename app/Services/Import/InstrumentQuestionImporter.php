<?php

namespace App\Services\Import;

use App\Enums\QuestionType;
use App\Models\AnswerScale;
use App\Models\InstrumentVersion;
use App\Services\InstrumentVersioning;

/**
 * Impor butir ke versi instrumen berstatus draf (options.instrument_version_id).
 */
class InstrumentQuestionImporter extends Importer
{
    public function __construct(private InstrumentVersioning $versioning) {}

    public function type(): string
    {
        return 'butir_instrumen';
    }

    public function label(): string
    {
        return 'Butir Instrumen';
    }

    public function description(): string
    {
        return 'Susun bagian & butir pertanyaan secara massal ke versi instrumen berstatus draf.';
    }

    public function columns(): array
    {
        return [
            ['key' => 'kode_bagian', 'label' => 'Kode Bagian', 'required' => true, 'example' => 'A', 'note' => 'Bagian dibuat otomatis bila belum ada.'],
            ['key' => 'judul_bagian', 'label' => 'Judul Bagian', 'required' => true, 'example' => 'Perencanaan Pembelajaran', 'note' => ''],
            ['key' => 'kode_butir', 'label' => 'Kode Butir', 'required' => true, 'example' => 'A1', 'note' => 'Unik dalam versi.'],
            ['key' => 'pertanyaan', 'label' => 'Pertanyaan', 'required' => true, 'example' => 'RPS disampaikan di awal perkuliahan.', 'note' => ''],
            ['key' => 'tipe', 'label' => 'Tipe', 'required' => true, 'example' => 'likert', 'note' => implode(', ', array_column(QuestionType::cases(), 'value'))],
            ['key' => 'skala', 'label' => 'Preset Skala', 'required' => false, 'example' => 'Likert 4 — Kualitas', 'note' => 'Nama preset di menu Klasifikasi & Skala (untuk tipe berpilihan).'],
            ['key' => 'bobot', 'label' => 'Bobot', 'required' => false, 'example' => '1', 'note' => 'Default 1.'],
            ['key' => 'wajib', 'label' => 'Wajib', 'required' => false, 'example' => 'ya', 'note' => 'ya/tidak. Default ya.'],
            ['key' => 'indikator', 'label' => 'Indikator', 'required' => false, 'example' => 'Ketersediaan RPS', 'note' => ''],
        ];
    }

    public function key(array $row): ?string
    {
        return $row['kode_butir'] ?? null;
    }

    public function exists(string $key, ImportContext $context): bool
    {
        return $this->version($context)?->questions()->where('code', strtoupper($key))->exists() ?? false;
    }

    public function validate(array $row, ImportContext $context): array
    {
        $version = $this->version($context);

        if (! $version || ! $version->isEditable()) {
            return ['kode_butir' => 'Versi instrumen tujuan tidak ditemukan atau sudah terkunci'];
        }

        $errors = $this->requireColumns($row, ['kode_bagian', 'judul_bagian', 'kode_butir', 'pertanyaan', 'tipe']);

        if (! empty($row['tipe']) && ! QuestionType::tryFrom(strtolower((string) $row['tipe']))) {
            $errors['tipe'] = 'Tipe tidak dikenal';
        }
        if (! empty($row['skala']) && ! AnswerScale::query()->where('name', $row['skala'])->exists()) {
            $errors['skala'] = 'Preset skala tidak ditemukan';
        }
        if (! empty($row['bobot']) && (! is_numeric($row['bobot']) || (float) $row['bobot'] < 0)) {
            $errors['bobot'] = 'Bobot harus angka ≥ 0';
        }

        return $errors;
    }

    public function persist(array $row, ImportContext $context, bool $updateExisting): string
    {
        $version = $this->version($context);
        $type = QuestionType::from(strtolower((string) $row['tipe']));
        $section = $version->sections()->firstOrCreate(
            ['code' => strtoupper($row['kode_bagian'])],
            ['title' => $row['judul_bagian'], 'sort_order' => (int) $version->sections()->max('sort_order') + 1],
        );

        $existing = $version->questions()->where('code', strtoupper($row['kode_butir']))->first();

        if ($existing && ! $updateExisting) {
            return 'skipped';
        }

        $attributes = [
            'instrument_version_id' => $version->id,
            'instrument_section_id' => $section->id,
            'label' => $row['pertanyaan'],
            'type' => $type,
            'weight' => (float) ($row['bobot'] ?? 1),
            'is_required' => ! in_array(strtolower((string) ($row['wajib'] ?? 'ya')), ['tidak', 'no', '0'], true),
            'is_scored' => $type->isScorable(),
            'indicator' => $row['indikator'] ?? null,
        ];

        $question = $existing
            ? tap($existing)->update($attributes)
            : $section->questions()->create([...$attributes, 'code' => strtoupper($row['kode_butir']), 'sort_order' => (int) $section->questions()->max('sort_order') + 1]);

        if ($type->hasOptions()) {
            $options = ! empty($row['skala'])
                ? AnswerScale::query()->where('name', $row['skala'])->value('options')
                : $this->versioning->defaultOptions($type, $version->scale_max);

            $question->options()->delete();
            $question->options()->createMany(collect($options)->values()->map(fn (array $o, int $i): array => [...$o, 'sort_order' => $i + 1])->all());
        }

        return $existing ? 'updated' : 'created';
    }

    private function version(ImportContext $context): ?InstrumentVersion
    {
        $id = $context->options['instrument_version_id'] ?? null;

        return $id ? InstrumentVersion::query()->find($id) : null;
    }
}
