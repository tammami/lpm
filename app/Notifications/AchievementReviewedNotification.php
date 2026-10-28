<?php

namespace App\Notifications;

use App\Enums\AchievementStatus;
use App\Models\StudentAchievement;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Hasil verifikasi prestasi oleh bidang kemahasiswaan.
 */
class AchievementReviewedNotification extends Notification
{
    public function __construct(public StudentAchievement $achievement) {}

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
            'title' => match ($this->achievement->status) {
                AchievementStatus::Verified => 'Prestasi Anda sudah diverifikasi',
                AchievementStatus::Rejected => 'Prestasi Anda ditolak',
                default => 'Data prestasi perlu diperbaiki',
            },
            'body' => Str::limit($this->achievement->title, 80).($this->achievement->verification_note ? " — {$this->achievement->verification_note}" : ''),
            'url' => route('achievements.mine.index'),
            'icon' => 'achievement',
            'tone' => match ($this->achievement->status) {
                AchievementStatus::Verified => 'success',
                AchievementStatus::Rejected => 'danger',
                default => 'warning',
            },
        ];
    }
}
