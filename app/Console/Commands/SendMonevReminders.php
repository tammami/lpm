<?php

namespace App\Console\Commands;

use App\Models\Survey;
use App\Models\User;
use App\Notifications\SurveyReminderNotification;
use App\Services\Monev\EligibilityService;
use App\Services\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

#[Signature('monev:remind')]
#[Description('Kirim pengingat kepada responden yang belum menyelesaikan Monev menjelang batas waktu')]
class SendMonevReminders extends Command
{
    public function handle(EligibilityService $eligibility): int
    {
        $window = (int) Settings::get('monev.reminder_days_before_close', 3);
        $sent = 0;

        Survey::query()->open()->where('ends_at', '<=', now()->addDays($window)->endOfDay())->get()
            ->each(function (Survey $survey) use ($eligibility, &$sent): void {
                $daysLeft = max(1, (int) ceil(now()->diffInHours($survey->ends_at) / 24));

                $eligibility->pendingUserIds($survey)->chunk(200)->each(function ($ids) use ($survey, $daysLeft, &$sent): void {
                    $users = User::query()->whereIn('id', $ids)->where('is_active', true)->get();
                    Notification::send($users, new SurveyReminderNotification($survey, $daysLeft));
                    $sent += $users->count();
                });
            });

        $this->info("Pengingat terkirim ke {$sent} responden.");

        return self::SUCCESS;
    }
}
