<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\Unit;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public const TYPES = [
        ['value' => 'lembaga', 'label' => 'Lembaga'],
        ['value' => 'biro', 'label' => 'Biro'],
        ['value' => 'upt', 'label' => 'UPT'],
        ['value' => 'bagian', 'label' => 'Bagian'],
        ['value' => 'unit', 'label' => 'Unit lainnya'],
    ];

    public function index(Request $request): Response
    {
        $units = TableQuery::for(Unit::query()->with('faculty:id,name'), $request)
            ->search(['name', 'code', 'head_name'])
            ->filter(['type' => 'type'])
            ->sort(['name', 'code', 'type'], 'name')
            ->paginate()
            ->through(fn (Unit $unit): array => [
                ...$unit->only(['id', 'code', 'name', 'type', 'faculty_id', 'head_name', 'is_active']),
                'faculty' => $unit->faculty?->name,
            ]);

        return Inertia::render('master/units', [
            'units' => $units,
            'filters' => $this->filters($request, ['type']),
            'types' => self::TYPES,
            'faculties' => Options::faculties(),
            'canManage' => $request->user()->can('organization.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Unit::query()->create([...$this->validated($request), 'institution_id' => Institution::current()->id]);
        $this->toast('Unit kerja berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $unit->update($this->validated($request, $unit));
        $this->toast('Unit kerja berhasil diperbarui.');

        return back();
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $this->deleteSafely($unit, 'Unit kerja');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('units')->ignore($unit)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_column(self::TYPES, 'value'))],
            'faculty_id' => ['nullable', 'exists:faculties,id'],
            'head_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
