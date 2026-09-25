<?php

namespace App\Notifications;

use App\Models\Finding;
use Illuminate\Notifications\Notification;

class FindingNotification extends Notification
{
    public function __construct(public Finding $finding, public string $event, public ?string $notes = null) {}

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
            'issued' => ["Temuan baru: {$this->finding->code}", "{$this->finding->title}. Susun tindakan koreksi sebelum ".($this->finding->due_date?->translatedFormat('d F Y') ?? 'tenggat').'.', 'danger'],
            'submitted' => ["Verifikasi diminta: {$this->finding->code}", "Auditee telah menyelesaikan tindakan koreksi untuk \"{$this->finding->title}\".", 'info'],
            'verified' => ["Tindak lanjut diterima: {$this->finding->code}", 'Auditor memverifikasi tindakan koreksi Anda.', 'primary'],
            'rejected' => ["Tindak lanjut perlu perbaikan: {$this->finding->code}", $this->notes ? "Catatan auditor: {$this->notes}" : 'Periksa catatan auditor.', 'warning'],
            'overdue' => ["Temuan melewati tenggat: {$this->finding->code}", "\"{$this->finding->title}\" belum diselesaikan.", 'danger'],
            default => ["Pembaruan temuan {$this->finding->code}", $this->finding->title, 'primary'],
        };

        return ['title' => $title, 'body' => $body, 'url' => route('ami.findings.show', $this->finding), 'icon' => 'finding', 'tone' => $tone];
    }
}
