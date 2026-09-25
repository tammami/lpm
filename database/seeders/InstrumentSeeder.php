<?php

namespace Database\Seeders;

use App\Enums\InstrumentType;
use App\Enums\InstrumentVersionStatus;
use App\Enums\QuestionType;
use App\Enums\RespondentType;
use App\Enums\ScoringMethod;
use App\Models\AnswerScale;
use App\Models\ClassificationScheme;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\User;
use App\Services\InstrumentVersioning;
use Illuminate\Database\Seeder;

/**
 * Instrumen contoh (BRD §26). Instrumen final tetap ditetapkan oleh LPM melalui builder.
 */
class InstrumentSeeder extends Seeder
{
    public function run(InstrumentVersioning $versioning): void
    {
        $admin = User::query()->where('username', 'lpm')->first() ?? User::query()->firstOrFail();

        $this->seedTeachingEvaluation($versioning, $admin);
        $this->seedSatisfactionSurvey($versioning, $admin);
    }

    private function seedTeachingEvaluation(InstrumentVersioning $versioning, User $admin): void
    {
        if (Instrument::query()->where('code', 'MONEV-PBM')->exists()) {
            return;
        }

        $instrument = $versioning->createInstrument([
            'code' => 'MONEV-PBM',
            'name' => 'Monev Pembelajaran',
            'type' => InstrumentType::MonevPembelajaran,
            'respondent_type' => RespondentType::Mahasiswa,
            'description' => 'Evaluasi proses pembelajaran oleh mahasiswa terhadap setiap dosen pengampu pada setiap kelas.',
        ], $admin);

        $version = $instrument->versions()->first();
        $version->sections()->delete();

        $likert = AnswerScale::query()->where('name', 'Likert 4 — Kualitas')->value('options');

        $structure = [
            ['A', 'Perencanaan Pembelajaran', 'Kesiapan dosen sebelum perkuliahan dimulai.', [
                ['RPS disampaikan dan dijelaskan kepada mahasiswa di awal perkuliahan.', 'Ketersediaan RPS', 1],
                ['Tujuan/capaian pembelajaran mata kuliah dijelaskan dengan jelas.', 'Kejelasan CPMK', 1],
                ['Materi perkuliahan sesuai dengan RPS.', 'Kesesuaian materi', 1],
            ]],
            ['B', 'Pelaksanaan Pembelajaran', 'Proses perkuliahan selama satu semester.', [
                ['Dosen hadir dan memulai perkuliahan tepat waktu.', 'Kehadiran dosen', 1.5],
                ['Dosen menguasai materi perkuliahan.', 'Penguasaan materi', 1.5],
                ['Materi disampaikan dengan jelas dan mudah dipahami.', 'Kejelasan penyampaian', 1.5],
                ['Metode pembelajaran bervariasi dan sesuai karakter mata kuliah.', 'Metode pembelajaran', 1],
            ]],
            ['C', 'Evaluasi Pembelajaran', 'Penilaian dan umpan balik hasil belajar.', [
                ['Tugas diberikan secara terstruktur dan relevan.', 'Penugasan', 1],
                ['Penilaian dilakukan secara transparan dan objektif.', 'Transparansi penilaian', 1],
                ['Dosen memberikan umpan balik atas tugas/ujian.', 'Umpan balik', 1],
            ]],
            ['D', 'Sarana Pembelajaran', 'Dukungan media dan teknologi.', [
                ['Media pembelajaran yang digunakan membantu pemahaman.', 'Media pembelajaran', 0.5],
                ['LMS/platform daring dimanfaatkan dengan baik bila diperlukan.', 'Pemanfaatan LMS', 0.5],
            ]],
        ];

        foreach ($structure as $sectionIndex => [$code, $title, $description, $items]) {
            $section = $version->sections()->create(['code' => $code, 'title' => $title, 'description' => $description, 'sort_order' => $sectionIndex + 1]);

            foreach ($items as $index => [$label, $indicator, $weight]) {
                $question = $section->questions()->create([
                    'instrument_version_id' => $version->id,
                    'code' => $code.($index + 1),
                    'label' => $label,
                    'type' => QuestionType::Likert,
                    'category' => $title,
                    'indicator' => $indicator,
                    'weight' => $weight,
                    'is_required' => true,
                    'is_scored' => true,
                    'sort_order' => $index + 1,
                ]);
                $question->options()->createMany(collect($likert)->map(fn (array $o, int $i): array => [...$o, 'sort_order' => $i + 1])->all());
            }
        }

        $comment = $version->sections()->create(['code' => 'E', 'title' => 'Saran & Masukan', 'description' => 'Tuliskan masukan yang membangun. Identitas Anda tidak ditampilkan.', 'sort_order' => 5]);
        $comment->questions()->createMany([
            ['instrument_version_id' => $version->id, 'code' => 'E1', 'label' => 'Hal yang paling membantu Anda dalam perkuliahan ini.', 'type' => QuestionType::LongText, 'is_required' => false, 'is_scored' => false, 'visible_to_evaluatee' => true, 'sort_order' => 1],
            ['instrument_version_id' => $version->id, 'code' => 'E2', 'label' => 'Saran perbaikan untuk dosen/mata kuliah ini.', 'type' => QuestionType::LongText, 'is_required' => false, 'is_scored' => false, 'visible_to_evaluatee' => true, 'sort_order' => 2],
        ]);

        $version->update(['scoring_method' => ScoringMethod::Weighted, 'scale_min' => 1, 'scale_max' => 4]);
        $this->publish($version, $admin);

        // Versi 1.1: penambahan butir kesesuaian RPS dengan capaian lulusan.
        $next = $versioning->cloneVersion($version, $admin, changelog: 'Menambahkan butir A4 (keterkaitan dengan capaian lulusan) dan memperjelas redaksi B3.');
        $sectionA = $next->sections()->where('code', 'A')->first();
        $a4 = $sectionA->questions()->create([
            'instrument_version_id' => $next->id,
            'code' => 'A4',
            'label' => 'Dosen mengaitkan materi dengan capaian pembelajaran lulusan dan konteks nyata.',
            'type' => QuestionType::Likert,
            'category' => 'Perencanaan Pembelajaran',
            'indicator' => 'Relevansi materi',
            'weight' => 1,
            'sort_order' => 4,
        ]);
        $a4->options()->createMany(collect($likert)->map(fn (array $o, int $i): array => [...$o, 'sort_order' => $i + 1])->all());
        $next->questions()->where('code', 'B3')->update(['label' => 'Materi disampaikan secara runtut, jelas, dan mudah dipahami.']);
        $this->publish($next, $admin);
    }

