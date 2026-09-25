<?php

namespace Database\Seeders;

use App\Enums\ActionPlanStatus;
use App\Enums\RecommendationStatus;
use App\Models\ActionPlan;
use App\Models\Evidence;
use App\Models\Recommendation;
use App\Models\User;
use App\Models\Verification;
use App\Services\Evidence\EvidenceService;
use App\Services\Improvement\ImprovementWorkflow;
use App\Services\Improvement\RecommendationEngine;
use App\Support\CodeGenerator;
use Illuminate\Database\Seeder;

/**
 * Contoh siklus peningkatan mutu: rekomendasi hasil mesin aturan dengan rencana aksi di berbagai tahap.
 */
class ImprovementDemoSeeder extends Seeder
{
    public function run(RecommendationEngine $engine, ImprovementWorkflow $workflow, EvidenceService $evidence): void
    {
        $lpm = User::query()->where('username', 'lpm')->first();

        if (! $lpm || Recommendation::query()->exists()) {
            return;
        }

        $candidates = $engine->candidates($lpm)->where('exists', false)->take(9)->values();

        $recommendations = $candidates->map(fn (array $candidate, int $index): Recommendation => Recommendation::query()->create([
            ...collect($candidate)->only(['rule_key', 'title', 'description', 'rationale', 'origin', 'source_type', 'source_id', 'target_type', 'target_id', 'target_name', 'study_program_id', 'faculty_id', 'indicator', 'priority'])->all(),
            'code' => CodeGenerator::next('REK', 'recommendations'),
            'pic_user_id' => $candidate['pic_user_id'] ?? $lpm->id,
            'due_date' => now()->addDays([45, 30, 60, -10, 20, 90, 30, 45, 60][$index] ?? 30)->toDateString(),
            'status' => RecommendationStatus::Open,
            'created_by' => $lpm->id,
            'created_at' => now()->subDays(40 - $index * 3),
        ]));

        $plan = function (Recommendation $recommendation, string $title, array $tasks, int $done, int $dueInDays, ?string $output = null) use ($lpm): ActionPlan {
            $progress = count($tasks) ? (int) round($done / count($tasks) * 100) : 0;
            $plan = $recommendation->actionPlans()->create([
                'title' => $title,
                'target_output' => $output,
                'pic_user_id' => $recommendation->pic_user_id ?? $lpm->id,
                'starts_on' => now()->subDays(30)->toDateString(),
                'due_date' => now()->addDays($dueInDays)->toDateString(),
                'progress' => $progress,
                'status' => $progress === 100 ? ActionPlanStatus::Completed : ($progress > 0 ? ActionPlanStatus::InProgress : ActionPlanStatus::Planned),
                'completed_at' => $progress === 100 ? now()->subDays(3) : null,
                'created_by' => $lpm->id,
            ]);

            foreach ($tasks as $i => $task) {
                $plan->tasks()->create(['title' => $task, 'sort_order' => $i + 1, 'is_done' => $i < $done, 'done_at' => $i < $done ? now()->subDays(10 - $i) : null]);
            }

            return $plan;
        };

        $attach = function (ActionPlan $plan, Recommendation $recommendation) use ($evidence, $lpm): void {
            $document = Evidence::query()->where('study_program_id', $recommendation->study_program_id)->where('status', 'verified')->first()
                ?? Evidence::query()->where('status', 'verified')->first();

            if ($document) {
                $evidence->map($document, 'action_plan', $plan->id, $lpm);
            }
        };

        $verify = function (ActionPlan $plan) use ($lpm): void {
            Verification::query()->create([
                'verifiable_type' => $plan->getMorphClass(), 'verifiable_id' => $plan->id, 'verifier_id' => $lpm->id,
                'decision' => 'accepted', 'notes' => 'Bukti pelaksanaan lengkap dan sesuai luaran.', 'verified_at' => now()->subDay(),
            ]);
            $plan->update(['status' => ActionPlanStatus::Verified]);
        };

        // Templat rencana aksi per jenis aturan: [judul, tugas, luaran].
        $templates = fn (Recommendation $r): array => match (true) {
            str_ends_with($r->rule_key, ':low') => ['Workshop umpan balik & penilaian autentik', ['Analisis butir skor rendah bersama dosen', 'Susun TOR workshop', 'Laksanakan workshop', 'Rekap daftar hadir & materi'], 'Seluruh dosen prodi mengikuti workshop'],
            str_ends_with($r->rule_key, ':rate') => ['Sosialisasi pengisian Monev melalui dosen PA', ['Kirim surat edaran kaprodi', 'Briefing dosen PA', 'Pengingat grup kelas'], 'Response rate ≥ 80%'],
            str_ends_with($r->rule_key, ':trend') => ['Rapat analisis penyebab penurunan skor', ['Kumpulkan data per dosen', 'Rapat prodi', 'Susun langkah perbaikan'], null],
            str_contains($r->rule_key, ':lecturer:') => ['Peer teaching dan observasi kelas', ['Tetapkan dosen pendamping', 'Observasi 2 pertemuan', 'Refleksi hasil observasi'], 'Laporan observasi & refleksi'],
            str_starts_with($r->rule_key, 'ami:') => ['Penyelesaian tindakan korektif temuan', ['Rapat koordinasi PIC', 'Laksanakan tindakan korektif', 'Unggah bukti ke temuan'], 'Temuan siap diverifikasi auditor'],
            default => ['Penyusunan rencana kerja tim akreditasi', ['Bentuk tim akreditasi', 'Pemetaan dokumen per kriteria', 'Lengkapi dokumen yang kurang', 'Simulasi asesmen lapangan'], 'Dokumen akreditasi lengkap'],
        };

        // Tahap demo per urutan rekomendasi: [tugas selesai (null = semua), tenggat (hari), verifikasi?]
        $stages = [[null, -5, true], [null, 10, false], [1, -6, false], [null, -2, true], [0, 40, false]];

        foreach ($recommendations as $index => $r) {
            if (! isset($stages[$index])) {
                break;
            }

            [$title, $tasks, $output] = $templates($r);
            [$done, $due, $verified] = $stages[$index];
            $created = $plan($r, $title, $tasks, $done ?? count($tasks), $due, $output);

            if ($done === null) {
                $attach($created, $r);
            }

            if ($verified) {
                $verify($created);
            }

            if ($index === 0) {
                $plan($r, 'Pendampingan penyusunan RPS revisi', ['Identifikasi MK prioritas', 'Pendampingan penyusunan', 'Review RPS oleh GKM', 'Evaluasi ulang pada Monev berikutnya'], 2, 21, 'RPS revisi untuk MK dengan skor rendah');
            }
        }

        $recommendations->each(fn (Recommendation $r) => $workflow->sync($r->refresh()));

        if (($r = $recommendations->get(3)) && $r->refresh()->status === RecommendationStatus::Verified) {
            $r->update(['status' => RecommendationStatus::Closed, 'closed_at' => now()]);
        }
    }
}
