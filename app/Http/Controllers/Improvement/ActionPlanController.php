<?php

namespace App\Http\Controllers\Improvement;

use App\Enums\ActionPlanStatus;
use App\Http\Controllers\Controller;
use App\Models\ActionPlan;
use App\Models\ActionTask;
use App\Models\Recommendation;
use App\Notifications\ImprovementNotification;
use App\Services\Improvement\ImprovementWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ActionPlanController extends Controller
{
    public function store(Request $request, Recommendation $recommendation, ImprovementWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('improvement.manage') || $recommendation->pic_user_id === $request->user()->id, 403);

        $validated = $this->validated($request);
        $tasks = $validated['tasks'] ?? [];
        unset($validated['tasks']);

        $plan = DB::transaction(function () use ($recommendation, $validated, $tasks, $request, $workflow): ActionPlan {
            $plan = $recommendation->actionPlans()->create([...$validated, 'status' => ActionPlanStatus::Planned, 'created_by' => $request->user()->id]);

            foreach (array_values(array_filter($tasks)) as $index => $title) {
                $plan->tasks()->create(['title' => $title, 'sort_order' => $index + 1]);
            }

            $workflow->sync($recommendation);

            return $plan;
        });

        if ($plan->pic_user_id !== $request->user()->id) {
            $plan->pic?->notify(new ImprovementNotification($recommendation, 'plan_assigned', null, $plan));
        }

        $this->toast('Rencana aksi ditambahkan.');

        return back();
    }

    public function update(Request $request, ActionPlan $plan, ImprovementWorkflow $workflow): RedirectResponse
    {
        $this->authorizeUpdate($request, $plan);

        $validated = $request->validate([
            ...collect($this->rules())->except(['tasks', 'tasks.*'])->all(),
            'progress' => ['required', 'integer', 'between:0,100'],
            'implementation_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $completed = (int) $validated['progress'] === 100;
        $plan->update([
            ...$validated,
            'status' => $completed ? ActionPlanStatus::Completed : ($validated['progress'] > 0 ? ActionPlanStatus::InProgress : ActionPlanStatus::Planned),
            'completed_at' => $completed ? ($plan->completed_at ?? now()) : null,
        ]);
        $workflow->sync($plan->recommendation);

        $this->toast($completed ? 'Rencana aksi selesai. Lampirkan bukti untuk diverifikasi.' : 'Progres diperbarui.');

        return back();
    }

    public function destroy(Request $request, ActionPlan $plan, ImprovementWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('improvement.manage') && $plan->status === ActionPlanStatus::Planned, 403);
        $recommendation = $plan->recommendation;
        $plan->delete();
        $workflow->sync($recommendation);
        $this->toast('Rencana aksi dihapus.');

        return back();
    }

    public function toggleTask(Request $request, ActionPlan $plan, ActionTask $task, ImprovementWorkflow $workflow): RedirectResponse
    {
        $this->authorizeUpdate($request, $plan);
        abort_unless($task->action_plan_id === $plan->id, 404);

        $task->update(['is_done' => ! $task->is_done, 'done_at' => $task->is_done ? null : now()]);

        // Progres otomatis mengikuti proporsi tugas selesai bila rencana memiliki tugas.
        $total = $plan->tasks()->count();
        $done = $plan->tasks()->where('is_done', true)->count();
        $progress = $total ? (int) round($done / $total * 100) : $plan->progress;

        $plan->update([
            'progress' => $progress,
            'status' => $progress === 100 ? ActionPlanStatus::Completed : ($progress > 0 ? ActionPlanStatus::InProgress : ActionPlanStatus::Planned),
            'completed_at' => $progress === 100 ? now() : null,
        ]);
        $workflow->sync($plan->recommendation);

        return back();
    }

    public function addTask(Request $request, ActionPlan $plan): RedirectResponse
    {
        $this->authorizeUpdate($request, $plan);
        $validated = $request->validate(['title' => ['required', 'string', 'max:255'], 'due_date' => ['nullable', 'date']]);
        $plan->tasks()->create([...$validated, 'sort_order' => (int) $plan->tasks()->max('sort_order') + 1]);

        return back();
    }

    public function verify(Request $request, ActionPlan $plan, ImprovementWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'notes' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);

        $workflow->verifyPlan($plan, $request->user(), $validated['decision'] === 'accepted', $validated['notes'] ?? null);
        $this->toast($validated['decision'] === 'accepted' ? 'Rencana aksi terverifikasi.' : 'Rencana aksi dikembalikan untuk diperbaiki.');

        return back();
    }

    private function authorizeUpdate(Request $request, ActionPlan $plan): void
    {
        abort_unless($plan->pic_user_id === $request->user()->id || $request->user()->can('improvement.manage'), 403, 'Hanya PIC rencana aksi yang dapat memperbarui progres.');

        if ($plan->status === ActionPlanStatus::Verified) {
            $this->failWith('Rencana aksi yang sudah terverifikasi terkunci.');
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'target_output' => ['nullable', 'string', 'max:255'],
            'pic_user_id' => ['required', 'exists:users,id'],
            'starts_on' => ['nullable', 'date'],
            'due_date' => ['required', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'tasks' => ['array'],
            'tasks.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate($this->rules(), ['pic_user_id.required' => 'Tentukan PIC rencana aksi.']);
    }
}
