<?php

namespace App\Notifications;

use App\Models\ScientificWork;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Dosen ditandai sebagai penulis bersama pada karya yang diisi dosen lain.
 */
class ScientificWorkTaggedNotification extends Notification
{
    public function __construct(public ScientificWork $work, public string $taggedBy) {}

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
            'title' => 'Anda ditandai sebagai penulis bersama',
            'body' => "{$this->taggedBy} mencatat karya \"".Str::limit($this->work->title, 80).'" dan menandai Anda.',
            'url' => route('research.mine.index', ['tab' => 'ditandai']),
            'icon' => 'research',
            'tone' => 'info',
        ];
    }
}
