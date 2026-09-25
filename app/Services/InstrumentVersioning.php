<?php

namespace App\Services;

use App\Enums\InstrumentVersionStatus;
use App\Enums\QuestionType;
use App\Enums\ScoringMethod;
use App\Models\ClassificationScheme;
use App\Models\Instrument;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentSection;
use App\Models\InstrumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus hidup instrumen: pembuatan, versi baru (salin penuh), validasi & transisi status.
 */
class InstrumentVersioning
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createInstrument(array $attributes, User $user): Instrument
    {
        return DB::transaction(function () use ($attributes, $user): Instrument {
            $instrument = Instrument::query()->create([...$attributes, 'created_by' => $user->id]);

            $version = $instrument->versions()->create([
                'version' => '1.0',
                'version_number' => 1,
                'status' => InstrumentVersionStatus::Draft,
                'scoring_method' => ScoringMethod::Average,
                'scale_min' => 1,
                'scale_max' => 4,
                'classification_scheme_id' => ClassificationScheme::default()?->id,
                'changelog' => 'Versi awal.',
            ]);

            $version->sections()->create(['code' => 'A', 'title' => 'Bagian A', 'sort_order' => 1]);

            return $instrument;
        });
    }

    /**
     * Salin seluruh isi versi menjadi versi draf baru. Versi sumber tidak berubah.
     */
    public function cloneVersion(InstrumentVersion $source, User $user, bool $major = false, ?string $changelog = null): InstrumentVersion
    {
        return DB::transaction(function () use ($source, $major, $changelog): InstrumentVersion {
            $instrument = $source->instrument;
            $latest = $instrument->versions()->reorder()->orderByDesc('version_number')->first();
            [$majorPart, $minorPart] = array_map('intval', explode('.', $latest->version.'.0'));

            $version = $instrument->versions()->create([
                'parent_version_id' => $source->id,
                'classification_scheme_id' => $source->classification_scheme_id,
                'version' => $major ? ($majorPart + 1).'.0' : $majorPart.'.'.($minorPart + 1),
                'version_number' => $latest->version_number + 1,
                'status' => InstrumentVersionStatus::Draft,
                'scoring_method' => $source->scoring_method,
                'scale_min' => $source->scale_min,
                'scale_max' => $source->scale_max,
                'changelog' => $changelog ?? "Diturunkan dari versi {$source->version}.",
            ]);

            $source->load('sections.questions.options');

            foreach ($source->sections as $section) {
                $newSection = $version->sections()->create($section->only(['code', 'title', 'description', 'sort_order']));

                foreach ($section->questions as $question) {
                    $newQuestion = $newSection->questions()->create([
                        ...$question->only([
                            'code', 'label', 'description', 'type', 'category', 'indicator', 'is_required', 'is_scored',
                            'weight', 'min_score', 'max_score', 'requires_evidence', 'visible_to_evaluatee', 'is_active',
                            'settings', 'sort_order',
                        ]),
                        'instrument_version_id' => $version->id,
                    ]);

                    $newQuestion->options()->createMany(
                        $question->options->map(fn ($option): array => $option->only(['label', 'value', 'score', 'sort_order']))->all(),
                    );
                }
            }

            return $version;
        });
    }

    /**
     * Daftar masalah yang menghalangi penerbitan versi.
     *
     * @return list<string>
     */
    public function publishIssues(InstrumentVersion $version): array
    {
        $version->loadMissing('sections.questions.options');
        $issues = [];

        if ($version->sections->isEmpty()) {
            $issues[] = 'Instrumen belum memiliki bagian.';
        }

        $questions = $version->sections->flatMap->questions;

        if ($questions->where('is_active', true)->isEmpty()) {
            $issues[] = 'Instrumen belum memiliki pertanyaan aktif.';
        }

        foreach ($version->sections as $section) {
            if ($section->questions->isEmpty()) {
                $issues[] = "Bagian {$section->code} ({$section->title}) masih kosong.";
            }
        }

        foreach ($questions as $question) {
            /** @var InstrumentQuestion $question */
            if ($question->type->hasOptions() && $question->options->count() < 2) {
                $issues[] = "Butir {$question->code} membutuhkan minimal 2 opsi jawaban.";
            }

            if ($question->type->hasOptions() && $question->is_scored && $question->options->whereNotNull('score')->isEmpty()) {
                $issues[] = "Butir {$question->code} dihitung dalam skor tetapi opsinya belum memiliki nilai.";
            }

            if ($question->is_scored && $question->weight <= 0) {
                $issues[] = "Bobot butir {$question->code} harus lebih dari 0.";
            }
        }

        $duplicates = $questions->groupBy('code')->filter(fn ($group) => $group->count() > 1)->keys();

        foreach ($duplicates as $code) {
            $issues[] = "Kode butir {$code} digunakan lebih dari sekali.";
        }

        return $issues;
    }

    /**
     * @throws ValidationException
     */
    public function transition(InstrumentVersion $version, InstrumentVersionStatus $target, User $user, ?string $notes = null): void
    {
        if (! $version->status->canTransitionTo($target)) {
            throw ValidationException::withMessages([
                'status' => "Status {$version->status->label()} tidak dapat diubah menjadi {$target->label()}.",
            ]);
        }

        if (in_array($target, [InstrumentVersionStatus::Review, InstrumentVersionStatus::Approved, InstrumentVersionStatus::Published], true)) {
            $issues = $this->publishIssues($version);

            if ($issues !== []) {
                throw ValidationException::withMessages(['status' => $issues[0].(count($issues) > 1 ? ' (dan '.(count($issues) - 1).' masalah lain)' : '')]);
            }
        }

        $previous = $version->status;

        $updates = match ($target) {
            InstrumentVersionStatus::Review => ['submitted_by' => $user->id, 'submitted_at' => now(), 'review_notes' => $notes],
            InstrumentVersionStatus::Approved => ['approved_by' => $user->id, 'approved_at' => now(), 'review_notes' => $notes ?? $version->review_notes],
            InstrumentVersionStatus::Draft => ['review_notes' => $notes ?? $version->review_notes],
            InstrumentVersionStatus::Published => ['published_by' => $user->id, 'published_at' => now()],
            InstrumentVersionStatus::Archived => ['archived_at' => now()],
        };

        $version->update([...$updates, 'status' => $target]);

        $event = match ($target) {
            InstrumentVersionStatus::Approved => 'approved',
            InstrumentVersionStatus::Published => 'published',
            default => 'status_changed',
        };

        AuditLogger::log(
            $event,
            'instrument',
            $version,
            "Versi {$version->version} {$version->instrument->name}: {$previous->label()} → {$target->label()}",
            ['status' => $previous->value],
            ['status' => $target->value, 'notes' => $notes],
        );
    }

    public function nextQuestionCode(InstrumentSection $section): string
    {
        $count = $section->questions()->count();

        return $section->code.($count + 1);
    }

    /**
     * Opsi bawaan untuk tipe pertanyaan tertentu (dipakai saat opsi belum diisi).
     *
     * @return list<array{label: string, value: string, score: float|null}>
     */
    public function defaultOptions(QuestionType $type, float $scaleMax = 4): array
    {
        return match ($type) {
            QuestionType::YesNo => [
                ['label' => 'Ya', 'value' => 'yes', 'score' => $scaleMax],
                ['label' => 'Tidak', 'value' => 'no', 'score' => 0],
            ],
            QuestionType::Likert => collect(['Kurang', 'Cukup', 'Baik', 'Sangat Baik'])
                ->map(fn (string $label, int $index): array => ['label' => $label, 'value' => (string) ($index + 1), 'score' => (float) ($index + 1)])
                ->all(),
            default => [],
        };
    }
}
