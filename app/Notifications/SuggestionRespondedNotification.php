<?php

namespace App\Notifications;

use App\Models\Suggestion;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * LPM menanggapi saran yang dikirim pengguna.
 */
class SuggestionRespondedNotification extends Notification
{
    public function __construct(public Suggestion $suggestion) {}

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
            'title' => 'LPM menanggapi saran Anda',
            'body' => Str::limit($this->suggestion->subject, 60).' — '.Str::limit((string) $this->suggestion->response, 100),
            'url' => route('suggestions.mine.index'),
            'icon' => 'suggestion',
            'tone' => 'success',
        ];
    }
}
