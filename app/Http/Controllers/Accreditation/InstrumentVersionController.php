<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\AccreditationVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\AccreditationCriterion;
use App\Models\AccreditationIndicator;
use App\Models\AccreditationInstrumentVersion;
use App\Services\Accreditation\AccreditationInstruments;
use App\Support\Spreadsheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstrumentVersionController extends Controller
{
    public function show(AccreditationInstrumentVersion $version, AccreditationInstruments $instruments): Response
    {
        $version->load(['instrument.body:id,code,name', 'instrument.versions:id,accreditation_instrument_id,version,status']);
        $criteria = $version->criteria()->with('indicators')->get();

        return Inertia::render('accreditation/instruments/builder', [
            'version' => [
                ...$version->only(['id', 'version', 'scale_max', 'notes']),
                'status' => $version->status->value,
                'status_label' => $version->status->label(),
                'editable' => $version->isEditable(),
                'thresholds' => $version->thresholds(),
                'periods_count' => $version->periods()->count(),
                'published_at' => $version->published_at?->toIso8601String(),
            ],
            'instrument' => [
                ...$version->instrument->only(['id', 'code', 'name', 'description']),
                'body' => $version->instrument->body?->code,
                'versions' => $version->instrument->versions->map(fn (AccreditationInstrumentVersion $v): array => [
                    'id' => $v->id, 'version' => $v->version, 'status' => $v->status->value, 'status_label' => $v->status->label(),
                ])->all(),
            ],
            'criteria' => $criteria->map(fn (AccreditationCriterion $criterion): array => [
                ...$criterion->only(['id', 'parent_id', 'code', 'title', 'description', 'weight', 'sort_order']),
                'indicators' => $criterion->indicators->map(fn (AccreditationIndicator $indicator): array => $indicator->only([
                    'id', 'criterion_id', 'code', 'statement', 'target', 'evidence_hint', 'weight', 'is_essential', 'sort_order',
                ]))->all(),
            ])->all(),
            'issues' => $version->isEditable() ? $instruments->publishIssues($version) : [],
        ]);
    }

    public function update(Request $request, AccreditationInstrumentVersion $version): RedirectResponse
    {
        $this->ensureEditable($version);

        $validated = $request->validate([
            'version' => ['required', 'string', 'max:20', Rule::unique('accreditation_instrument_versions', 'version')->where('accreditation_instrument_id', $version->accreditation_instrument_id)->ignore($version)],
            'scale_max' => ['required', 'numeric', 'between:1,10'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'thresholds' => ['required', 'array', 'min:1'],
            'thresholds.*.label' => ['required', 'string', 'max:50'],
            'thresholds.*.min' => ['required', 'numeric', 'between:0,400'],
        ]);

        $version->update([
            ...collect($validated)->only(['version', 'scale_max', 'notes'])->all(),
            'grade_thresholds' => array_values($validated['thresholds']),
        ]);
        $this->toast('Pengaturan versi disimpan.');

        return back();
    }

    public function transition(Request $request, AccreditationInstrumentVersion $version, AccreditationInstruments $instruments): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(AccreditationVersionStatus::class)]]);
        $instruments->transition($version, AccreditationVersionStatus::from($validated['status']));
        $this->toast($validated['status'] === 'published' ? 'Versi ditetapkan berlaku dan terkunci.' : 'Status versi diperbarui.');

        return back();
    }

    public function duplicate(Request $request, AccreditationInstrumentVersion $version, AccreditationInstruments $instruments): RedirectResponse
    {
        $copy = $instruments->clone($version, $request->user());
        $this->toast("Versi {$copy->version} (draf) dibuat dari versi {$version->version}.");

        return redirect()->route('accreditation.versions.show', $copy);
    }

    public function destroy(AccreditationInstrumentVersion $version): RedirectResponse
    {
        $this->ensureEditable($version);

        if ($version->instrument->versions()->count() === 1) {
            $this->failWith('Versi terakhir tidak dapat dihapus. Hapus instrumennya bila tidak diperlukan.');
        }

        $instrument = $version->instrument;
        $this->deleteSafely($version, 'Versi draf');

        return redirect()->route('accreditation.versions.show', $instrument->versions()->first());
    }

    public function import(Request $request, AccreditationInstrumentVersion $version, AccreditationInstruments $instruments): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120']]);
        $file = $request->file('file');
        $path = $file->storeAs('tmp', Str::uuid().'.'.strtolower($file->getClientOriginalExtension()), 'local');

        try {
            $result = $instruments->import($version, Storage::disk('local')->path($path));
        } finally {
            Storage::disk('local')->delete($path);
        }

        $this->toast("Impor selesai: {$result['criteria']} kriteria, {$result['indicators']} indikator.");

        return back();
    }

    public function export(AccreditationInstrumentVersion $version, AccreditationInstruments $instruments): StreamedResponse
    {
        $version->load('instrument');

        return Spreadsheet::download(
            Str::slug("instrumen-{$version->instrument->code}-v{$version->version}").'.xlsx',
            AccreditationInstruments::IMPORT_HEADERS,
            $instruments->exportRows($version),
            [14, 36, 14, 10, 14, 70, 40, 40, 8, 10],
            title: 'Instrumen',
        );
    }

    private function ensureEditable(AccreditationInstrumentVersion $version): void
    {
        if (! $version->isEditable()) {
            $this->failWith('Versi yang berlaku/diarsipkan terkunci. Buat versi baru untuk melakukan perubahan.');
        }
    }
}
