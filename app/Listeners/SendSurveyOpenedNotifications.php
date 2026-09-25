<?php

namespace App\Listeners;

use App\Events\SurveyOpened;
use App\Models\User;
use App\Notifications\SurveyOpenedNotification;
use App\Services\Monev\EligibilityService;
use Illuminate\Support\Facades\Notification;

/**
 * Beri tahu seluruh responden eligible saat Monev dibuka.
 */
class SendSurveyOpenedNotifications
{
    public function __construct(private EligibilityService $eligibility) {}

    public function handle(SurveyOpened $event): void
    {
        $this->eligibility->targetCountsByUser($event->survey)->keys()->chunk(200)->each(function ($ids) use ($event): void {
            Notification::send(User::query()->whereIn('id', $ids)->where('is_active', true)->get(), new SurveyOpenedNotification($event->survey));
        });
    }
}
