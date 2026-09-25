<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\Institution;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FacultyController extends Controller
{
    public function index(Request $request): Response
    {
        $faculties = TableQuery::for(Faculty::query()->withCount('studyPrograms'), $request)
            ->search(['name', 'code', 'dean_name'])
            ->sort(['name', 'code'], 'name')
            ->paginate()
            ->through(fn (Faculty $faculty): array => [
                ...$faculty->only(['id', 'code', 'name', 'dean_name', 'is_active']),
                'study_programs_count' => $faculty->study_programs_count,
            ]);

        return Inertia::render('master/faculties', [
            'faculties' => $faculties,
            'filters' => $this->filters($request, []),
            'canManage' => $request->user()->can('organization.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Faculty::query()->create([...$this->validated($request), 'institution_id' => Institution::current()->id]);
        $this->toast('Fakultas berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, Faculty $faculty): RedirectResponse
    {
        $faculty->update($this->validated($request, $faculty));
        $this->toast('Fakultas berhasil diperbarui.');

        return back();
    }

    public function destroy(Faculty $faculty): RedirectResponse
    {
        $this->deleteSafely($faculty, 'Fakultas');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Faculty $faculty = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('faculties')->ignore($faculty)],
            'name' => ['required', 'string', 'max:255'],
            'dean_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
