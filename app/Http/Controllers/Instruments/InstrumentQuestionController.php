<?php

namespace App\Http\Controllers\Instruments;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentSection;
use App\Models\InstrumentVersion;
use App\Services\InstrumentVersioning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InstrumentQuestionController extends Controller
{
    public function store(Request $request, InstrumentSection $section, InstrumentVersioning $versioning): RedirectResponse
    {
        $version = $section->version;
        $this->ensureEditable($version);

        $validated = $this->validated($request, $version);
        $options = $validated['options'] ?? [];
        unset($validated['options']);

        $type = QuestionType::from($validated['type']);

        if ($type->hasOptions() && $options === []) {
            $options = $versioning->defaultOptions($type, $version->scale_max);
        }

        DB::transaction(function () use ($section, $version, $validated, $options, $versioning): void {
            $question = $section->questions()->create([
                ...$validated,
                'code' => ($validated['code'] ?? null) ?: $versioning->nextQuestionCode($section),
                'instrument_version_id' => $version->id,
                'sort_order' => (int) $section->questions()->max('sort_order') + 1,
            ]);

            $this->syncOptions($question, $options);
        });

        $this->toast('Pertanyaan ditambahkan.');

        return back();
    }

    public function update(Request $request, InstrumentQuestion $question): RedirectResponse
    {
        $version = $question->version;
        $this->ensureEditable($version);

        $validated = $this->validated($request, $version);
        $options = $validated['options'] ?? [];
        unset($validated['options']);

        DB::transaction(function () use ($question, $validated, $options): void {
            if ((int) $validated['instrument_section_id'] !== $question->instrument_section_id) {
                $validated['sort_order'] = (int) InstrumentQuestion::query()
                    ->where('instrument_section_id', $validated['instrument_section_id'])->max('sort_order') + 1;
            }

            $validated['code'] = ($validated['code'] ?? null) ?: $question->code;
            $question->update($validated);
            $this->syncOptions($question, QuestionType::from($validated['type'])->hasOptions() ? $options : []);
        });

        $this->toast('Pertanyaan diperbarui.');

        return back();
    }

    public function duplicate(InstrumentQuestion $question, InstrumentVersioning $versioning): RedirectResponse
    {
        $this->ensureEditable($question->version);

        DB::transaction(function () use ($question, $versioning): void {
            $copy = $question->replicate();
            $copy->code = $versioning->nextQuestionCode($question->section);
            $copy->sort_order = (int) $question->section->questions()->max('sort_order') + 1;
            $copy->save();

            $this->syncOptions($copy, $question->options->map->only(['label', 'value', 'score'])->all());
        });

        $this->toast('Pertanyaan diduplikasi.');

        return back();
    }

    public function destroy(InstrumentQuestion $question): RedirectResponse
    {
        $this->ensureEditable($question->version);

        $question->delete();
        $this->toast('Pertanyaan dihapus.');

        return back();
    }

    public function move(Request $request, InstrumentQuestion $question): RedirectResponse
    {
        $this->ensureEditable($question->version);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        $questions = $question->section->questions()->get()->values();
        $index = $questions->search(fn (InstrumentQuestion $item) => $item->id === $question->id);
        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex >= 0 && $swapIndex < $questions->count()) {
            $ordered = $questions->all();
            [$ordered[$index], $ordered[$swapIndex]] = [$ordered[$swapIndex], $ordered[$index]];

            DB::transaction(function () use ($ordered): void {
                foreach (array_values($ordered) as $position => $item) {
                    $item->updateQuietly(['sort_order' => $position + 1]);
                }
            });
        }

        return back();
    }

    /**
     * @param  list<array{label: string, value?: string|null, score?: float|int|string|null}>  $options
     */
    private function syncOptions(InstrumentQuestion $question, array $options): void
    {
        $question->options()->delete();

        foreach (array_values($options) as $index => $option) {
            $question->options()->create([
                'label' => $option['label'],
                'value' => ($option['value'] ?? '') !== '' ? $option['value'] : (string) ($index + 1),
                'score' => isset($option['score']) && $option['score'] !== '' ? (float) $option['score'] : null,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, InstrumentVersion $version): array
    {
        $validated = $request->validate([
            'instrument_section_id' => ['required', Rule::exists('instrument_sections', 'id')->where('instrument_version_id', $version->id)],
            'code' => ['nullable', 'string', 'max:20'],
            'label' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(QuestionType::class)],
            'category' => ['nullable', 'string', 'max:255'],
            'indicator' => ['nullable', 'string', 'max:255'],
            'is_required' => ['boolean'],
            'is_scored' => ['boolean'],
            'weight' => ['required', 'numeric', 'min:0', 'max:1000'],
            'min_score' => ['nullable', 'numeric'],
            'max_score' => ['nullable', 'numeric', 'gte:min_score'],
            'requires_evidence' => ['boolean'],
            'visible_to_evaluatee' => ['boolean'],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'settings.max_rating' => ['nullable', 'integer', 'between:3,10'],
            'settings.min' => ['nullable', 'numeric'],
            'settings.max' => ['nullable', 'numeric'],
            'settings.max_length' => ['nullable', 'integer', 'min:1'],
            'settings.accept' => ['nullable', 'string', 'max:255'],
            'options' => ['array'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.value' => ['nullable', 'string', 'max:50'],
            'options.*.score' => ['nullable', 'numeric'],
        ], [
            'options.*.label.required' => 'Label opsi wajib diisi.',
        ]);

        $type = QuestionType::from($validated['type']);

        if (! $type->isScorable()) {
            $validated['is_scored'] = false;
        }

        return $validated;
    }

    private function ensureEditable(InstrumentVersion $version): void
    {
        if (! $version->isEditable()) {
            $this->failWith('Versi ini terkunci. Buat versi baru untuk melakukan perubahan.');
        }
    }
}
