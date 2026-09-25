<?php

namespace App\Http\Controllers\Accreditation;

use App\Http\Controllers\Controller;
use App\Models\AccreditationCriterion;
use App\Models\AccreditationIndicator;
use App\Models\AccreditationInstrumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Penyusunan pohon kriteria → sub-kriteria → indikator pada versi draf.
 */
class StructureController extends Controller
{
    public function storeCriterion(Request $request, AccreditationInstrumentVersion $version): RedirectResponse
    {
        $this->ensureEditable($version);
        $validated = $this->criterionRules($request, $version);
        $version->criteria()->create([...$validated, 'sort_order' => (int) $version->criteria()->max('sort_order') + 1]);
        $this->toast('Kriteria ditambahkan.');

        return back();
    }

    public function updateCriterion(Request $request, AccreditationCriterion $criterion): RedirectResponse
    {
        $this->ensureEditable($criterion->version);
        $validated = $this->criterionRules($request, $criterion->version, $criterion);

        if (($validated['parent_id'] ?? null) === $criterion->id) {
            $this->failWith('Kriteria tidak dapat menjadi induk dirinya sendiri.');
        }

        $criterion->update($validated);
        $this->toast('Kriteria diperbarui.');

        return back();
    }

    public function destroyCriterion(AccreditationCriterion $criterion): RedirectResponse
    {
        $this->ensureEditable($criterion->version);
        $criterion->delete();
        $this->toast('Kriteria beserta indikatornya dihapus.');

        return back();
    }

    public function storeIndicator(Request $request, AccreditationCriterion $criterion): RedirectResponse
    {
        $this->ensureEditable($criterion->version);
        $criterion->indicators()->create([
            ...$this->indicatorRules($request),
            'instrument_version_id' => $criterion->instrument_version_id,
            'sort_order' => (int) AccreditationIndicator::query()->where('instrument_version_id', $criterion->instrument_version_id)->max('sort_order') + 1,
        ]);
        $this->toast('Indikator ditambahkan.');

        return back();
    }

    public function updateIndicator(Request $request, AccreditationIndicator $indicator): RedirectResponse
    {
        $this->ensureEditable($indicator->version);
        $indicator->update($this->indicatorRules($request));
        $this->toast('Indikator diperbarui.');

        return back();
    }

    public function destroyIndicator(AccreditationIndicator $indicator): RedirectResponse
    {
        $this->ensureEditable($indicator->version);
        $indicator->delete();
        $this->toast('Indikator dihapus.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function criterionRules(Request $request, AccreditationInstrumentVersion $version, ?AccreditationCriterion $criterion = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accreditation_criteria', 'code')->where('instrument_version_id', $version->id)->ignore($criterion)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'weight' => ['nullable', 'numeric', 'between:0,100'],
            'parent_id' => ['nullable', Rule::exists('accreditation_criteria', 'id')->where('instrument_version_id', $version->id)->whereNull('parent_id')],
        ]) + ['weight' => 0];
    }

    /**
     * @return array<string, mixed>
     */
    private function indicatorRules(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'statement' => ['required', 'string', 'max:3000'],
            'target' => ['nullable', 'string', 'max:2000'],
            'evidence_hint' => ['nullable', 'string', 'max:2000'],
            'weight' => ['required', 'numeric', 'between:0,100'],
            'is_essential' => ['boolean'],
        ]);
    }

    private function ensureEditable(AccreditationInstrumentVersion $version): void
    {
        if (! $version->isEditable()) {
            $this->failWith('Versi yang berlaku terkunci. Buat versi baru untuk mengubah struktur.');
        }
    }
}
