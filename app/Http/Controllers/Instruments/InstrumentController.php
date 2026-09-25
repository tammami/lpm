<?php

namespace App\Http\Controllers\Instruments;

use App\Enums\InstrumentType;
use App\Enums\RespondentType;
use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Services\InstrumentVersioning;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Instrument::query()
            ->with(['latestVersion' => fn ($q) => $q->withCount('questions'), 'publishedVersion'])
            ->withCount(['versions', 'surveys'])
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'));

        $instruments = TableQuery::for($query, $request)
            ->search(['name', 'code', 'description'])
            ->filter(['type' => 'type', 'respondent_type' => 'respondent_type'])
            ->sort(['name', 'code', 'updated_at'], '-updated_at')
            ->paginate(12)
            ->through(fn (Instrument $instrument): array => [
                'id' => $instrument->id,
                'code' => $instrument->code,
                'name' => $instrument->name,
                'description' => $instrument->description,
                'type' => $instrument->type->value,
                'type_label' => $instrument->type->label(),
                'respondent_type' => $instrument->respondent_type->value,
                'respondent_label' => $instrument->respondent_type->label(),
                'versions_count' => $instrument->versions_count,
                'archived' => $instrument->archived_at !== null,
                'updated_at' => $instrument->updated_at?->toIso8601String(),
                'latest' => $instrument->latestVersion ? [
                    'id' => $instrument->latestVersion->id,
                    'version' => $instrument->latestVersion->version,
                    'status' => $instrument->latestVersion->status->value,
                    'status_label' => $instrument->latestVersion->status->label(),
                    'questions_count' => $instrument->latestVersion->questions_count,
                ] : null,
                'published_version' => $instrument->publishedVersion?->version,
                'surveys_count' => $instrument->surveys_count,
            ]);

        return Inertia::render('instruments/index', [
            'instruments' => $instruments,
            'filters' => $this->filters($request, ['type', 'respondent_type', 'archived']),
            'types' => InstrumentType::options(),
            'respondentTypes' => RespondentType::options(),
            'canManage' => $request->user()->can('instruments.manage'),
        ]);
    }

    public function store(Request $request, InstrumentVersioning $versioning): RedirectResponse
    {
        $instrument = $versioning->createInstrument($this->validated($request), $request->user());
        $this->toast('Instrumen dibuat. Susun bagian dan pertanyaan pada versi 1.0.');

        return redirect()->route('instrument-versions.show', $instrument->latestVersion()->first());
    }

    public function show(Instrument $instrument): RedirectResponse
    {
        return redirect()->route('instrument-versions.show', $instrument->latestVersion()->firstOrFail());
    }

    public function update(Request $request, Instrument $instrument): RedirectResponse
    {
        $instrument->update($this->validated($request, $instrument));
        $this->toast('Informasi instrumen diperbarui.');

        return back();
    }

    /**
     * Arsipkan/aktifkan kembali instrumen. Instrumen tidak pernah dihapus bila pernah dipakai.
     */
    public function toggleArchive(Instrument $instrument): RedirectResponse
    {
        $instrument->update(['archived_at' => $instrument->archived_at ? null : now()]);
        $this->toast($instrument->archived_at ? 'Instrumen diarsipkan.' : 'Instrumen diaktifkan kembali.');

        return back();
    }

    public function destroy(Instrument $instrument): RedirectResponse
    {
        if ($instrument->surveys()->exists()) {
            $this->toast('Instrumen sudah dipakai pada kegiatan Monev sehingga tidak dapat dihapus. Arsipkan saja.', 'error');

            return back();
        }

        $this->deleteSafely($instrument, 'Instrumen');

        return redirect()->route('instruments.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Instrument $instrument = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('instruments')->ignore($instrument)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(InstrumentType::class)],
            'respondent_type' => ['required', Rule::enum(RespondentType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
