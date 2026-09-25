<?php

namespace Database\Seeders;

use App\Enums\AuditeeType;
use App\Enums\AuditProgramStatus;
use App\Enums\AuditStatus;
use App\Enums\CorrectiveActionStatus;
use App\Enums\FindingStatus;
use App\Enums\UserRole;
use App\Models\Audit;
use App\Models\Auditor;
use App\Models\AuditProgram;
use App\Models\Finding;
use App\Models\FindingSeverity;
use App\Models\Instrument;
use App\Models\Lecturer;
use App\Models\QualityStandard;
use App\Models\RootCauseCategory;
use App\Models\StudyProgram;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Contoh siklus AMI 2026: audit tiap prodi dengan tahapan & temuan beragam.
 */
class AmiDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (AuditProgram::query()->exists()) {
            return;
        }

        mt_srand(77);
        $lpm = User::query()->where('username', 'lpm')->first();
        $leadUser = User::query()->where('username', 'auditor')->first();
        $version = Instrument::query()->where('code', 'AMI-PRODI')->first()?->versions()->with('questions.options', 'questions.standard')->first();

        if (! $leadUser || ! $version) {
            return;
        }

        $lead = Auditor::query()->firstOrCreate(['user_id' => $leadUser->id], [
            'certification' => 'Pelatihan Auditor Mutu Internal SPMI', 'certificate_number' => 'AMI/LPM/2025/014', 'certified_on' => '2025-08-12',
            'competencies' => 'Standar pendidikan, sistem penjaminan mutu, audit dokumen.',
        ]);

        $members = Lecturer::query()->whereHas('studyProgram', fn ($q) => $q->whereIn('code', ['PAI', 'TBIG', 'TFIS']))->where('employment_status', 'tetap')->with('user')->take(3)->get()
            ->map(function (Lecturer $lecturer): Auditor {
                User::query()->findOrFail($lecturer->user_id)->assignRole(UserRole::Auditor->value);

                return Auditor::query()->firstOrCreate(['user_id' => $lecturer->user_id], [
                    'certification' => 'Pelatihan AMI Internal IAIA NU 2025', 'competencies' => 'Pembelajaran & kurikulum.', 'certified_on' => '2025-10-02',
                ]);
            });

        $program = AuditProgram::query()->create([
            'code' => 'AMI-'.now()->year.'-01',
            'name' => 'AMI Tahun Akademik 2025/2026',
            'year' => now()->year,
            'scope' => 'Seluruh program studi Fakultas Tarbiyah dan Keguruan.',
            'objective' => 'Memastikan standar SPMI dilaksanakan, mengidentifikasi ketidaksesuaian, dan peluang peningkatan menjelang akreditasi.',
            'criteria' => 'Standar SPMI IAIA NU 2026; SN-Dikti; instrumen LAMDIK/LAMGAMA.',
            'starts_on' => now()->subDays(75)->toDateString(),
            'ends_on' => now()->addDays(30)->toDateString(),
            'status' => AuditProgramStatus::Ongoing,
            'created_by' => $lpm?->id,
        ]);

        $severities = FindingSeverity::query()->get()->keyBy('code');
        $rootCauses = RootCauseCategory::query()->pluck('id')->all();
        $stages = [
            'TMTK' => [AuditStatus::Completed, 62],
            'PAI' => [AuditStatus::Completed, 55],
            'TBIG' => [AuditStatus::Reporting, 20],
            'TFIS' => [AuditStatus::FieldAudit, 6],
            'PIAUD' => [AuditStatus::Planned, -12],
        ];

        foreach (StudyProgram::query()->whereIn('code', array_keys($stages))->get() as $index => $program_) {
            [$status, $daysAgo] = $stages[$program_->code];
            $pic = User::query()->where('study_program_id', $program_->id)->role(UserRole::AdminProdi->value)->first()
                ?? Lecturer::query()->where('study_program_id', $program_->id)->with('user')->first()?->user;

            $audit = Audit::query()->create([
                'audit_program_id' => $program->id,
                'code' => sprintf('AUD-%d-%03d', now()->year, $index + 1),
                'auditee_type' => AuditeeType::StudyProgram,
                'auditee_id' => $program_->id,
                'auditee_name' => $program_->full_name,
                'study_program_id' => $program_->id,
                'faculty_id' => $program_->faculty_id,
                'instrument_version_id' => $version->id,
                'auditee_pic_user_id' => $pic?->id,
                'desk_review_due' => now()->subDays($daysAgo + 7)->toDateString(),
                'scheduled_on' => now()->subDays($daysAgo)->toDateString(),
                'location' => 'Ruang Prodi '.$program_->name,
                'status' => $status,
                'started_at' => $status === AuditStatus::Planned ? null : now()->subDays($daysAgo + 10),
                'completed_at' => $status === AuditStatus::Completed ? now()->subDays($daysAgo - 5) : null,
                'summary' => $status === AuditStatus::Completed ? 'Audit dilaksanakan melalui desk evaluation dokumen dan wawancara dengan kaprodi, dosen, serta mahasiswa.' : null,
                'strengths' => $status === AuditStatus::Completed ? "Kurikulum telah berbasis OBE.\nMonev pembelajaran rutin dilaksanakan setiap semester." : null,
                'conclusion' => $status === AuditStatus::Completed ? 'Prodi secara umum telah memenuhi standar dengan beberapa ketidaksesuaian minor yang perlu ditindaklanjuti.' : null,
            ]);

            $team = [$lead->id => ['role' => 'lead']];
            if ($members->isNotEmpty()) {
                $team[$members[$index % $members->count()]->id] = ['role' => 'member'];
            }
            $audit->auditors()->sync($team);

            if ($status === AuditStatus::Planned) {
                continue;
            }

            // Isi daftar tilik: sebagian besar sesuai, beberapa sebagian/tidak sesuai.
            $answerRatio = $status === AuditStatus::FieldAudit ? 0.6 : 1.0;
            foreach ($version->questions as $qIndex => $question) {
                if (mt_rand(0, 100) / 100 > $answerRatio) {
                    continue;
                }
                $roll = mt_rand(0, 100);
                $value = $roll < 68 ? 'compliant' : ($roll < 88 ? 'partial' : ($roll < 95 ? 'non_compliant' : 'na'));
                $option = $question->options->firstWhere('value', $value);
                $audit->answers()->create([
                    'instrument_question_id' => $question->id,
                    'instrument_question_option_id' => $option->id,
                    'score' => $option->score,
                    'auditor_note' => $value === 'compliant' ? null : 'Bukti belum lengkap untuk seluruh sampel yang diperiksa.',
                    'updated_by' => $leadUser->id,
                ]);
            }

            $this->seedFindings($audit, $status, $severities, $rootCauses, $pic, $leadUser, $daysAgo);
        }
    }

    private function seedFindings(Audit $audit, AuditStatus $status, $severities, array $rootCauses, ?User $pic, User $auditor, int $daysAgo): void
    {
        $templates = [
            ['MINOR', 'RPS belum tersedia untuk sebagian mata kuliah', 'Dari sampel 11 mata kuliah, 3 belum memiliki RPS yang disahkan kaprodi.', 'Standar Proses: seluruh mata kuliah wajib memiliki RPS.', 'Menyusun dan mengesahkan RPS seluruh mata kuliah sebelum perkuliahan dimulai.', 'STD-03'],
            ['MAYOR', 'Monev pembelajaran belum ditindaklanjuti', 'Hasil Monev semester lalu belum dianalisis dan tidak ada rencana tindak lanjut tertulis.', 'Standar Pengelolaan: hasil Monev wajib ditindaklanjuti.', 'Menyusun laporan analisis Monev dan rencana aksi perbaikan per semester.', 'STD-07'],
            ['OB', 'Rubrik penilaian belum seragam', 'Format rubrik penilaian antar dosen belum seragam.', 'Standar Penilaian: rubrik disampaikan di awal perkuliahan.', 'Menyusun template rubrik prodi.', 'STD-04'],
            ['MINOR', 'Roadmap penelitian belum diperbarui', 'Roadmap penelitian prodi terakhir diperbarui tahun 2021.', 'Standar Penelitian: roadmap ditinjau berkala.', 'Memperbarui roadmap penelitian 2026–2030.', 'STD-09'],
        ];

        $count = $status === AuditStatus::FieldAudit ? 1 : mt_rand(2, 4);
        $statuses = match ($status) {
            AuditStatus::Completed => [FindingStatus::Closed, FindingStatus::Submitted, FindingStatus::InProgress, FindingStatus::ActionRequired],
            AuditStatus::Reporting => [FindingStatus::ActionRequired, FindingStatus::Open, FindingStatus::Open],
            default => [FindingStatus::Open],
        };

        foreach (array_slice($templates, 0, $count) as $i => [$severityCode, $title, $description, $criteria, $recommendation, $standardCode]) {
            $severity = $severities[$severityCode];
            $findingStatus = $statuses[$i % count($statuses)];
            $issuedAt = Carbon::parse($audit->scheduled_on)->addDays(3);

            $finding = Finding::query()->create([
                'code' => sprintf('TMN-%d-%04d', now()->year, Finding::query()->count() + 1),
                'audit_id' => $audit->id,
                'auditee_type' => $audit->auditee_type,
                'auditee_id' => $audit->auditee_id,
                'auditee_name' => $audit->auditee_name,
                'study_program_id' => $audit->study_program_id,
                'faculty_id' => $audit->faculty_id,
                'quality_standard_id' => QualityStandard::query()->where('code', $standardCode)->value('id'),
                'finding_severity_id' => $severity->id,
                'title' => $title,
                'description' => $description,
                'criteria' => $criteria,
                'effect' => 'Berpotensi menurunkan capaian standar dan nilai akreditasi.',
                'recommendation' => $recommendation,
                'pic_user_id' => $pic?->id,
                'due_date' => $issuedAt->copy()->addDays($severity->default_due_days)->toDateString(),
                'status' => $findingStatus,
                'created_by' => $auditor->id,
                'issued_at' => $findingStatus === FindingStatus::Open ? null : $issuedAt,
                'verified_at' => $findingStatus === FindingStatus::Closed ? $issuedAt->copy()->addDays(25) : null,
                'closed_at' => $findingStatus === FindingStatus::Closed ? $issuedAt->copy()->addDays(27) : null,
            ]);
            $finding->forceFill(['created_at' => $issuedAt->copy()->subDay()])->saveQuietly();

            if (in_array($findingStatus, [FindingStatus::InProgress, FindingStatus::Submitted, FindingStatus::Closed], true) && $pic) {
                $done = $findingStatus !== FindingStatus::InProgress;
                $finding->correctiveActions()->create([
                    'root_cause_category_id' => $rootCauses[array_rand($rootCauses)],
                    'root_cause' => 'Belum ada mekanisme pengingat dan pembagian tugas yang jelas di tingkat prodi.',
                    'action_plan' => $recommendation,
                    'preventive_action' => 'Menetapkan jadwal rutin dan penanggung jawab di rapat awal semester.',
                    'pic_user_id' => $pic->id,
                    'due_date' => $finding->due_date,
                    'status' => $findingStatus === FindingStatus::Closed ? CorrectiveActionStatus::Verified : ($done ? CorrectiveActionStatus::Completed : CorrectiveActionStatus::InProgress),
                    'progress' => $done ? 100 : 60,
                    'implementation_notes' => $done ? 'Telah dilaksanakan dan didokumentasikan.' : 'Sedang berjalan, 6 dari 10 dokumen selesai.',
                    'completed_at' => $done ? $issuedAt->copy()->addDays(20) : null,
                    'created_by' => $pic->id,
                ]);
            }

            if ($findingStatus === FindingStatus::Closed) {
                Verification::query()->create([
                    'verifiable_type' => 'finding',
                    'verifiable_id' => $finding->id,
                    'verifier_id' => $auditor->id,
                    'decision' => 'accepted',
                    'notes' => 'Bukti pelaksanaan lengkap dan efektif.',
                    'verified_at' => $finding->verified_at,
                ]);
            }
        }
    }
}
