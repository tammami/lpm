<?php

namespace App\Notifications;

use App\Models\ScientificWork;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ScientificWorkRevisionNotification extends Notification
{
    public function __construct(public ScientificWork $work) {}

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
            'title' => 'Data karya ilmiah perlu diperbaiki',
            'body' => Str::limit($this->work->title, 80).($this->work->verification_note ? " — {$this->work->verification_note}" : ''),
            'url' => route('research.mine.index'),
            'icon' => 'research',
            'tone' => 'warning',
        ];
    }
}
