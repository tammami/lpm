<?php

namespace App\Notifications;

use App\Models\ActionPlan;
use App\Models\Recommendation;
use Illuminate\Notifications\Notification;

class ImprovementNotification extends Notification
{
    public function __construct(public Recommendation $recommendation, public string $event, public ?string $notes = null, public ?ActionPlan $plan = null) {}

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
        [$title, $body, $tone] = match ($this->event) {
            'assigned' => ["Rekomendasi untuk ditindaklanjuti: {$this->recommendation->code}", $this->recommendation->title, 'primary'],
            'plan_assigned' => ['Rencana aksi baru ditugaskan kepada Anda', $this->plan?->title ?? $this->recommendation->title, 'primary'],
            'plan_verified' => ['Rencana aksi terverifikasi', $this->plan?->title ?? '', 'primary'],
            'plan_rejected' => ['Rencana aksi perlu perbaikan', $this->notes ? "Catatan: {$this->notes}" : ($this->plan?->title ?? ''), 'warning'],
            'overdue' => ['Rencana aksi melewati tenggat', $this->plan?->title ?? $this->recommendation->title, 'danger'],
            default => ["Pembaruan rekomendasi {$this->recommendation->code}", $this->recommendation->title, 'info'],
        };

        return ['title' => $title, 'body' => $body, 'url' => route('improvement.recommendations.show', $this->recommendation), 'icon' => 'finding', 'tone' => $tone];
    }
}
