<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\AccreditationBody;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccreditationBodyController extends Controller
{
    public function index(Request $request): Response
    {
        $bodies = TableQuery::for(AccreditationBody::query()->withCount('studyPrograms'), $request)
            ->search(['name', 'code'])
            ->sort(['code', 'name'], 'code')
            ->paginate()
            ->through(fn (AccreditationBody $body): array => [
                ...$body->only(['id', 'code', 'name', 'description', 'website', 'is_active']),
                'study_programs_count' => $body->study_programs_count,
            ]);

        return Inertia::render('master/accreditation-bodies', [
            'bodies' => $bodies,
            'filters' => $this->filters($request, []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AccreditationBody::query()->create($this->validated($request));
        $this->toast('Lembaga akreditasi berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, AccreditationBody $accreditationBody): RedirectResponse
    {
        $accreditationBody->update($this->validated($request, $accreditationBody));
        $this->toast('Lembaga akreditasi berhasil diperbarui.');

        return back();
    }

    public function destroy(AccreditationBody $accreditationBody): RedirectResponse
    {
        $this->deleteSafely($accreditationBody, 'Lembaga akreditasi');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AccreditationBody $body = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('accreditation_bodies')->ignore($body)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
