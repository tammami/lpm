<?php

namespace App\Http\Controllers\Ami;

use App\Enums\AuditProgramStatus;
use App\Enums\AuditStatus;
use App\Enums\FindingStatus;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\AuditProgram;
use App\Models\Finding;
use App\Support\CodeGenerator;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $programs = TableQuery::for(AuditProgram::query()->with('academicPeriod:id,name'), $request)
            ->search(['name', 'code'])
            ->filter(['status' => 'status', 'year' => 'year'])
            ->sort(['year', 'name', 'starts_on'], '-year')
            ->paginate(10)
            ->through(function (AuditProgram $program) use ($user): array {
                $audits = Audit::query()->visibleTo($user, $user->can('ami.view'))->where('audit_program_id', $program->id);
                $findings = Finding::query()->visibleTo($user, $user->can('ami.view'))->whereHas('audit', fn ($q) => $q->where('audit_program_id', $program->id));

                return [
                    ...$program->only(['id', 'code', 'name', 'year', 'scope', 'objective']),
                    'status' => $program->status->value,
                    'status_label' => $program->status->label(),
                    'period' => $program->academicPeriod?->name,
                    'starts_on' => $program->starts_on?->toDateString(),
                    'ends_on' => $program->ends_on?->toDateString(),
                    'audits_total' => (clone $audits)->count(),
                    'audits_completed' => (clone $audits)->where('status', AuditStatus::Completed)->count(),
                    'findings_total' => (clone $findings)->count(),
                    'findings_open' => (clone $findings)->whereIn('status', FindingStatus::unresolved())->count(),
                ];
            });

        return Inertia::render('ami/programs/index', [
            'programs' => $programs,
            'filters' => $this->filters($request, ['status', 'year']),
            'statuses' => AuditProgramStatus::options(),
            'periods' => Options::periods(),
            'canManage' => $user->can('ami.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $program = DB::transaction(fn () => AuditProgram::query()->create([
            ...$this->validated($request),
            'code' => CodeGenerator::next('AMI', 'audit_programs', pad: 2),
            'status' => AuditProgramStatus::Draft,
            'created_by' => $request->user()->id,
        ]));

        $this->toast('Program audit dibuat. Tambahkan jadwal audit untuk setiap auditee.');

        return redirect()->route('ami.programs.show', $program);
    }

    public function show(Request $request, AuditProgram $program): Response
    {
        $user = $request->user();
        $audits = Audit::query()->visibleTo($user, $user->can('ami.view'))
            ->where('audit_program_id', $program->id)
            ->with(['auditors.user:id,name', 'auditeePic:id,name', 'instrumentVersion.instrument:id,name'])
            ->withCount(['findings', 'findings as open_findings_count' => fn ($q) => $q->whereIn('status', FindingStatus::unresolved())])
            ->orderBy('scheduled_on')
            ->get();

        return Inertia::render('ami/programs/show', [
            'program' => [
                ...$program->only(['id', 'code', 'name', 'year', 'scope', 'objective', 'criteria', 'academic_period_id']),
                'status' => $program->status->value,
                'status_label' => $program->status->label(),
                'period' => $program->academicPeriod?->name,
                'starts_on' => $program->starts_on?->toDateString(),
                'ends_on' => $program->ends_on?->toDateString(),
            ],
            'audits' => $audits->map(fn (Audit $audit): array => AuditController::summary($audit))->all(),
            'statuses' => AuditProgramStatus::options(),
            'periods' => Options::periods(),
            ...AuditController::formOptions($request),
            'canManage' => $user->can('ami.manage'),
        ]);
    }

    public function update(Request $request, AuditProgram $program): RedirectResponse
    {
        $program->update($this->validated($request));
        $this->toast('Program audit diperbarui.');

        return back();
    }

    public function transition(Request $request, AuditProgram $program): RedirectResponse
    {
        $status = AuditProgramStatus::from($request->validate(['status' => ['required', Rule::enum(AuditProgramStatus::class)]])['status']);
        $program->update(['status' => $status]);
        $this->toast("Status program: {$status->label()}.");

        return back();
    }

    public function destroy(AuditProgram $program): RedirectResponse
    {
        if ($program->audits()->exists()) {
            $this->failWith('Program yang sudah memiliki jadwal audit tidak dapat dihapus. Arsipkan saja.');
        }

        $program->delete();
        $this->toast('Program audit dihapus.');

        return redirect()->route('ami.programs.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'academic_period_id' => ['nullable', 'exists:academic_periods,id'],
            'scope' => ['nullable', 'string', 'max:3000'],
            'objective' => ['nullable', 'string', 'max:3000'],
            'criteria' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
    }
}
