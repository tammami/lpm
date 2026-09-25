<?php

namespace App\Http\Controllers\Accreditation;

use App\Http\Controllers\Controller;
use App\Models\AccreditationInstrument;
use App\Models\AccreditationInstrumentVersion;
use App\Services\Accreditation\AccreditationInstruments;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentController extends Controller
{
    public function index(): Response
    {
        $instruments = AccreditationInstrument::query()
            ->with(['body:id,code,name', 'versions' => fn ($q) => $q->withCount(['indicators', 'criteria as criteria_count' => fn ($c) => $c->whereNull('parent_id'), 'periods'])])
            ->orderBy('code')
            ->get();

        return Inertia::render('accreditation/instruments/index', [
            'instruments' => $instruments->map(fn (AccreditationInstrument $instrument): array => [
                ...$instrument->only(['id', 'code', 'name', 'description', 'is_active', 'accreditation_body_id']),
                'body' => $instrument->body?->code,
                'body_name' => $instrument->body?->name,
                'versions' => $instrument->versions->map(fn (AccreditationInstrumentVersion $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'status' => $version->status->value,
                    'status_label' => $version->status->label(),
                    'criteria_count' => $version->criteria_count,
                    'indicators_count' => $version->indicators_count,
                    'periods_count' => $version->periods_count,
                    'published_at' => $version->published_at?->toIso8601String(),
                ])->all(),
            ])->all(),
            'bodies' => Options::accreditationBodies(),
        ]);
    }

    public function store(Request $request, AccreditationInstruments $instruments): RedirectResponse
    {
        $instrument = $instruments->create($this->validated($request), $request->user());
        $this->toast('Instrumen dibuat. Susun kriteria & indikatornya, lalu tetapkan berlaku.');

        return redirect()->route('accreditation.versions.show', $instrument->versions()->first());
    }

    public function update(Request $request, AccreditationInstrument $instrument): RedirectResponse
    {
        $instrument->update($this->validated($request, $instrument));
        $this->toast('Instrumen diperbarui.');

        return back();
    }

    public function destroy(AccreditationInstrument $instrument): RedirectResponse
    {
        if ($instrument->versions()->whereHas('periods')->exists()) {
            $this->failWith('Instrumen sudah dipakai periode akreditasi. Nonaktifkan saja.');
        }

        $this->deleteSafely($instrument, 'Instrumen');

        return redirect()->route('accreditation.instruments.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AccreditationInstrument $instrument = null): array
    {
        return $request->validate([
            'accreditation_body_id' => ['required', 'exists:accreditation_bodies,id'],
            'code' => ['required', 'string', 'max:40', Rule::unique('accreditation_instruments', 'code')->ignore($instrument)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);
    }
}
