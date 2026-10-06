<?php

namespace Database\Seeders;

use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\User;
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
        'journal_national' => ['Jurnal Ilmiah Al-Muta\'aliyah', 'Jurnal Pendidikan dan Keislaman', 'Jurnal Tarbiyah Lotim'],
        'journal_accredited' => ['Jurnal Pendidikan Matematika (SINTA)', 'Edukasia Islamika', 'Jurnal Pendidikan Islam Al-Tanzim'],
        'journal_international' => ['International Journal of Islamic Education', 'Asian Journal of Education Studies'],
        'journal_reputable' => ['Journal of Physics: Conference Series', 'Cogent Education', 'Indonesian Journal of Islam and Muslim Societies'],
        'proceeding_national' => ['Prosiding Seminar Nasional Pendidikan IAIA', 'Prosiding SEMNAS Matematika'],
        'proceeding_international' => ['Proceedings of the International Conference on Islamic Education'],
        'book' => ['Penerbit Pustaka Lotim', 'CV Literasi Nusantara'],
        'book_chapter' => ['Bunga Rampai Pendidikan Islam (CV Literasi Nusantara)'],
        'ip' => ['DJKI Kemenkumham'],
        'other' => ['Harian Lombok Post'],
    ];

    public function run(): void
    {
        if (ScientificWork::query()->exists()) {
            return;
        }

        $lecturers = Lecturer::query()->where('is_active', true)->orderBy('id')->get(['id']);
        $verifier = User::query()->where('username', 'lpm')->value('id');
        mt_srand(2026);

        $weights = [
            'journal_national' => 30, 'journal_accredited' => 25, 'journal_international' => 6, 'journal_reputable' => 5,
            'proceeding_national' => 12, 'proceeding_international' => 6, 'book' => 6, 'book_chapter' => 4, 'ip' => 4, 'other' => 2,
        ];

        foreach ($lecturers as $index => $lecturer) {
            // Sekitar seperempat dosen belum memiliki karya.
            if ($index % 4 === 3) {
                continue;
            }

            foreach (range(1, mt_rand(1, 6)) as $ignored) {
                $type = ScientificWorkType::from($this->weighted($weights));
                $ranks = array_keys($type->ranks());
                $status = [ScientificWorkStatus::Verified, ScientificWorkStatus::Verified, ScientificWorkStatus::Pending, ScientificWorkStatus::Verified, ScientificWorkStatus::Revision][mt_rand(0, 4)];
                $venues = self::VENUES[$type->value];

                ScientificWork::query()->create([
                    'lecturer_id' => $lecturer->id,
                    'title' => self::TOPICS[mt_rand(0, count(self::TOPICS) - 1)].' '.self::PLACES[mt_rand(0, count(self::PLACES) - 1)],
                    'type' => $type,
                    'year' => now()->year - mt_rand(0, 3),
                    'venue' => $venues[mt_rand(0, count($venues) - 1)],
                    'rank' => $ranks === [] ? null : $ranks[mt_rand(0, count($ranks) - 1)],
                    'author_role' => ['first', 'first', 'corresponding', 'member'][mt_rand(0, 3)],
                    'source' => ['research', 'research', 'service', 'other'][mt_rand(0, 3)],
                    'funding' => ['self', 'self', 'internal', 'government', 'external'][mt_rand(0, 4)],
                    'identifier' => $type->isJournal() ? sprintf('%04d-%04d', mt_rand(1000, 9999), mt_rand(1000, 9999)) : null,
                    'url' => $type->isJournal() ? sprintf('10.%d/demo.%d.%d', mt_rand(10000, 99999), now()->year, mt_rand(100, 999)) : null,
                    'status' => $status,
                    'verification_note' => $status === ScientificWorkStatus::Revision ? 'Tautan DOI tidak dapat dibuka. Periksa kembali.' : null,
                    'verified_by' => $status === ScientificWorkStatus::Pending ? null : $verifier,
                    'verified_at' => $status === ScientificWorkStatus::Pending ? null : now(),
                ]);
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
