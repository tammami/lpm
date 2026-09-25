<?php

namespace App\Notifications;

use App\Models\Evidence;
use Illuminate\Notifications\Notification;

class EvidenceExpiringNotification extends Notification
{
    public function __construct(public Evidence $evidence, public int $daysLeft) {}

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
            'title' => $this->daysLeft > 0 ? "Dokumen {$this->evidence->code} kedaluwarsa dalam {$this->daysLeft} hari" : "Dokumen {$this->evidence->code} kedaluwarsa hari ini",
            'body' => "\"{$this->evidence->title}\" — unggah versi terbaru agar bukti tetap berlaku.",
            'url' => route('evidence.show', $this->evidence),
            'icon' => 'evidence',
            'tone' => $this->daysLeft > 0 ? 'warning' : 'danger',
        ];
    }
}
