<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\AnswerScale;
use App\Models\ClassificationScheme;
use App\Models\ScoreClassification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Skema klasifikasi skor & preset skala jawaban — keduanya dapat diatur tanpa developer.
 */
class ScaleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('system/scales', [
            'schemes' => ClassificationScheme::query()->with('classifications')->withCount(['classifications'])->orderByDesc('is_default')->orderBy('name')->get()
                ->map(fn (ClassificationScheme $scheme): array => [
                    ...$scheme->only(['id', 'name', 'scale_min', 'scale_max', 'description', 'is_default']),
                    'used_by' => DB::table('instrument_versions')->where('classification_scheme_id', $scheme->id)->count(),
                    'classes' => $scheme->classifications->map(fn (ScoreClassification $class): array => $class->only(['label', 'min_score', 'max_score', 'color', 'description']))->all(),
                ])->all(),
            'scales' => AnswerScale::query()->orderBy('name')->get(['id', 'name', 'question_type', 'options', 'description', 'is_active'])->all(),
        ]);
    }

    public function storeScheme(Request $request): RedirectResponse
    {
        DB::transaction(fn () => $this->saveScheme(new ClassificationScheme, $this->validatedScheme($request)));
        $this->toast('Skema klasifikasi ditambahkan.');

        return back();
    }

    public function updateScheme(Request $request, ClassificationScheme $scheme): RedirectResponse
    {
        DB::transaction(fn () => $this->saveScheme($scheme, $this->validatedScheme($request)));
        $this->toast('Skema klasifikasi diperbarui.');

        return back();
    }

    public function destroyScheme(ClassificationScheme $scheme): RedirectResponse
    {
        if ($scheme->is_default) {
            $this->failWith('Skema bawaan tidak dapat dihapus. Jadikan skema lain sebagai bawaan terlebih dahulu.');
        }

        $this->deleteSafely($scheme, 'Skema klasifikasi');

        return back();
    }

    public function storeScale(Request $request): RedirectResponse
    {
        AnswerScale::query()->create($this->validatedScale($request));
        $this->toast('Preset skala ditambahkan.');

        return back();
    }

    public function updateScale(Request $request, AnswerScale $scale): RedirectResponse
    {
        $scale->update($this->validatedScale($request));
        $this->toast('Preset skala diperbarui.');

        return back();
    }

    public function destroyScale(AnswerScale $scale): RedirectResponse
    {
        $scale->delete();
        $this->toast('Preset skala dihapus. Butir yang sudah memakai opsi ini tidak terpengaruh.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedScheme(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'scale_min' => ['required', 'numeric'],
            'scale_max' => ['required', 'numeric', 'gt:scale_min'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
            'classes' => ['required', 'array', 'min:2'],
            'classes.*.label' => ['required', 'string', 'max:100'],
            'classes.*.min_score' => ['required', 'numeric'],
            'classes.*.max_score' => ['required', 'numeric'],
            'classes.*.color' => ['required', Rule::in(['danger', 'warning', 'neutral', 'info', 'success'])],
            'classes.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $classes = collect($validated['classes'])->sortBy('min_score')->values();

        foreach ($classes as $index => $class) {
            if ($class['max_score'] <= $class['min_score']) {
                throw ValidationException::withMessages(["classes.{$index}.max_score" => "Batas atas \"{$class['label']}\" harus lebih besar dari batas bawah."]);
            }

            if ($index > 0 && abs($class['min_score'] - $classes[$index - 1]['max_score']) > 0.0001) {
                throw ValidationException::withMessages(['classes' => "Rentang \"{$classes[$index - 1]['label']}\" dan \"{$class['label']}\" harus bersambung tanpa celah/tumpang tindih."]);
            }
        }

        if (abs($classes->first()['min_score'] - $validated['scale_min']) > 0.0001 || abs($classes->last()['max_score'] - $validated['scale_max']) > 0.0001) {
            throw ValidationException::withMessages(['classes' => 'Rentang klasifikasi harus mencakup seluruh skala (dari skala min. hingga maks.).']);
        }

        $validated['classes'] = $classes->all();

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveScheme(ClassificationScheme $scheme, array $data): void
    {
        if ($data['is_default'] ?? false) {
            ClassificationScheme::query()->whereKeyNot($scheme->id ?? 0)->update(['is_default' => false]);
        }

        $scheme->fill(collect($data)->except('classes')->all())->save();
        $scheme->classifications()->delete();

        foreach ($data['classes'] as $index => $class) {
            $scheme->classifications()->create([...$class, 'sort_order' => $index + 1]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedScale(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'question_type' => ['required', Rule::in(['likert', 'single_choice', 'multiple_choice', 'yes_no'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.value' => ['required', 'string', 'max:50'],
            'options.*.score' => ['nullable', 'numeric'],
        ]);
    }
}
