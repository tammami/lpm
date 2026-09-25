<?php

namespace App\Http\Controllers\Ami;

use App\Enums\CorrectiveActionStatus;
use App\Enums\FindingStatus;
use App\Http\Controllers\Controller;
use App\Models\Audit;
use App\Models\CorrectiveAction;
use App\Models\Finding;
use App\Models\FindingSeverity;
use App\Models\QualityStandard;
use App\Models\RootCauseCategory;
use App\Models\User;
use App\Services\Ami\FindingWorkflow;
use App\Services\Evidence\EvidenceService;
use App\Support\CodeGenerator;
use App\Support\EvidenceOptions;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FindingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $base = Finding::query()->visibleTo($user, $user->can('ami.view'));

        $query = (clone $base)
            ->with(['severity', 'pic:id,name', 'standard:id,code,name', 'audit:id,code'])
            ->withCount('correctiveActions')
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->overdue())
            ->when($request->boolean('mine'), fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('pic_user_id', $user->id)->orWhereHas('correctiveActions', fn (Builder $a) => $a->where('pic_user_id', $user->id))));

        $findings = TableQuery::for($query, $request)
            ->search(['code', 'title', 'auditee_name'])
            ->filter(['status' => 'status', 'finding_severity_id' => 'finding_severity_id', 'quality_standard_id' => 'quality_standard_id'])
            ->sort(['code', 'due_date', 'created_at'], '-created_at')
            ->paginate()
            ->through(fn (Finding $finding): array => self::row($finding));

        $statusCounts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('ami/findings/index', [
            'findings' => $findings,
            'filters' => [...$this->filters($request, ['status', 'finding_severity_id', 'quality_standard_id']), 'overdue' => $request->boolean('overdue'), 'mine' => $request->boolean('mine')],
            'statuses' => FindingStatus::options(),
            'severities' => FindingSeverity::query()->orderBy('sort_order')->get(['id', 'name'])->map(fn ($s): array => ['value' => $s->id, 'label' => $s->name])->all(),
            'standards' => QualityStandard::query()->orderBy('sort_order')->get(['id', 'code', 'name'])->map(fn ($s): array => ['value' => $s->id, 'label' => "{$s->code} — {$s->name}"])->all(),
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                'unresolved' => (int) collect(FindingStatus::unresolved())->sum(fn (FindingStatus $s) => $statusCounts[$s->value] ?? 0),
                'overdue' => (clone $base)->overdue()->count(),
                'awaiting' => (int) ($statusCounts[FindingStatus::Submitted->value] ?? 0),
                'closed' => (int) ($statusCounts[FindingStatus::Closed->value] ?? 0),
            ],
        ]);
    }

    public function store(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless($request->user()->can('ami.audit') && $audit->isAssignedTo($request->user()), 403, 'Hanya auditor yang ditugaskan yang dapat membuat temuan.');

        $validated = $this->validated($request, $audit);
        $severity = FindingSeverity::query()->findOrFail($validated['finding_severity_id']);

        DB::transaction(fn () => Finding::query()->create([
            ...$validated,
            'code' => CodeGenerator::next('TMN', 'findings'),
            'audit_id' => $audit->id,
            'auditee_type' => $audit->auditee_type,
            'auditee_id' => $audit->auditee_id,
            'auditee_name' => $audit->auditee_name,
            'study_program_id' => $audit->study_program_id,
            'faculty_id' => $audit->faculty_id,
            'pic_user_id' => $validated['pic_user_id'] ?? $audit->auditee_pic_user_id,
            'due_date' => $validated['due_date'] ?? now()->addDays($severity->default_due_days)->toDateString(),
            'status' => FindingStatus::Open,
            'created_by' => $request->user()->id,
        ]));

        $this->toast('Temuan dicatat sebagai draf auditor. Terbitkan ke auditee bila sudah final.');

        return back();
    }

    public function show(Request $request, Finding $finding): Response
    {
        $user = $request->user();
        $this->authorizeView($request, $finding);

        $finding->load([
            'severity', 'standard', 'question', 'pic:id,name,email', 'creator:id,name', 'audit.auditors.user:id,name',
            'correctiveActions.pic:id,name', 'correctiveActions.rootCauseCategory',
            'correctiveActions.evidenceMappings.evidence.currentVersion', 'evidenceMappings.evidence.currentVersion',
            'verifications.verifier:id,name',
        ]);

        $isAuditor = $finding->audit && $user->can('ami.audit') && $finding->audit->isAssignedTo($user);
        $isResponder = $this->canRespond($user, $finding);

        return Inertia::render('ami/findings/show', [
            'finding' => [
                ...self::row($finding),
                'description' => $finding->description,
                'criteria' => $finding->criteria,
                'effect' => $finding->effect,
                'recommendation' => $finding->recommendation,
                'question' => $finding->question?->only(['code', 'label']),
                'standard_detail' => $finding->standard?->only(['code', 'name', 'statement', 'indicator']),
                'audit' => $finding->audit ? ['id' => $finding->audit->id, 'code' => $finding->audit->code, 'auditors' => $finding->audit->auditors->map(fn ($a) => $a->user?->name)->filter()->values()->all()] : null,
                'creator' => $finding->creator?->name,
                'issued_at' => $finding->issued_at?->toIso8601String(),
                'verified_at' => $finding->verified_at?->toIso8601String(),
                'closed_at' => $finding->closed_at?->toIso8601String(),
                'created_at' => $finding->created_at?->toIso8601String(),
                'step' => $finding->status->step(),
                'requires_corrective_action' => $finding->severity->requires_corrective_action,
                'finding_severity_id' => $finding->finding_severity_id,
                'quality_standard_id' => $finding->quality_standard_id,
                'instrument_question_id' => $finding->instrument_question_id,
                'pic_user_id' => $finding->pic_user_id,
            ],
            'actions' => $finding->correctiveActions->map(fn (CorrectiveAction $action): array => [
                ...$action->only(['id', 'root_cause', 'action_plan', 'preventive_action', 'progress', 'implementation_notes', 'root_cause_category_id', 'pic_user_id']),
                'status' => $action->status->value,
                'status_label' => $action->status->label(),
                'root_cause_category' => $action->rootCauseCategory?->name,
                'pic' => $action->pic?->name,
                'due_date' => $action->due_date->toDateString(),
                'completed_at' => $action->completed_at?->toIso8601String(),
                'overdue' => $action->due_date->isPast() && in_array($action->status, [CorrectiveActionStatus::Planned, CorrectiveActionStatus::InProgress, CorrectiveActionStatus::Rejected], true),
                'evidence' => EvidenceService::linkedPayload($action->evidenceMappings, $user, EvidenceService::isLocked($action)),
            ])->all(),
            'evidence' => EvidenceService::linkedPayload($finding->evidenceMappings, $user, EvidenceService::isLocked($finding)),
            'verifications' => $finding->verifications->map(fn ($v): array => [
                'decision' => $v->decision, 'notes' => $v->notes, 'verifier' => $v->verifier?->name, 'verified_at' => $v->verified_at->toIso8601String(),
            ])->all(),
            'rootCauses' => RootCauseCategory::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name'])->map(fn ($r): array => ['value' => $r->id, 'label' => $r->name])->all(),
            'evidenceOptions' => EvidenceOptions::for($request),
            ...($finding->audit ? self::formOptions($finding->audit) : []),
            'picOptions' => AuditController::formOptions($request)['picOptions'],
            'can' => [
                'edit' => $isAuditor && $finding->status === FindingStatus::Open,
                'issue' => ($isAuditor || $user->can('ami.manage')) && $finding->status === FindingStatus::Open,
                'respond' => $isResponder && in_array($finding->status, [FindingStatus::ActionRequired, FindingStatus::InProgress], true),
                'submit' => $isResponder && $finding->status === FindingStatus::InProgress,
                'verify' => ($isAuditor || $user->can('improvement.verify')) && $finding->status === FindingStatus::Submitted,
                'close' => ($user->can('ami.manage') || ($finding->audit?->isLeadAuditor($user) ?? false))
                    && ($finding->status === FindingStatus::Verified || (! $finding->severity->requires_corrective_action && in_array($finding->status, FindingStatus::unresolved(), true))),
                'attach' => $isResponder || $isAuditor,
            ],
        ]);
    }

    public function update(Request $request, Finding $finding): RedirectResponse
    {
        abort_unless($finding->audit && $finding->audit->isAssignedTo($request->user()) && $finding->status === FindingStatus::Open, 403);
        $finding->update($this->validated($request, $finding->audit));
        $this->toast('Temuan diperbarui.');

        return back();
    }

    public function destroy(Request $request, Finding $finding): RedirectResponse
    {
        abort_unless($finding->audit && $finding->audit->isAssignedTo($request->user()) && $finding->status === FindingStatus::Open, 403, 'Hanya temuan draf yang dapat dihapus auditor.');
        $finding->delete();
        $this->toast('Temuan draf dihapus.');

        return redirect()->route('ami.audits.show', $finding->audit_id);
    }

    public function issue(Request $request, Finding $finding, FindingWorkflow $workflow): RedirectResponse
    {
        abort_unless(($finding->audit?->isAssignedTo($request->user()) ?? false) || $request->user()->can('ami.manage'), 403);
        $workflow->issue($finding, $request->user());
        $this->toast('Temuan diterbitkan. PIC auditee telah diberi tahu.');

        return back();
    }

    public function storeAction(Request $request, Finding $finding, FindingWorkflow $workflow): RedirectResponse
    {
        abort_unless($this->canRespond($request->user(), $finding), 403);
        $workflow->addCorrectiveAction($finding, $this->validatedAction($request), $request->user());
        $this->toast('Rencana tindakan koreksi ditambahkan.');

        return back();
    }

    public function updateAction(Request $request, Finding $finding, CorrectiveAction $action): RedirectResponse
    {
        abort_unless($action->finding_id === $finding->id && $this->canRespond($request->user(), $finding), 403);

        if (! in_array($finding->status, [FindingStatus::ActionRequired, FindingStatus::InProgress], true)) {
            $this->failWith('Tindakan koreksi terkunci selama proses verifikasi.');
        }

        $validated = $request->validate([
            ...$this->actionRules(),
            'progress' => ['required', 'integer', 'between:0,100'],
            'implementation_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $completed = (int) $validated['progress'] === 100;

        $action->update([
            ...$validated,
            'status' => $completed ? CorrectiveActionStatus::Completed : ($validated['progress'] > 0 ? CorrectiveActionStatus::InProgress : CorrectiveActionStatus::Planned),
            'completed_at' => $completed ? ($action->completed_at ?? now()) : null,
        ]);

        $this->toast($completed ? 'Tindakan koreksi ditandai selesai. Lampirkan bukti lalu ajukan verifikasi.' : 'Progres tindakan koreksi diperbarui.');

        return back();
    }

    public function destroyAction(Request $request, Finding $finding, CorrectiveAction $action): RedirectResponse
    {
        abort_unless($action->finding_id === $finding->id && $this->canRespond($request->user(), $finding) && $action->status === CorrectiveActionStatus::Planned, 403);
        $action->delete();
        $this->toast('Rencana tindakan koreksi dihapus.');

        return back();
    }

    public function submit(Request $request, Finding $finding, FindingWorkflow $workflow): RedirectResponse
    {
        abort_unless($this->canRespond($request->user(), $finding), 403);
        $workflow->submit($finding, $request->user());
        $this->toast('Tindak lanjut diajukan untuk diverifikasi auditor.');

        return back();
    }

    public function verify(Request $request, Finding $finding, FindingWorkflow $workflow): RedirectResponse
    {
        $user = $request->user();
        abort_unless((($finding->audit?->isAssignedTo($user) ?? false) && $user->can('ami.audit')) || $user->can('improvement.verify'), 403);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'notes' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ], ['notes.required_if' => 'Sertakan catatan perbaikan untuk auditee.']);

        $workflow->verify($finding, $user, $validated['decision'] === 'accepted', $validated['notes'] ?? null);
        $this->toast($validated['decision'] === 'accepted' ? 'Tindak lanjut terverifikasi.' : 'Tindak lanjut dikembalikan untuk diperbaiki.');

        return back();
    }

    public function close(Request $request, Finding $finding, FindingWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('ami.manage') || ($finding->audit?->isLeadAuditor($request->user()) ?? false), 403);
        $workflow->close($finding, $request->user(), $request->string('notes')->toString() ?: null);
        $this->toast('Temuan ditutup.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Finding $finding): array
    {
        $finding->loadMissing(['severity', 'pic:id,name', 'standard:id,code,name', 'audit:id,code']);

        return [
            'id' => $finding->id,
            'code' => $finding->code,
            'title' => $finding->title,
            'auditee_name' => $finding->auditee_name,
            'audit_id' => $finding->audit_id,
            'audit_code' => $finding->audit?->code,
            'severity' => $finding->severity->name,
            'severity_code' => $finding->severity->code,
            'severity_color' => $finding->severity->color,
            'standard' => $finding->standard ? "{$finding->standard->code} — {$finding->standard->name}" : null,
            'status' => $finding->status->value,
            'status_label' => $finding->status->label(),
            'pic' => $finding->pic?->name,
            'due_date' => $finding->due_date?->toDateString(),
            'overdue' => $finding->isOverdue(),
            'corrective_actions_count' => $finding->corrective_actions_count ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formOptions(Audit $audit): array
    {
        return [
            'severityOptions' => FindingSeverity::query()->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (FindingSeverity $s): array => ['value' => $s->id, 'label' => $s->name, 'days' => $s->default_due_days, 'requires_action' => $s->requires_corrective_action, 'color' => $s->color])->all(),
            'standardOptions' => QualityStandard::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'code', 'name'])
                ->map(fn ($s): array => ['value' => $s->id, 'label' => "{$s->code} — {$s->name}"])->all(),
            'questionOptions' => $audit->instrumentVersion?->questions()->where('is_active', true)->get(['id', 'code', 'label'])
                ->map(fn ($q): array => ['value' => $q->id, 'label' => "{$q->code} — ".str($q->label)->limit(80)])->all() ?? [],
        ];
    }

    private function canRespond(User $user, Finding $finding): bool
    {
        if ($finding->pic_user_id === $user->id || $finding->correctiveActions()->where('pic_user_id', $user->id)->exists()) {
            return true;
        }

        if ($user->can('ami.manage')) {
            return true;
        }

        return $user->can('findings.respond')
            && ! $user->isInstitutionWide()
            && Finding::query()->visibleTo($user)->whereKey($finding->id)->exists();
    }

    private function authorizeView(Request $request, Finding $finding): void
    {
        $user = $request->user();
        abort_unless(Finding::query()->visibleTo($user, $user->can('ami.view'))->whereKey($finding->id)->exists(), 403, 'Temuan ini berada di luar cakupan akses Anda.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Audit $audit): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'criteria' => ['nullable', 'string', 'max:3000'],
            'effect' => ['nullable', 'string', 'max:3000'],
            'recommendation' => ['nullable', 'string', 'max:3000'],
            'finding_severity_id' => ['required', 'exists:finding_severities,id'],
            'quality_standard_id' => ['nullable', 'exists:quality_standards,id'],
            'instrument_question_id' => ['nullable', Rule::exists('instrument_questions', 'id')->where('instrument_version_id', $audit->instrument_version_id)],
            'pic_user_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ], ['description.required' => 'Uraikan kondisi yang ditemukan.']);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function actionRules(): array
    {
        return [
            'root_cause' => ['required', 'string', 'max:3000'],
            'root_cause_category_id' => ['nullable', 'exists:root_cause_categories,id'],
            'action_plan' => ['required', 'string', 'max:3000'],
            'preventive_action' => ['nullable', 'string', 'max:3000'],
            'pic_user_id' => ['required', 'exists:users,id'],
            'due_date' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAction(Request $request): array
    {
        return $request->validate($this->actionRules(), [
            'pic_user_id.required' => 'Setiap tindakan koreksi wajib memiliki PIC.',
            'root_cause.required' => 'Uraikan akar masalah.',
        ]);
    }
}
