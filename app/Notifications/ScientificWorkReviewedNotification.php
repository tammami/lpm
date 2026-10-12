<?php

namespace App\Notifications;

use App\Enums\ScientificWorkStatus;
use App\Models\ScientificWork;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Hasil verifikasi LPM yang perlu ditindaklanjuti dosen: valid dengan perbaikan, tidak valid, atau duplikat.
 */
class ScientificWorkReviewedNotification extends Notification
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
            'title' => match ($this->work->status) {
                ScientificWorkStatus::Invalid => 'Karya ilmiah dinyatakan tidak valid',
                ScientificWorkStatus::Duplicate => 'Karya ilmiah ditandai duplikat',
                default => 'Data karya ilmiah perlu diperbaiki',
            },
            'body' => Str::limit($this->work->title, 80).($this->work->verification_note ? " — {$this->work->verification_note}" : ''),
            'url' => route('research.mine.index'),
            'icon' => 'research',
            'tone' => $this->work->status === ScientificWorkStatus::Revision ? 'warning' : 'danger',
        ];
    }
}
