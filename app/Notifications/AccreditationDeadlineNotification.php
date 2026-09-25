<?php

namespace App\Notifications;

use App\Models\AccreditationPeriod;
use Illuminate\Notifications\Notification;

class AccreditationDeadlineNotification extends Notification
{
    public function __construct(public AccreditationPeriod $period, public int $daysLeft, public float $readiness) {}

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
            'title' => $this->daysLeft > 0 ? "Batas pengajuan akreditasi {$this->daysLeft} hari lagi" : 'Hari ini batas pengajuan dokumen akreditasi',
            'body' => "{$this->period->name} — kesiapan dokumen ".number_format($this->readiness, 0, ',', '.').'%.',
            'url' => route('accreditation.periods.show', $this->period),
            'icon' => 'accreditation',
            'tone' => $this->daysLeft > 7 ? 'warning' : 'danger',
        ];
    }
}
