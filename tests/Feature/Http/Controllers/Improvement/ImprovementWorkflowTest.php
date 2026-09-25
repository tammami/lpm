<?php

use App\Enums\ActionPlanStatus;
use App\Enums\RecommendationStatus;
use App\Enums\UserRole;
use App\Models\ActionPlan;
use App\Models\Recommendation;
use App\Models\StudyProgram;
use App\Services\Evidence\EvidenceService;

beforeEach(function () {
    $this->program = StudyProgram::factory()->create(['accreditation_valid_until' => now()->addDays(90)->toDateString()]);
    $this->lpm = userWithRole(UserRole::AdminLpm);
    $this->kaprodi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);
});

function createRecommendation(): Recommendation
{
    test()->actingAs(test()->lpm)->post(route('improvement.recommendations.store'), [
        'title' => 'Perbaiki umpan balik penilaian',
        'description' => 'Dosen memberikan umpan balik tertulis atas tugas mahasiswa.',
        'target' => 'study_program:'.test()->program->id,
        'priority' => 'high',
        'pic_user_id' => test()->kaprodi->id,
        'due_date' => now()->addDays(30)->toDateString(),
    ])->assertRedirect();

    return Recommendation::query()->sole();
}

it('proposes candidates from rules and accepts each rule only once', function () {
    $key = "accreditation:{$this->program->id}:".now()->addDays(90)->toDateString();

    $this->actingAs($this->lpm)->get(route('improvement.recommendations.generate'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('candidates.0.rule_key', $key)->where('candidates.0.pic_user_id', $this->kaprodi->id));

    $accept = fn () => $this->actingAs($this->lpm)->post(route('improvement.recommendations.accept'), [
        'selected' => [['rule_key' => $key, 'pic_user_id' => null, 'priority' => 'high']],
    ]);

    $accept()->assertRedirect(route('improvement.recommendations.index'));
    $accept()->assertRedirect();

    $recommendation = Recommendation::query()->sole();
    expect($recommendation->rule_key)->toBe($key)
        ->and($recommendation->pic_user_id)->toBe($this->kaprodi->id)
        ->and($recommendation->status)->toBe(RecommendationStatus::Open)
        ->and($this->kaprodi->notifications()->count())->toBe(1);
});

it('runs a recommendation through plan, tasks, evidence, verification and closure', function () {
    $recommendation = createRecommendation();

    $this->actingAs($this->kaprodi)->post(route('improvement.action-plans.store', $recommendation), [
        'title' => 'Workshop umpan balik',
        'pic_user_id' => $this->kaprodi->id,
        'due_date' => now()->addDays(20)->toDateString(),
        'tasks' => ['Susun TOR', 'Laksanakan workshop', ''],
    ])->assertRedirect();

    $plan = ActionPlan::query()->sole();
    expect($plan->tasks()->count())->toBe(2)
        ->and($recommendation->fresh()->status)->toBe(RecommendationStatus::InProgress);

    foreach ($plan->tasks as $task) {
        $this->actingAs($this->kaprodi)->post(route('improvement.action-plans.tasks.toggle', [$plan, $task]))->assertRedirect();
    }

    expect($plan->fresh()->progress)->toBe(100)
        ->and($plan->fresh()->status)->toBe(ActionPlanStatus::Completed)
        ->and($recommendation->fresh()->status)->toBe(RecommendationStatus::Completed);

    // Verifikasi ditolak bila belum ada bukti pelaksanaan.
    $this->actingAs($this->lpm)->post(route('improvement.action-plans.verify', $plan), ['decision' => 'accepted'])
        ->assertSessionHasErrors('plan');

    $service = app(EvidenceService::class);
    $evidence = $service->create(['title' => 'Daftar hadir workshop'], $this->kaprodi, url: 'https://example.test/hadir');
    $service->map($evidence, 'action_plan', $plan->id, $this->kaprodi);

    $this->actingAs($this->lpm)->post(route('improvement.action-plans.verify', $plan), ['decision' => 'accepted'])->assertRedirect();
    expect($plan->fresh()->status)->toBe(ActionPlanStatus::Verified)
        ->and($recommendation->fresh()->status)->toBe(RecommendationStatus::Verified);

    // Rencana aksi terverifikasi terkunci.
    $this->actingAs($this->kaprodi)->post(route('improvement.action-plans.tasks.toggle', [$plan, $plan->tasks->first()]))
        ->assertRedirect();
    expect($plan->fresh()->progress)->toBe(100);

    $this->actingAs($this->lpm)->post(route('improvement.recommendations.close', $recommendation))->assertRedirect();
    expect($recommendation->fresh()->status)->toBe(RecommendationStatus::Closed);
});

it('requires notes to return a plan and restricts updates to the PIC or managers', function () {
    $recommendation = createRecommendation();
    $plan = $recommendation->actionPlans()->create([
        'title' => 'Pelatihan', 'pic_user_id' => $this->kaprodi->id, 'due_date' => now()->addDays(5)->toDateString(),
        'status' => ActionPlanStatus::Completed, 'progress' => 100,
    ]);

    $this->actingAs($this->lpm)->post(route('improvement.action-plans.verify', $plan), ['decision' => 'rejected'])
        ->assertSessionHasErrors('notes');

    $this->actingAs($this->lpm)->post(route('improvement.action-plans.verify', $plan), ['decision' => 'rejected', 'notes' => 'Lampirkan notulen.'])->assertRedirect();
    expect($plan->fresh()->status)->toBe(ActionPlanStatus::Rejected);

    $lecturer = userWithRole(UserRole::Dosen);
    $this->actingAs($lecturer)->put(route('improvement.action-plans.update', $plan), [
        'title' => 'Pelatihan', 'pic_user_id' => $lecturer->id, 'due_date' => now()->toDateString(), 'progress' => 50,
    ])->assertForbidden();

    $this->actingAs($this->kaprodi)->get(route('improvement.action-plans.index', ['mine' => 1]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('plans.data.0.id', $plan->id)->where('summary.total', 1));
});

it('locks evidence of a verified plan on the server', function () {
    $recommendation = createRecommendation();
    $plan = $recommendation->actionPlans()->create([
        'title' => 'Pelatihan', 'pic_user_id' => $this->kaprodi->id, 'due_date' => now()->addDays(5)->toDateString(),
        'status' => ActionPlanStatus::Completed, 'progress' => 100,
    ]);
    $service = app(EvidenceService::class);
    $evidence = $service->create(['title' => 'Notulen'], $this->kaprodi, url: 'https://example.test/notulen');
    $mapping = $service->map($evidence, 'action_plan', $plan->id, $this->kaprodi);

    $this->actingAs($this->lpm)->post(route('improvement.action-plans.verify', $plan), ['decision' => 'accepted'])->assertRedirect();

    $this->actingAs($this->kaprodi)->delete(route('evidence.unmap', $mapping))->assertSessionHasErrors('mappable_id');
    $this->actingAs($this->kaprodi)->post(route('evidence.map'), ['evidence_id' => $evidence->id, 'mappable_type' => 'action_plan', 'mappable_id' => $plan->id, 'context_id' => 99])
        ->assertSessionHasErrors('mappable_id');
    expect($plan->evidenceMappings()->count())->toBe(1);
});
