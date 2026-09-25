<?php

namespace App\Services\Ami;

use App\Enums\CorrectiveActionStatus;
use App\Enums\FindingStatus;
use App\Models\CorrectiveAction;
use App\Models\Finding;
use App\Models\User;
use App\Models\Verification;
use App\Notifications\FindingNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Siklus temuan: diterbitkan → rencana tindakan koreksi → pelaksanaan → verifikasi → ditutup.
 */
class FindingWorkflow
{
    /**
     * Terbitkan temuan ke auditee (OPEN → ACTION REQUIRED).
     */
    public function issue(Finding $finding, User $actor): void
    {
        $this->expect($finding, [FindingStatus::Open]);

        if (! $finding->pic_user_id) {
            throw ValidationException::withMessages(['pic_user_id' => 'Tentukan PIC auditee sebelum temuan diterbitkan.']);
        }

        $finding->update(['status' => FindingStatus::ActionRequired, 'issued_at' => now()]);
        AuditLogger::log('issued', 'ami', $finding, "Menerbitkan temuan {$finding->code} kepada {$finding->auditee_name}");

        $finding->pic?->notify(new FindingNotification($finding, 'issued'));
    }

    /**
     * Tambah rencana tindakan koreksi (BR-005: wajib PIC). Temuan menjadi IN PROGRESS.
     *
     * @param  array<string, mixed>  $data
     */
    public function addCorrectiveAction(Finding $finding, array $data, User $actor): CorrectiveAction
    {
        $this->expect($finding, [FindingStatus::ActionRequired, FindingStatus::InProgress]);

        return DB::transaction(function () use ($finding, $data, $actor): CorrectiveAction {
            $action = $finding->correctiveActions()->create([...$data, 'status' => CorrectiveActionStatus::Planned, 'created_by' => $actor->id]);

            if ($finding->status === FindingStatus::ActionRequired) {
                $finding->update(['status' => FindingStatus::InProgress]);
            }

            return $action;
        });
    }

    /**
     * Ajukan verifikasi: seluruh tindakan koreksi harus sudah selesai.
     */
    public function submit(Finding $finding, User $actor): void
    {
        $this->expect($finding, [FindingStatus::InProgress]);
        $actions = $finding->correctiveActions()->get();

        if ($actions->isEmpty()) {
            throw ValidationException::withMessages(['finding' => 'Susun minimal satu tindakan koreksi terlebih dahulu.']);
        }

        if ($actions->contains(fn (CorrectiveAction $action) => $action->status !== CorrectiveActionStatus::Completed && $action->status !== CorrectiveActionStatus::Verified)) {
            throw ValidationException::withMessages(['finding' => 'Seluruh tindakan koreksi harus ditandai selesai beserta buktinya.']);
        }

        $finding->update(['status' => FindingStatus::Submitted]);
        AuditLogger::log('status_changed', 'ami', $finding, "Mengajukan verifikasi temuan {$finding->code}");

        $auditors = $finding->audit?->auditors()->with('user')->get()->pluck('user')->filter() ?? collect();
        Notification::send($auditors, new FindingNotification($finding, 'submitted'));
    }

    /**
     * Keputusan verifikasi auditor. Ditolak → kembali ke IN PROGRESS.
     */
    public function verify(Finding $finding, User $auditor, bool $accepted, ?string $notes): void
    {
        $this->expect($finding, [FindingStatus::Submitted]);

        DB::transaction(function () use ($finding, $auditor, $accepted, $notes): void {
            Verification::query()->create([
                'verifiable_type' => $finding->getMorphClass(),
                'verifiable_id' => $finding->id,
                'verifier_id' => $auditor->id,
                'decision' => $accepted ? 'accepted' : 'rejected',
                'notes' => $notes,
                'verified_at' => now(),
            ]);

            $finding->correctiveActions()->where('status', CorrectiveActionStatus::Completed)
                ->update(['status' => $accepted ? CorrectiveActionStatus::Verified : CorrectiveActionStatus::Rejected]);

            $finding->update($accepted
                ? ['status' => FindingStatus::Verified, 'verified_at' => now()]
                : ['status' => FindingStatus::InProgress]);
        });

        AuditLogger::log('verified', 'ami', $finding, ($accepted ? 'Memverifikasi' : 'Menolak').' tindak lanjut temuan '.$finding->code, null, ['notes' => $notes]);
        $finding->pic?->notify(new FindingNotification($finding, $accepted ? 'verified' : 'rejected', $notes));
    }

    /**
     * Tutup temuan. Temuan yang menuntut tindakan koreksi wajib terverifikasi (BR-006).
     */
    public function close(Finding $finding, User $actor, ?string $notes = null): void
    {
        $finding->loadMissing('severity');

        if ($finding->severity->requires_corrective_action) {
            $this->expect($finding, [FindingStatus::Verified]);
        } else {
            $this->expect($finding, [FindingStatus::Open, FindingStatus::ActionRequired, FindingStatus::InProgress, FindingStatus::Verified]);
        }

        $finding->update(['status' => FindingStatus::Closed, 'closed_at' => now(), 'closed_by' => $actor->id]);
        AuditLogger::log('closed', 'ami', $finding, "Menutup temuan {$finding->code}", null, ['notes' => $notes]);
    }

    /**
     * @param  list<FindingStatus>  $allowed
     */
    private function expect(Finding $finding, array $allowed): void
    {
        if (! in_array($finding->status, $allowed, true)) {
            throw ValidationException::withMessages(['finding' => "Tindakan ini tidak dapat dilakukan pada temuan berstatus \"{$finding->status->label()}\"."]);
        }
    }
}
