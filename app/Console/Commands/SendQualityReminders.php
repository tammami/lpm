<?php

namespace App\Console\Commands;

use App\Enums\AccreditationPeriodStatus;
use App\Models\AccreditationPeriod;
use App\Models\ActionPlan;
use App\Models\Evidence;
use App\Models\Finding;
use App\Notifications\AccreditationDeadlineNotification;
use App\Notifications\EvidenceExpiringNotification;
use App\Notifications\FindingNotification;
use App\Notifications\ImprovementNotification;
use App\Services\Accreditation\ReadinessCalculator;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quality:remind')]
#[Description('Ingatkan PIC atas temuan AMI & rencana aksi lewat tenggat, dokumen bukti yang akan kedaluwarsa, dan batas pengajuan akreditasi')]
class SendQualityReminders extends Command
{
    /**
     * Hari sebelum kedaluwarsa dokumen / batas pengajuan akreditasi saat pengingat dikirim.
     */
    private const EXPIRY_NOTICES = [30, 7, 0];

    public function handle(): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        Finding::query()->overdue()->with(['pic', 'audit:id,code'])->get()
            ->filter(fn (Finding $finding): bool => $this->isReminderDay($finding->due_date, $today))
            ->each(function (Finding $finding) use (&$sent): void {
                $finding->pic?->notify(new FindingNotification($finding, 'overdue'));
                $sent += (int) (bool) $finding->pic;
            });

        ActionPlan::query()->overdue()->with(['pic', 'recommendation'])->get()
            ->filter(fn (ActionPlan $plan): bool => $this->isReminderDay($plan->due_date, $today))
            ->each(function (ActionPlan $plan) use (&$sent): void {
                $plan->pic?->notify(new ImprovementNotification($plan->recommendation, 'overdue', null, $plan));
                $sent += (int) (bool) $plan->pic;
            });

        Evidence::query()
            ->whereIn('valid_until', array_map(fn (int $days): string => $today->copy()->addDays($days)->toDateString(), self::EXPIRY_NOTICES))
            ->with('owner')
            ->get()
            ->each(function (Evidence $evidence) use ($today, &$sent): void {
                $evidence->owner?->notify(new EvidenceExpiringNotification($evidence, (int) $today->diffInDays($evidence->valid_until, false)));
                $sent += (int) (bool) $evidence->owner;
            });

        AccreditationPeriod::query()
            ->where('status', AccreditationPeriodStatus::Preparing)
            ->whereIn('submission_deadline', array_map(fn (int $days): string => $today->copy()->addDays($days)->toDateString(), self::EXPIRY_NOTICES))
            ->with(['pic', 'version'])
            ->get()
            ->each(function (AccreditationPeriod $period) use ($today, &$sent): void {
                $period->pic?->notify(new AccreditationDeadlineNotification(
                    $period,
                    (int) $today->diffInDays($period->submission_deadline, false),
                    ReadinessCalculator::for($period)->summary()['readiness'],
                ));
                $sent += (int) (bool) $period->pic;
            });

        $this->info("{$sent} pengingat mutu terkirim.");

        return self::SUCCESS;
    }

    /**
     * Pengingat lewat tenggat dikirim sehari setelah tenggat lalu setiap 7 hari, agar tidak membanjiri PIC.
     */
    private function isReminderDay(?CarbonInterface $due, CarbonInterface $today): bool
    {
        if (! $due) {
            return false;
        }

        $late = (int) $due->copy()->startOfDay()->diffInDays($today, false);

        return $late === 1 || ($late > 1 && $late % 7 === 1);
    }
}
