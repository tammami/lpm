<?php

namespace App\Http\Controllers\Accreditation;

use App\Http\Controllers\Controller;
use App\Models\AccreditationPeriod;
use App\Models\StudyProgram;
use App\Services\Accreditation\ReadinessCalculator;
use App\Services\Settings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard kesiapan akreditasi seluruh prodi dalam cakupan pengguna.
 */
class ReadinessController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $warningDays = (int) Settings::get('accreditation.expiry_warning_days', 180);

        $programs = StudyProgram::query()->visibleTo($user)->where('is_active', true)->with('accreditationBody:id,code')->orderBy('name')->get();
        $periods = AccreditationPeriod::query()
            ->whereIn('study_program_id', $programs->pluck('id'))
            ->whereIn('status', ['preparing', 'submitted', 'visitation'])
            ->with(['version.instrument:id,code', 'pic:id,name'])
            ->get()
            ->keyBy('study_program_id');

        $rows = $programs->map(function (StudyProgram $program) use ($periods): array {
            $period = $periods->get($program->id);
            $calculator = $period ? ReadinessCalculator::for($period) : null;

            return [
                'id' => $program->id,
                'name' => $program->full_name,
                'code' => $program->code,
                'body' => $program->accreditationBody?->code,
                'status' => $program->accreditation_status,
                'valid_until' => $program->accreditation_valid_until?->toDateString(),
                'days_left' => $program->accreditation_valid_until ? (int) now()->startOfDay()->diffInDays($program->accreditation_valid_until, false) : null,
                'period' => $period ? [
                    'id' => $period->id,
                    'code' => $period->code,
                    'name' => $period->name,
                    'status_label' => $period->status->label(),
                    'status' => $period->status->value,
                    'instrument' => $period->version->instrument->code.' v'.$period->version->version,
                    'pic' => $period->pic?->name,
                    'submission_deadline' => $period->submission_deadline?->toDateString(),
                    'days_to_deadline' => $period->daysToDeadline(),
                ] : null,
                'summary' => $calculator?->summary(),
                'criteria' => $calculator ? array_map(fn (array $c): array => [
                    'code' => $c['code'], 'title' => $c['title'], 'readiness' => $c['readiness'],
                ], $calculator->byCriterion()) : [],
            ];
        })->sortBy(fn (array $row): array => [$row['period'] ? 0 : 1, $row['days_left'] ?? PHP_INT_MAX])->values();

        return Inertia::render('accreditation/readiness', [
            'programs' => $rows->all(),
            'warningDays' => $warningDays,
            'can' => ['manage' => $user->can('accreditation.manage')],
        ]);
    }
}
