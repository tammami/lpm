<?php

namespace App\Notifications;

use App\Models\Survey;
use Illuminate\Notifications\Notification;

class SurveyOpenedNotification extends Notification
{
    public function __construct(public Survey $survey) {}

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
            'title' => 'Monev dibuka: '.$this->survey->title,
            'body' => 'Silakan isi evaluasi sebelum '.$this->survey->ends_at->translatedFormat('d F Y H:i').'. Jawaban Anda anonim.',
            'url' => route('portal.home'),
            'icon' => 'survey',
            'tone' => 'primary',
        ];
    }
}
