<?php

namespace Database\Seeders;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementStatus;
use App\Enums\LpmActivityCategory;
use App\Enums\LpmActivityStatus;
use App\Enums\SuggestionCategory;
use App\Enums\SuggestionStatus;
use App\Models\Lecturer;
use App\Models\LpmActivity;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\Suggestion;
use Illuminate\Database\Seeder;

/**
 * Contoh timeline program kerja LPM, kotak saran, dan prestasi mahasiswa.
 */
class LpmProgramDemoSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;

        $activities = [
            ['Rapat kerja tahunan LPM', LpmActivityCategory::Meeting, "{$year}-01-15", "{$year}-01-16", LpmActivityStatus::Done],
            ['Workshop penyusunan dokumen SPMI', LpmActivityCategory::Training, "{$year}-02-20", "{$year}-02-21", LpmActivityStatus::Done],
            ['Sosialisasi kebijakan mutu kepada mahasiswa baru', LpmActivityCategory::Socialization, "{$year}-03-10", "{$year}-03-10", LpmActivityStatus::Done],
            ['Revisi manual mutu dan standar SPMI', LpmActivityCategory::Document, "{$year}-04-01", "{$year}-05-31", LpmActivityStatus::Ongoing],
            ['Pendampingan borang akreditasi prodi', LpmActivityCategory::Accreditation, "{$year}-06-03", "{$year}-08-30", LpmActivityStatus::Planned],
            ['Studi banding ke LPM perguruan tinggi mitra', LpmActivityCategory::Benchmarking, "{$year}-09-12", "{$year}-09-13", LpmActivityStatus::Postponed],
            ['Survei kepuasan layanan administrasi', LpmActivityCategory::Survey, "{$year}-10-01", "{$year}-10-31", LpmActivityStatus::Planned],
            ['Penyusunan laporan tahunan LPM', LpmActivityCategory::Reporting, "{$year}-12-01", "{$year}-12-20", LpmActivityStatus::Planned],
        ];

        foreach ($activities as $index => [$title, $category, $start, $end, $status]) {
            LpmActivity::query()->updateOrCreate(['code' => sprintf('PK-%d-%04d', $year, $index + 1)], [
                'year' => $year,
                'title' => $title,
                'category' => $category,
                'start_date' => $start,
                'end_date' => $end,
                'location' => 'Kampus IAIA NU Lombok Timur',
                'pic' => 'Ketua LPM',
                'participants' => 'Pimpinan, kaprodi, dan unit terkait',
                'status' => $status,
                'realization_note' => $status === LpmActivityStatus::Done ? 'Terlaksana sesuai rencana.' : null,
            ]);
        }

        $students = Student::query()->whereNotNull('user_id')->orderBy('id')->limit(4)->get();
        $lecturer = Lecturer::query()->whereNotNull('user_id')->orderBy('id')->first();

        foreach ($students->take(2) as $index => $student) {
            Suggestion::query()->firstOrCreate(['user_id' => $student->user_id, 'subject' => $index === 0 ? 'Wi-Fi perpustakaan sering terputus' : 'Jadwal layanan akademik'], [
                'sender_role' => 'mahasiswa',
                'study_program_id' => $index === 0 ? $student->study_program_id : null,
                'is_anonymous' => $index === 1,
                'category' => $index === 0 ? SuggestionCategory::Facility : SuggestionCategory::Service,
                'message' => $index === 0
                    ? 'Mohon jaringan Wi-Fi di perpustakaan diperkuat karena sering terputus saat jam ramai.'
                    : 'Mohon layanan akademik dibuka sampai sore agar mahasiswa yang kuliah pagi tetap terlayani.',
                'status' => $index === 0 ? SuggestionStatus::FollowedUp : SuggestionStatus::New,
                'response' => $index === 0 ? 'Terima kasih. Saran sudah diteruskan ke unit TI dan penambahan access point dijadwalkan bulan depan.' : null,
                'responded_at' => $index === 0 ? now() : null,
            ]);
        }

        if ($lecturer) {
            Suggestion::query()->firstOrCreate(['user_id' => $lecturer->user_id, 'subject' => 'Pelatihan penyusunan RPS'], [
                'sender_role' => 'dosen',
                'study_program_id' => $lecturer->study_program_id,
                'category' => SuggestionCategory::Academic,
                'message' => 'Usul agar LPM mengadakan pelatihan penyusunan RPS berbasis OBE untuk dosen baru.',
                'status' => SuggestionStatus::InReview,
            ]);
        }

        $samples = [
            ['Lomba Karya Tulis Ilmiah Mahasiswa', AchievementCategory::Academic, AchievementLevel::National, 'Juara 2', AchievementStatus::Verified],
            ['Musabaqah Tilawatil Quran antarkampus', AchievementCategory::NonAcademic, AchievementLevel::Provincial, 'Juara 1', AchievementStatus::Pending],
            ['Olimpiade Matematika Mahasiswa', AchievementCategory::Academic, AchievementLevel::Regency, 'Finalis', AchievementStatus::Revision],
        ];

        foreach ($students->take(3)->values() as $index => $student) {
            [$title, $category, $level, $rank, $status] = $samples[$index];

            StudentAchievement::query()->firstOrCreate(['student_id' => $student->id, 'title' => $title], [
                'category' => $category,
                'level' => $level,
                'achievement' => $rank,
                'participation' => 'individual',
                'organizer' => 'Panitia '.$title,
                'achieved_on' => now()->subMonths($index + 1)->toDateString(),
                'evidence_url' => 'https://contoh.ac.id/sertifikat/'.($index + 1),
                'status' => $status,
                'verification_note' => $status === AchievementStatus::Revision ? 'Lampirkan sertifikat yang memuat nama Anda.' : null,
                'verified_at' => $status === AchievementStatus::Pending ? null : now(),
            ]);
        }
    }
}
