<?php

namespace App\Notifications;

use App\Models\Evidence;
use Illuminate\Notifications\Notification;

class EvidenceRejectedNotification extends Notification
{
    public function __construct(public Evidence $evidence, public ?string $reason) {}

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
            'title' => "Dokumen {$this->evidence->code} ditolak",
            'body' => $this->reason ? "Alasan: {$this->reason}" : 'Periksa kembali dokumen dan unggah versi perbaikan.',
            'url' => route('evidence.show', $this->evidence),
            'icon' => 'evidence',
            'tone' => 'danger',
        ];
    }
}
