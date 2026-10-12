<?php

namespace Database\Seeders;

use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\Student;
use App\Models\User;
use App\Services\Research\ScientificWorks;
use Illuminate\Database\Seeder;

/**
 * Contoh karya ilmiah dosen 4 tahun terakhir; sebagian dosen sengaja tanpa karya agar analisis terisi.
 */
class ResearchDemoSeeder extends Seeder
{
    private const TOPICS = [
        'Implementasi Pembelajaran Berdiferensiasi pada', 'Analisis Kemampuan Pemecahan Masalah Matematis', 'Moderasi Beragama dalam Kurikulum',
        'Pengembangan Media Pembelajaran Interaktif untuk', 'Literasi Digital Guru Madrasah di', 'Nilai-nilai Pendidikan Karakter dalam Tradisi',
        'Efektivitas Model Problem Based Learning terhadap', 'Peran Pesantren dalam Penguatan Ekonomi', 'Kesiapan Guru PAUD menghadapi',
        'Strategi Pembelajaran Bahasa Inggris berbasis', 'Eksperimen Fisika Sederhana berbasis Kearifan Lokal', 'Pendampingan Penulisan Karya Ilmiah bagi Guru',
    ];

    private const PLACES = ['Lombok Timur', 'Nusa Tenggara Barat', 'Madrasah Ibtidaiyah', 'Sekolah Menengah', 'Pondok Pesantren', 'Kabupaten Lombok Timur'];

    private const VENUES = [
        'journal_national' => ['Jurnal Ilmiah Al-Muta\'aliyah', 'Jurnal Pendidikan Matematika', 'Edukasia Islamika', 'Jurnal Pendidikan Islam Al-Tanzim'],
        'journal_international' => ['International Journal of Islamic Education', 'Cogent Education', 'Indonesian Journal of Islam and Muslim Societies'],
        'proceeding_national' => ['Prosiding Seminar Nasional Pendidikan IAIA', 'Prosiding SEMNAS Matematika'],
        'proceeding_international' => ['Proceedings of the International Conference on Islamic Education', 'Journal of Physics: Conference Series'],
        'book_text' => ['Penerbit Pustaka Lotim'],
        'book_reference' => ['CV Literasi Nusantara'],
        'book_chapter' => ['Bunga Rampai Pendidikan Islam (CV Literasi Nusantara)'],
        'popular' => ['Harian Lombok Post'],
        'ip_copyright' => ['DJKI Kemenkumham'],
        'appropriate_technology' => ['LP2M IAI Al-Manan'],
    ];

    /** Indeksasi yang lazim per subjenis. */
    private const INDEXATIONS = [
        'journal_national' => ['not_accredited', 'sinta_2', 'sinta_3', 'sinta_4', 'sinta_4', 'sinta_5', 'sinta_6', 'garuda', 'google_scholar'],
        'journal_international' => ['scopus_q2', 'scopus_q3', 'scopus_q4', 'scopus_no_quartile', 'wos_esci', 'doaj', 'other_international'],
        'proceeding_national' => ['not_indexed', 'google_scholar', 'garuda'],
        'proceeding_international' => ['scopus_no_quartile', 'wos_esci', 'crossref'],
    ];

