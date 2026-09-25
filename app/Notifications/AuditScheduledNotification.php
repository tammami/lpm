<?php

namespace App\Notifications;

use App\Models\Audit;
use Illuminate\Notifications\Notification;

class AuditScheduledNotification extends Notification
{
    public function __construct(public Audit $audit) {}

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
            'title' => "AMI dijadwalkan: {$this->audit->auditee_name}",
            'body' => 'Audit '.$this->audit->code.' pada '.$this->audit->scheduled_on?->translatedFormat('d F Y').($this->audit->location ? " di {$this->audit->location}" : '').'.',
            'url' => route('ami.audits.show', $this->audit),
            'icon' => 'finding',
            'tone' => 'info',
        ];
    }
}
