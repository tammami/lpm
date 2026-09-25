<?php

namespace App\Http\Controllers\Ami;

use App\Http\Controllers\Controller;
use App\Models\FindingSeverity;
use App\Models\QualityStandard;
use App\Models\RootCauseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Referensi AMI yang dapat dikonfigurasi LPM: standar SPMI, kategori temuan, kategori akar masalah.
 */
class ReferenceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ami/references', [
            'standards' => QualityStandard::query()->withCount('findings')->orderBy('sort_order')->orderBy('code')->get()
                ->map(fn (QualityStandard $standard): array => [
                    ...$standard->only(['id', 'code', 'name', 'category', 'statement', 'indicator', 'target', 'reference', 'is_active', 'sort_order']),
                    'findings_count' => $standard->findings_count,
                ])->all(),
            'categories' => collect(QualityStandard::CATEGORIES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            'severities' => FindingSeverity::query()->orderBy('sort_order')->get()->all(),
            'rootCauses' => RootCauseCategory::query()->orderBy('sort_order')->get()->all(),
        ]);
    }

    public function storeStandard(Request $request): RedirectResponse
    {
        QualityStandard::query()->create($this->validatedStandard($request));
        $this->toast('Standar mutu ditambahkan.');

        return back();
    }

    public function updateStandard(Request $request, QualityStandard $standard): RedirectResponse
    {
        $standard->update($this->validatedStandard($request, $standard));
        $this->toast('Standar mutu diperbarui.');

        return back();
    }

    public function destroyStandard(QualityStandard $standard): RedirectResponse
    {
        $this->deleteSafely($standard, 'Standar mutu');

        return back();
    }

    public function saveSeverity(Request $request, ?FindingSeverity $severity = null): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('finding_severities')->ignore($severity)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', Rule::in(['danger', 'warning', 'info', 'neutral', 'success'])],
            'requires_corrective_action' => ['boolean'],
            'default_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $severity ? $severity->update($validated) : FindingSeverity::query()->create($validated);
        $this->toast('Kategori temuan disimpan.');

        return back();
    }

    public function saveRootCause(Request $request, ?RootCauseCategory $rootCause = null): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $rootCause ? $rootCause->update($validated) : RootCauseCategory::query()->create($validated);
        $this->toast('Kategori akar masalah disimpan.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedStandard(Request $request, ?QualityStandard $standard = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('quality_standards')->ignore($standard)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(QualityStandard::CATEGORIES))],
            'statement' => ['nullable', 'string', 'max:3000'],
            'indicator' => ['nullable', 'string', 'max:3000'],
            'target' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
