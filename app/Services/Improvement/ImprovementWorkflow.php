<?php

namespace App\Services\Improvement;

use App\Enums\ActionPlanStatus;
use App\Enums\RecommendationStatus;
use App\Models\ActionPlan;
use App\Models\Recommendation;
use App\Models\User;
use App\Models\Verification;
use App\Notifications\ImprovementNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rekomendasi → rencana aksi → pelaksanaan → bukti → verifikasi → ditutup (BRD §47).
 */
class ImprovementWorkflow
{
    /**
     * Sinkronkan status rekomendasi dari status rencana aksinya.
     */
    public function sync(Recommendation $recommendation): void
    {
        if (in_array($recommendation->status, [RecommendationStatus::Closed, RecommendationStatus::Cancelled], true)) {
            return;
        }

        $plans = $recommendation->actionPlans()->get();

        $status = match (true) {
            $plans->isEmpty() => RecommendationStatus::Open,
            $plans->every(fn (ActionPlan $p) => $p->status === ActionPlanStatus::Verified) => RecommendationStatus::Verified,
            $plans->every(fn (ActionPlan $p) => in_array($p->status, [ActionPlanStatus::Completed, ActionPlanStatus::Verified], true)) => RecommendationStatus::Completed,
            default => RecommendationStatus::InProgress,
        };

        if ($recommendation->status !== $status) {
            $recommendation->update(['status' => $status]);
        }
    }

    public function verifyPlan(ActionPlan $plan, User $verifier, bool $accepted, ?string $notes): void
    {
        if ($plan->status !== ActionPlanStatus::Completed) {
            throw ValidationException::withMessages(['plan' => 'Hanya rencana aksi yang sudah selesai yang dapat diverifikasi.']);
        }

        if ($accepted && ! $plan->evidenceMappings()->exists()) {
            throw ValidationException::withMessages(['plan' => 'Lampirkan minimal satu bukti pelaksanaan sebelum rencana aksi diverifikasi.']);
        }

        DB::transaction(function () use ($plan, $verifier, $accepted, $notes): void {
            Verification::query()->create([
                'verifiable_type' => $plan->getMorphClass(),
                'verifiable_id' => $plan->id,
                'verifier_id' => $verifier->id,
                'decision' => $accepted ? 'accepted' : 'rejected',
                'notes' => $notes,
                'verified_at' => now(),
            ]);

            $plan->update(['status' => $accepted ? ActionPlanStatus::Verified : ActionPlanStatus::Rejected]);
            $this->sync($plan->recommendation);
        });

        AuditLogger::log('verified', 'improvement', $plan, ($accepted ? 'Memverifikasi' : 'Menolak').' rencana aksi '.$plan->title, null, ['notes' => $notes]);
        $plan->pic?->notify(new ImprovementNotification($plan->recommendation, $accepted ? 'plan_verified' : 'plan_rejected', $notes, $plan));
    }

    public function close(Recommendation $recommendation, User $actor): void
    {
        if ($recommendation->status !== RecommendationStatus::Verified) {
            throw ValidationException::withMessages(['recommendation' => 'Rekomendasi hanya dapat ditutup setelah seluruh rencana aksi terverifikasi.']);
        }

        $recommendation->update(['status' => RecommendationStatus::Closed, 'closed_at' => now()]);
        AuditLogger::log('closed', 'improvement', $recommendation, "Menutup rekomendasi {$recommendation->code}");
    }
}
