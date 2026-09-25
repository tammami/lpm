<?php

namespace Database\Seeders;

use App\Models\CorrectiveAction;
use App\Models\Evidence;
use App\Models\EvidenceCategory;
use App\Models\EvidenceMapping;
use App\Models\StudyProgram;
use App\Models\Unit;
use App\Models\User;
use App\Services\Evidence\EvidenceService;
use Illuminate\Database\Seeder;

/**
 * Contoh repositori dokumen bukti (tautan) lintas prodi & unit, sebagian terpetakan ke tindakan AMI.
 */
class EvidenceDemoSeeder extends Seeder
{
    public function run(EvidenceService $service): void
    {
        if (Evidence::query()->exists()) {
            return;
        }

        $lpm = User::query()->where('username', 'lpm')->first();

        if (! $lpm) {
            return;
        }

        $categories = EvidenceCategory::query()->pluck('id', 'code');
        $base = 'https://drive.iaianulotim.ac.id/spmi';
        $make = function (array $data, string $status = 'verified') use ($service, $lpm, $categories, $base): Evidence {
            $evidence = $service->create([
                ...$data,
                'evidence_category_id' => $categories[$data['category']] ?? null,
            ], $lpm, url: $base.'/'.str($data['title'])->slug());

            if ($status !== 'pending') {
                $service->verify($evidence, $lpm, $status === 'verified', $status === 'rejected' ? 'Dokumen belum ditandatangani pimpinan. Unggah versi final.' : null);
            }

            return $evidence;
        };

        $make(['title' => 'Kebijakan SPMI IAIA NU Lombok Timur', 'category' => 'SK', 'unit' => 'institution:', 'valid_until' => now()->addYears(3)->toDateString(), 'confidentiality' => 'public']);
        $make(['title' => 'Manual Mutu SPMI 2026', 'category' => 'SOP', 'unit' => 'institution:', 'confidentiality' => 'public']);
        $make(['title' => 'SOP Monitoring dan Evaluasi Pembelajaran', 'category' => 'SOP', 'unit' => 'unit:'.Unit::query()->where('code', 'LPM')->value('id')]);
        $make(['title' => 'SOP Layanan Sirkulasi Perpustakaan', 'category' => 'SOP', 'unit' => 'unit:'.Unit::query()->where('code', 'UPT-PUS')->value('id')], 'pending');
        $make(['title' => 'SK Tim Auditor Mutu Internal 2026', 'category' => 'SK', 'unit' => 'unit:'.Unit::query()->where('code', 'LPM')->value('id'), 'valid_until' => now()->addDays(25)->toDateString()]);

        foreach (StudyProgram::query()->orderBy('id')->get() as $index => $program) {
            $unit = 'study_program:'.$program->id;
            $make(['title' => "Dokumen Kurikulum OBE {$program->code} 2024", 'category' => 'KUR', 'unit' => $unit]);
            $make(['title' => "Kumpulan RPS Semester Genap {$program->code}", 'category' => 'RPS', 'unit' => $unit], $index % 2 ? 'pending' : 'verified');
            $make(['title' => "Laporan Monev Pembelajaran {$program->code} 2025/2026 Ganjil", 'category' => 'LAP', 'unit' => $unit]);
            $make(['title' => "Notulen Rapat Tinjauan Kurikulum {$program->code}", 'category' => 'NOT', 'unit' => $unit], $index === 2 ? 'rejected' : 'verified');
            $make(['title' => "Sertifikat Akreditasi {$program->code}", 'category' => 'SERT', 'unit' => $unit, 'valid_until' => $program->accreditation_valid_until?->toDateString(), 'confidentiality' => 'public']);
        }

        // Petakan notulen/laporan prodi ke tindakan korektif AMI yang sudah berjalan.
        CorrectiveAction::query()->with('finding')->whereIn('status', ['completed', 'verified'])->get()
            ->each(function (CorrectiveAction $action) use ($lpm): void {
                $evidence = Evidence::query()->where('study_program_id', $action->finding->study_program_id)->where('title', 'like', 'Notulen%')->first();

                // Ditulis langsung: tindakan yang sudah terverifikasi terkunci dari pemetaan baru via layanan.
                if ($evidence) {
                    EvidenceMapping::query()->firstOrCreate(
                        ['evidence_id' => $evidence->id, 'mappable_type' => $action->getMorphClass(), 'mappable_id' => $action->id, 'context_id' => null],
                        ['created_by' => $lpm->id],
                    );
                }
            });
    }
}