    private function seedSatisfactionSurvey(InstrumentVersioning $versioning, User $admin): void
    {
        if (Instrument::query()->where('code', 'SURVEI-LAYANAN')->exists()) {
            return;
        }

        $instrument = $versioning->createInstrument([
            'code' => 'SURVEI-LAYANAN',
            'name' => 'Survei Kepuasan Layanan Akademik',
            'type' => InstrumentType::Survei,
            'respondent_type' => RespondentType::Mahasiswa,
            'description' => 'Tingkat kepuasan mahasiswa terhadap layanan akademik, sarana, dan kemahasiswaan (dimensi SERVQUAL).',
        ], $admin);

        $version = $instrument->versions()->first();
        $version->sections()->delete();
        $version->update([
            'scale_min' => 1,
            'scale_max' => 5,
            'classification_scheme_id' => ClassificationScheme::query()->where('name', 'Skala 5')->value('id'),
        ]);

        $likert5 = AnswerScale::query()->where('name', 'Likert 5 — Kepuasan')->value('options');
        $dimensions = [
            ['A', 'Reliability (Keandalan)', ['Layanan administrasi akademik diberikan sesuai jadwal yang dijanjikan.', 'Informasi akademik akurat dan mudah diakses.']],
            ['B', 'Responsiveness (Daya Tanggap)', ['Petugas sigap membantu kesulitan mahasiswa.', 'Keluhan mahasiswa ditanggapi dengan cepat.']],
            ['C', 'Assurance (Jaminan)', ['Petugas memiliki pengetahuan yang memadai tentang layanan.', 'Proses layanan memberikan rasa aman dan terpercaya.']],
            ['D', 'Empathy (Empati)', ['Petugas bersikap ramah dan sopan.', 'Layanan memperhatikan kebutuhan mahasiswa secara individual.']],
            ['E', 'Tangible (Bukti Fisik)', ['Ruang kuliah bersih dan nyaman.', 'Fasilitas ibadah dan sarana keagamaan kampus memadai.', 'Akses internet kampus memadai untuk perkuliahan.']],
        ];

        foreach ($dimensions as $sectionIndex => [$code, $title, $items]) {
            $section = $version->sections()->create(['code' => $code, 'title' => $title, 'sort_order' => $sectionIndex + 1]);

            foreach ($items as $index => $label) {
                $question = $section->questions()->create([
                    'instrument_version_id' => $version->id,
                    'code' => $code.($index + 1),
                    'label' => $label,
                    'type' => QuestionType::Likert,
                    'category' => $title,
                    'weight' => 1,
                    'sort_order' => $index + 1,
                ]);
                $question->options()->createMany(collect($likert5)->map(fn (array $o, int $i): array => [...$o, 'sort_order' => $i + 1])->all());
            }
        }

        $this->publish($version, $admin);
    }

    private function publish(InstrumentVersion $version, User $admin): void
    {
        $version->update([
            'status' => InstrumentVersionStatus::Published,
            'submitted_by' => $admin->id,
            'submitted_at' => now()->subDays(20),
            'approved_by' => $admin->id,
            'approved_at' => now()->subDays(18),
            'published_by' => $admin->id,
            'published_at' => now()->subDays(15),
        ]);
    }
}