    public function run(): void
    {
        if (ScientificWork::query()->exists()) {
            return;
        }

        $lecturers = Lecturer::query()->where('is_active', true)->orderBy('id')->get(['id', 'study_program_id']);
        $students = Student::query()->orderBy('id')->get(['id', 'study_program_id']);
        $verifier = User::query()->where('username', 'lpm')->value('id');
        $creator = User::query()->where('username', 'lpm')->first() ?? User::query()->first();
        $works = app(ScientificWorks::class);
        mt_srand(2026);

        $weights = [
            'journal_national' => 50, 'journal_international' => 12, 'proceeding_national' => 12, 'proceeding_international' => 6,
            'book_text' => 4, 'book_reference' => 3, 'book_chapter' => 4, 'popular' => 3, 'ip_copyright' => 4, 'appropriate_technology' => 2,
        ];

        foreach ($lecturers as $index => $lecturer) {
            // Sekitar seperempat dosen belum memiliki karya.
            if ($index % 4 === 3) {
                continue;
            }

            foreach (range(1, mt_rand(1, 6)) as $ignored) {
                $type = ScientificWorkType::from($this->weighted($weights));
                $status = [ScientificWorkStatus::Verified, ScientificWorkStatus::Verified, ScientificWorkStatus::Pending, ScientificWorkStatus::Verified, ScientificWorkStatus::Revision][mt_rand(0, 4)];
                $venues = self::VENUES[$type->value];
                $indexations = self::INDEXATIONS[$type->value] ?? ['not_applicable'];

                // Sebagian karya melibatkan mahasiswa prodi yang sama dan/atau dosen rekan.
                $programStudents = $students->where('study_program_id', $lecturer->study_program_id)->values();
                $studentIds = mt_rand(1, 10) <= 4 && $programStudents->isNotEmpty()
                    ? $programStudents->random(min(mt_rand(1, 3), $programStudents->count()))->pluck('id')->all()
                    : [];
                $coAuthors = mt_rand(1, 10) <= 2 && $lecturers->count() > 1
                    ? $lecturers->where('id', '!=', $lecturer->id)->random(1)->pluck('id')->all()
                    : [];

                $work = $works->save(null, [
                    'lecturer_id' => $lecturer->id,
                    'title' => self::TOPICS[mt_rand(0, count(self::TOPICS) - 1)].' '.self::PLACES[mt_rand(0, count(self::PLACES) - 1)],
                    'type' => $type,
                    'year' => now()->year - mt_rand(0, 3),
                    'venue' => $venues[mt_rand(0, count($venues) - 1)],
                    'indexation' => $indexations[mt_rand(0, count($indexations) - 1)],
                    'author_role' => ['first', 'first', 'corresponding', 'second'][mt_rand(0, 3)],
                    'author_count' => 1 + count($studentIds) + count($coAuthors) + mt_rand(0, 1),
                    'source' => ['research', 'research', 'service', 'other'][mt_rand(0, 3)],
                    'funding' => ['self', 'self', 'institution', 'internal_grant', 'external_grant'][mt_rand(0, 4)],
                    'relevant_to_program' => true,
                    'relevant_to_roadmap' => mt_rand(0, 3) > 0,
                    'identifier' => $type->isJournal() ? sprintf('%04d-%04d', mt_rand(1000, 9999), mt_rand(1000, 9999)) : null,
                    'doi' => $type->isJournal() ? sprintf('10.%d/demo.%d.%d', mt_rand(10000, 99999), now()->year, mt_rand(100, 999)) : null,
                    'evidence_url' => 'https://drive.google.com/drive/folders/demo-'.mt_rand(100000, 999999),
                    'ip_status' => $type->isIntellectualProperty() ? 'certified' : null,
                    'ip_number' => $type->isIntellectualProperty() ? 'EC'.mt_rand(10000000, 99999999) : null,
                    'lecturers' => $coAuthors,
                    'students' => array_map(fn (int $id): array => ['student_id' => $id, 'name' => null, 'nim' => null], $studentIds),
                ], $creator, notify: false);

                $work->forceFill([
                    'status' => $status,
                    'verification_note' => $status === ScientificWorkStatus::Revision ? 'Tautan bukti tidak dapat dibuka. Periksa akses Google Drive.' : null,
                    'verified_by' => $status === ScientificWorkStatus::Pending ? null : $verifier,
                    'verified_at' => $status === ScientificWorkStatus::Pending ? null : now(),
                ])->save();
            }
        }
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function weighted(array $weights): string
    {
        $pick = mt_rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            if (($pick -= $weight) <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
