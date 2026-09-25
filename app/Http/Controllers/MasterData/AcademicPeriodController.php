<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\AcademicSemester;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Services\AuditLogger;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AcademicPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $periods = TableQuery::for(AcademicPeriod::query()->withCount('classes'), $request)
            ->search(['name', 'code', 'academic_year'])
            ->sort(['starts_on', 'code', 'name'], '-starts_on')
            ->paginate()
            ->through(fn (AcademicPeriod $period): array => [
                ...$period->only(['id', 'code', 'name', 'academic_year', 'is_active', 'classes_count']),
                'semester' => $period->semester->value,
                'semester_label' => $period->semester->label(),
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
            ]);

        return Inertia::render('master/academic-periods', [
            'periods' => $periods,
            'filters' => $this->filters($request, []),
            'semesters' => AcademicSemester::options(),
            'canManage' => $request->user()->can('organization.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        AcademicPeriod::query()->create($this->validated($request));
        $this->toast('Periode akademik berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, AcademicPeriod $academicPeriod): RedirectResponse
    {
        $academicPeriod->update($this->validated($request, $academicPeriod));
        $this->toast('Periode akademik berhasil diperbarui.');

        return back();
    }

    /**
     * Jadikan periode ini sebagai periode aktif (hanya satu periode aktif).
     */
    public function activate(AcademicPeriod $academicPeriod): RedirectResponse
    {
        DB::transaction(function () use ($academicPeriod): void {
            AcademicPeriod::query()->whereKeyNot($academicPeriod->id)->update(['is_active' => false]);
            $academicPeriod->update(['is_active' => true]);
        });

        AuditLogger::log('activated', 'master', $academicPeriod, "Mengaktifkan periode {$academicPeriod->name}");
        $this->toast("{$academicPeriod->name} kini menjadi periode aktif.");

        return back();
    }

    public function destroy(AcademicPeriod $academicPeriod): RedirectResponse
    {
        $this->deleteSafely($academicPeriod, 'Periode akademik');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AcademicPeriod $period = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('academic_periods')->ignore($period)],
            'name' => ['required', 'string', 'max:255'],
            'academic_year' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', Rule::enum(AcademicSemester::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ], ['academic_year.regex' => 'Format tahun akademik: 2026/2027.']);
    }
}
