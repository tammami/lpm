<?php

namespace App\Http\Controllers\Improvement;

use App\Enums\ActionPlanStatus;
use App\Enums\Priority;
use App\Enums\RecommendationOrigin;
use App\Enums\RecommendationStatus;
use App\Http\Controllers\Controller;
use App\Models\ActionPlan;
use App\Models\ActionTask;
use App\Models\Recommendation;
use App\Models\User;
use App\Notifications\ImprovementNotification;
use App\Services\AuditLogger;
use App\Services\Evidence\EvidenceService;
use App\Services\Improvement\ImprovementWorkflow;
use App\Services\Improvement\RecommendationEngine;
use App\Services\Settings;
use App\Support\Auditee;
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

class RecommendationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $base = Recommendation::query()->visibleTo($user, $user->can('improvement.view') && ! $user->hasRole('dosen'));

        $query = (clone $base)
            ->with(['pic:id,name'])
            ->withCount(['actionPlans', 'actionPlans as verified_plans_count' => fn (Builder $q) => $q->where('status', ActionPlanStatus::Verified)])
            ->withAvg('actionPlans', 'progress')
            ->when($request->boolean('mine'), fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('pic_user_id', $user->id)
                ->orWhereHas('actionPlans', fn (Builder $p) => $p->where('pic_user_id', $user->id))));

        $recommendations = TableQuery::for($query, $request)
            ->search(['code', 'title', 'target_name', 'indicator'])
            ->filter(['status' => 'status', 'priority' => 'priority', 'origin' => 'origin', 'study_program_id' => 'study_program_id'])
            ->sort(['code', 'due_date', 'created_at', 'priority'], '-created_at')
            ->paginate()
            ->through(fn (Recommendation $recommendation): array => self::row($recommendation));

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('improvement/recommendations/index', [
            'recommendations' => $recommendations,
            'filters' => [...$this->filters($request, ['status', 'priority', 'origin', 'study_program_id']), 'mine' => $request->boolean('mine')],
            'statuses' => RecommendationStatus::options(),
            'priorities' => Priority::options(),
            'origins' => RecommendationOrigin::options(),
            'stats' => [
                'open' => (int) ($counts[RecommendationStatus::Open->value] ?? 0),
                'in_progress' => (int) ($counts[RecommendationStatus::InProgress->value] ?? 0) + (int) ($counts[RecommendationStatus::Completed->value] ?? 0),
                'verified' => (int) ($counts[RecommendationStatus::Verified->value] ?? 0) + (int) ($counts[RecommendationStatus::Closed->value] ?? 0),
                'overdue_plans' => ActionPlan::query()->overdue()->whereIn('recommendation_id', (clone $base)->select('id'))->count(),
            ],
            ...$this->formOptions($request),
            'canManage' => $user->can('improvement.manage'),
        ]);
    }

    /**
     * Pratinjau kandidat rekomendasi dari mesin aturan.
     */
    public function generate(Request $request, RecommendationEngine $engine): Response
    {
        return Inertia::render('improvement/recommendations/generate', [
            'candidates' => $engine->candidates($request->user())->all(),
            'threshold' => (float) Settings::get('monev.low_score_threshold', 3.0),
            'picOptions' => $this->formOptions($request)['picOptions'],
            'defaultDueDays' => (int) Settings::get('improvement.default_due_days', 30),
        ]);
    }

    public function accept(Request $request, RecommendationEngine $engine): RedirectResponse
    {
        $validated = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*.rule_key' => ['required', 'string'],
            'selected.*.pic_user_id' => ['nullable', 'exists:users,id'],
            'selected.*.priority' => ['required', Rule::enum(Priority::class)],
        ], ['selected.required' => 'Pilih minimal satu kandidat rekomendasi.']);

        $candidates = $engine->candidates($request->user())->keyBy('rule_key');
        $due = now()->addDays((int) Settings::get('improvement.default_due_days', 30))->toDateString();
        $created = 0;

        DB::transaction(function () use ($validated, $candidates, $due, $request, &$created): void {
            foreach ($validated['selected'] as $item) {
                $candidate = $candidates->get($item['rule_key']);

                if (! $candidate || $candidate['exists']) {
                    continue;
                }

                $recommendation = Recommendation::query()->create([
                    ...collect($candidate)->only(['rule_key', 'title', 'description', 'rationale', 'origin', 'source_type', 'source_id', 'target_type', 'target_id', 'target_name', 'study_program_id', 'faculty_id', 'indicator'])->all(),
                    'code' => CodeGenerator::next('REK', 'recommendations'),
                    'priority' => $item['priority'],
                    'pic_user_id' => $item['pic_user_id'] ?? $candidate['pic_user_id'],
                    'due_date' => $due,
                    'status' => RecommendationStatus::Open,
                    'created_by' => $request->user()->id,
                ]);

                $recommendation->pic?->notify(new ImprovementNotification($recommendation, 'assigned'));
                $created++;
            }
        });

        $this->toast("{$created} rekomendasi dibuat dan PIC telah diberi tahu.");

        return redirect()->route('improvement.recommendations.index');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $target = ! empty($validated['target']) ? Auditee::resolve(...Auditee::parse($validated['target'])) : null;
        unset($validated['target']);

        $recommendation = Recommendation::query()->create([
            ...$validated,
            'code' => CodeGenerator::next('REK', 'recommendations'),
            'origin' => $validated['origin'] ?? RecommendationOrigin::Manual->value,
            'target_type' => $target['type'] ?? null,
            'target_id' => $target['id'] ?? null,
            'target_name' => $target['name'] ?? null,
            'study_program_id' => $target['study_program_id'] ?? null,
            'faculty_id' => $target['faculty_id'] ?? null,
            'status' => RecommendationStatus::Open,
            'created_by' => $request->user()->id,
        ]);

        $recommendation->pic?->notify(new ImprovementNotification($recommendation, 'assigned'));
        $this->toast('Rekomendasi dibuat.');

        return redirect()->route('improvement.recommendations.show', $recommendation);
    }

    public function show(Request $request, Recommendation $recommendation): Response
    {
        $user = $request->user();
        $this->authorizeView($request, $recommendation);

        $recommendation->load(['pic:id,name', 'source', 'actionPlans.pic:id,name', 'actionPlans.tasks', 'actionPlans.evidenceMappings.evidence.currentVersion', 'actionPlans.verifications.verifier:id,name']);

        return Inertia::render('improvement/recommendations/show', [
            'recommendation' => [
                ...self::row($recommendation),
                'description' => $recommendation->description,
                'rationale' => $recommendation->rationale,
                'source' => $this->sourceLink($recommendation),
                'target' => $recommendation->target_type ? "{$recommendation->target_type}:{$recommendation->target_id}" : null,
                'closed_at' => $recommendation->closed_at?->toIso8601String(),
            ],
            'plans' => $recommendation->actionPlans->map(fn (ActionPlan $plan): array => [
                ...$plan->only(['id', 'title', 'description', 'target_output', 'pic_user_id', 'budget', 'progress', 'implementation_notes']),
                'status' => $plan->status->value,
                'status_label' => $plan->status->label(),
                'pic' => $plan->pic?->name,
                'starts_on' => $plan->starts_on?->toDateString(),
                'due_date' => $plan->due_date->toDateString(),
                'overdue' => $plan->isOverdue(),
                'tasks' => $plan->tasks->map(fn (ActionTask $task): array => [...$task->only(['id', 'title', 'is_done']), 'due_date' => $task->due_date?->toDateString()])->all(),
                'evidence' => EvidenceService::linkedPayload($plan->evidenceMappings, $user, EvidenceService::isLocked($plan)),
                'verifications' => $plan->verifications->map(fn ($v): array => ['decision' => $v->decision, 'notes' => $v->notes, 'verifier' => $v->verifier?->name, 'verified_at' => $v->verified_at->toIso8601String()])->all(),
                'can_update' => $plan->pic_user_id === $user->id || $user->can('improvement.manage'),
            ])->all(),
            ...$this->formOptions($request),
            'evidenceOptions' => EvidenceOptions::for($request),
            'can' => [
                'manage' => $user->can('improvement.manage'),
                'verify' => $user->can('improvement.verify'),
                'plan' => $user->can('improvement.manage') || $recommendation->pic_user_id === $user->id,
            ],
        ]);
    }

    public function update(Request $request, Recommendation $recommendation): RedirectResponse
    {
        $this->authorizeView($request, $recommendation);
        $validated = $this->validated($request);
        $target = ! empty($validated['target']) ? Auditee::resolve(...Auditee::parse($validated['target'])) : null;
        unset($validated['target'], $validated['origin']);
        $previousPic = $recommendation->pic_user_id;

        $recommendation->update([
            ...$validated,
            'target_type' => $target['type'] ?? $recommendation->target_type,
            'target_id' => $target['id'] ?? $recommendation->target_id,
            'target_name' => $target['name'] ?? $recommendation->target_name,
            'study_program_id' => $target ? $target['study_program_id'] : $recommendation->study_program_id,
            'faculty_id' => $target ? $target['faculty_id'] : $recommendation->faculty_id,
        ]);

        if ($recommendation->pic_user_id && $recommendation->pic_user_id !== $previousPic) {
            $recommendation->pic?->notify(new ImprovementNotification($recommendation, 'assigned'));
        }

        $this->toast('Rekomendasi diperbarui.');

        return back();
    }

    public function close(Request $request, Recommendation $recommendation, ImprovementWorkflow $workflow): RedirectResponse
    {
        $workflow->close($recommendation, $request->user());
        $this->toast('Rekomendasi ditutup. Siklus perbaikan selesai.');

        return back();
    }

    public function cancel(Request $request, Recommendation $recommendation): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        $recommendation->update(['status' => RecommendationStatus::Cancelled]);
        AuditLogger::log('status_changed', 'improvement', $recommendation, "Membatalkan rekomendasi {$recommendation->code}", null, ['reason' => $reason]);
        $this->toast('Rekomendasi dibatalkan.');

        return back();
    }

    /**
     * Pemantauan seluruh rencana aksi lintas rekomendasi.
     */
    public function monitoring(Request $request): Response
    {
        $user = $request->user();
        $visible = Recommendation::query()->visibleTo($user, $user->can('improvement.view') && ! $user->hasRole('dosen'))->select('id');

        $plans = ActionPlan::query()
            ->whereIn('recommendation_id', $visible)
            ->with(['recommendation:id,code,title,target_name,priority', 'pic:id,name'])
            ->withCount(['tasks', 'tasks as done_tasks_count' => fn ($q) => $q->where('is_done', true)])
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->overdue())
            ->when($request->boolean('mine'), fn (Builder $q) => $q->where('pic_user_id', $user->id))
            ->when($request->filled('status') && $request->input('status') !== 'all', fn (Builder $q) => $q->where('status', $request->input('status')))
            ->orderBy('due_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ActionPlan $plan): array => [
                ...$plan->only(['id', 'title', 'progress', 'tasks_count', 'done_tasks_count']),
                'status' => $plan->status->value,
                'status_label' => $plan->status->label(),
                'pic' => $plan->pic?->name,
                'due_date' => $plan->due_date->toDateString(),
                'overdue' => $plan->isOverdue(),
                'recommendation' => [
                    'id' => $plan->recommendation->id,
                    'code' => $plan->recommendation->code,
                    'title' => $plan->recommendation->title,
                    'target_name' => $plan->recommendation->target_name,
                    'priority' => $plan->recommendation->priority->value,
                    'priority_label' => $plan->recommendation->priority->label(),
                ],
            ]);

        return Inertia::render('improvement/monitoring', [
            'plans' => $plans,
            'filters' => ['status' => $request->input('status', 'all'), 'overdue' => $request->boolean('overdue'), 'mine' => $request->boolean('mine')],
            'statuses' => ActionPlanStatus::options(),
            'summary' => [
                'total' => ActionPlan::query()->whereIn('recommendation_id', $visible)->count(),
                'overdue' => ActionPlan::query()->whereIn('recommendation_id', $visible)->overdue()->count(),
                'completed' => ActionPlan::query()->whereIn('recommendation_id', $visible)->whereIn('status', [ActionPlanStatus::Completed, ActionPlanStatus::Verified])->count(),
                'avg_progress' => round((float) ActionPlan::query()->whereIn('recommendation_id', $visible)->avg('progress'), 1),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Recommendation $recommendation): array
    {
        $recommendation->loadMissing('pic:id,name');

        return [
            'id' => $recommendation->id,
            'code' => $recommendation->code,
            'title' => $recommendation->title,
            'origin' => $recommendation->origin->value,
            'origin_label' => $recommendation->origin->label(),
            'target_name' => $recommendation->target_name,
            'indicator' => $recommendation->indicator,
            'priority' => $recommendation->priority->value,
            'priority_label' => $recommendation->priority->label(),
            'status' => $recommendation->status->value,
            'status_label' => $recommendation->status->label(),
            'pic' => $recommendation->pic?->name,
            'pic_user_id' => $recommendation->pic_user_id,
            'due_date' => $recommendation->due_date?->toDateString(),
            'overdue' => $recommendation->due_date?->isPast() && in_array($recommendation->status, [RecommendationStatus::Open, RecommendationStatus::InProgress], true),
            'plans_count' => $recommendation->action_plans_count ?? null,
            'verified_plans_count' => $recommendation->verified_plans_count ?? null,
            'progress' => isset($recommendation->action_plans_avg_progress) ? round((float) $recommendation->action_plans_avg_progress) : null,
            'created_at' => $recommendation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{label: string, url: string|null}|null
     */
    private function sourceLink(Recommendation $recommendation): ?array
    {
        $source = $recommendation->source;

        return match ($recommendation->source_type) {
            'survey' => $source ? ['label' => "Monev: {$source->title}", 'url' => route('analytics.index', ['survey' => $source->id, 'study_program_id' => $recommendation->study_program_id])] : null,
            'finding' => $source ? ['label' => "Temuan {$source->code}", 'url' => route('ami.findings.show', $source)] : null,
            'accreditation_period' => $source ? ['label' => "Periode akreditasi {$source->code}", 'url' => route('accreditation.periods.show', $source)] : null,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'targets' => Auditee::options($request->user()),
            'picOptions' => User::query()->where('is_active', true)->whereDoesntHave('roles', fn ($q) => $q->where('name', 'mahasiswa'))->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])->all(),
            'priorityOptions' => Priority::options(),
        ];
    }

    private function authorizeView(Request $request, Recommendation $recommendation): void
    {
        $user = $request->user();
        abort_unless(Recommendation::query()->visibleTo($user, $user->can('improvement.view') && ! $user->hasRole('dosen'))->whereKey($recommendation->id)->exists(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'rationale' => ['nullable', 'string', 'max:2000'],
            'origin' => ['nullable', Rule::enum(RecommendationOrigin::class)],
            'target' => ['nullable', 'string'],
            'indicator' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'pic_user_id' => ['nullable', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);
    }
}
