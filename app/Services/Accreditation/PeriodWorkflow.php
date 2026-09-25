<?php

namespace App\Services\Accreditation;

use App\Enums\AccreditationPeriodStatus;
use App\Models\AccreditationPeriod;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tahapan periode akreditasi: penyusunan → diajukan → asesmen lapangan → keputusan.
 * Keputusan menyinkronkan status akreditasi prodi (sumber data dashboard & mesin rekomendasi).
 */
class PeriodWorkflow
{
    public function advance(AccreditationPeriod $period, ?string $date = null): void
    {
        $next = $period->status->next();

        if (! $next || $next === AccreditationPeriodStatus::Decided) {
            throw ValidationException::withMessages(['status' => $next ? 'Isi hasil keputusan untuk menyelesaikan periode.' : 'Periode sudah selesai.']);
        }

        $period->update([
            'status' => $next,
            'submitted_on' => $next === AccreditationPeriodStatus::Submitted ? ($date ?? now()->toDateString()) : $period->submitted_on,
            'visit_on' => $next === AccreditationPeriodStatus::Visitation ? ($date ?? $period->visit_on ?? now()->toDateString()) : $period->visit_on,
        ]);

        AuditLogger::log('status_changed', 'accreditation', $period, "Periode {$period->code}: {$next->label()}");
    }

    /**
     * @param  array{result_grade: string, result_score?: float|null, sk_number?: string|null, certificate_number?: string|null, decided_on: string, valid_from?: string|null, valid_until: string}  $result
     */
    public function decide(AccreditationPeriod $period, array $result): void
    {
        if (! $period->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Periode ini sudah ditutup.']);
        }

        DB::transaction(function () use ($period, $result): void {
            $period->update([...$result, 'status' => AccreditationPeriodStatus::Decided]);

            $period->studyProgram->update([
                'accreditation_status' => $result['result_grade'],
                'accreditation_valid_until' => $result['valid_until'],
                'accreditation_sk_number' => $result['sk_number'] ?? $period->studyProgram->accreditation_sk_number,
            ]);
        });

        AuditLogger::log('closed', 'accreditation', $period, "Keputusan akreditasi {$period->studyProgram->full_name}: {$result['result_grade']}");
    }

    public function cancel(AccreditationPeriod $period, string $reason): void
    {
        if (! $period->status->isOpen()) {
            throw ValidationException::withMessages(['status' => 'Periode ini sudah ditutup.']);
        }

        $period->update(['status' => AccreditationPeriodStatus::Cancelled, 'notes' => trim(($period->notes ? $period->notes."\n" : '')."Dibatalkan: {$reason}")]);
        AuditLogger::log('status_changed', 'accreditation', $period, "Membatalkan periode {$period->code}", null, ['reason' => $reason]);
    }
}
