<?php

namespace App\Notifications;

use App\Models\Survey;
use Illuminate\Notifications\Notification;

class SurveyReminderNotification extends Notification
{
    public function __construct(public Survey $survey, public int $daysLeft) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->daysLeft <= 1 ? 'Hari terakhir mengisi Monev' : "Monev berakhir {$this->daysLeft} hari lagi",
            'body' => "Masih ada evaluasi pada \"{$this->survey->title}\" yang belum Anda isi.",
            'url' => route('portal.home'),
            'icon' => 'reminder',
            'tone' => 'warning',
        ];
    }
}
