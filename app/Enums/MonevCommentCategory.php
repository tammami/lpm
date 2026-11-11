<?php

namespace App\Enums;

/**
 * Kategori saran/komentar terbuka e-Monev. Ditentukan OTOMATIS dari kata kunci saat ditampilkan
 * (tidak disimpan), sehingga berlaku juga untuk data lama dan mudah disempurnakan.
 */
enum MonevCommentCategory: string
{
    use HasOptions;

    case Method = 'method';
    case Material = 'material';
    case Discipline = 'discipline';
    case Attitude = 'attitude';
    case Assessment = 'assessment';
    case Facility = 'facility';
    case Appreciation = 'appreciation';
    case Other = 'other';
    case Empty = 'empty';

    public function label(): string
    {
        return match ($this) {
            self::Method => 'Metode mengajar',
            self::Material => 'Materi & bahan ajar',
            self::Discipline => 'Kedisiplinan & kehadiran',
            self::Attitude => 'Sikap & komunikasi',
            self::Assessment => 'Tugas & penilaian',
            self::Facility => 'Sarana & media',
            self::Appreciation => 'Apresiasi',
            self::Other => 'Lainnya',
            self::Empty => 'Tidak ada saran',
        };
    }

    /**
     * Kata/frasa penanda (huruf kecil). Dicocokkan per kata utuh; urutan kasus menentukan pemenang bila seri.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return match ($this) {
            self::Method => [
                'metode', 'cara mengajar', 'cara menjelaskan', 'menjelaskan', 'penjelasan', 'diskusi', 'interaktif', 'praktik',
                'praktek', 'contoh', 'membosankan', 'bosan', 'monoton', 'paham', 'dipahami', 'memahami', 'terlalu cepat', 'pelan',
                'pelan-pelan', 'bervariasi', 'variasi', 'ceramah', 'studi kasus', 'kerja kelompok', 'tanya jawab', 'latihan soal',
            ],
            self::Material => [
                'materi', 'rps', 'silabus', 'modul', 'buku', 'referensi', 'bahan ajar', 'bahan', 'slide', 'ppt', 'kurikulum',
                'diktat', 'handout', 'sumber belajar', 'jurnal', 'relevan', 'update', 'terbaru',
            ],
            self::Discipline => [
                'tepat waktu', 'terlambat', 'telat', 'hadir', 'kehadiran', 'absen', 'jarang masuk', 'kosong', 'jadwal', 'disiplin',
                'pertemuan', 'izin', 'masuk kelas', 'diganti', 'ganti jadwal', 'molor', 'pulang cepat', 'jam kuliah',
            ],
            self::Attitude => [
                'ramah', 'sabar', 'galak', 'marah', 'sikap', 'komunikasi', 'komunikatif', 'respon', 'respons', 'menanggapi',
                'perhatian', 'motivasi', 'memotivasi', 'menghargai', 'tegas', 'akrab', 'peduli', 'killer', 'emosi', 'humoris',
                'sopan', 'adil', 'pilih kasih', 'membentak', 'santun', 'bersahabat',
            ],
            self::Assessment => [
                'nilai', 'penilaian', 'tugas', 'ujian', 'uts', 'uas', 'kuis', 'quiz', 'feedback', 'umpan balik', 'rubrik',
                'deadline', 'tenggat', 'koreksi', 'transparan', 'remedial', 'skor',
            ],
            self::Facility => [
                'ac', 'proyektor', 'lcd', 'infokus', 'kipas', 'wifi', 'internet', 'ruang', 'ruangan', 'kelas panas', 'panas',
                'kursi', 'meja', 'sarana', 'fasilitas', 'listrik', 'sound', 'mic', 'mikrofon', 'media', 'zoom', 'daring',
                'online', 'e-learning', 'lms', 'laboratorium', 'lab', 'perpustakaan', 'toilet',
            ],
            self::Appreciation => [
                'terima kasih', 'terimakasih', 'makasih', 'bagus', 'mantap', 'luar biasa', 'pertahankan', 'dipertahankan',
                'semangat', 'sukses', 'keren', 'puas', 'the best', 'terbaik', 'menyenangkan', 'senang', 'sehat selalu',
                'sudah baik', 'sudah bagus', 'sangat baik', 'lanjutkan', 'good', 'mantul',
            ],
            self::Other, self::Empty => [],
        };
    }

    /**
     * Tentukan kategori satu komentar: kategori dengan kata kunci terbanyak; seri → urutan kasus.
     */
    public static function classify(string $text): self
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
        $bare = trim($normalized, " \t.,;:!?-–—_/'\"()");

        if ($bare === '' || mb_strlen($bare) <= 2 || preg_match('/^(tidak ada|tdk ada|ga ada|gak ada|nggak ada|belum ada|nihil|kosong|cukup|tidak|ok|oke|none|no|nothing|aman)( saran)?( apa-apa)?$/u', $bare)) {
            return self::Empty;
        }

        $best = self::Other;
        $bestHits = 0;

        foreach (self::cases() as $category) {
            $hits = 0;

            foreach ($category->keywords() as $keyword) {
                // Izinkan akhiran umum (-nya, -lah) agar "penjelasannya" tetap cocok dengan "penjelasan".
                $hits += preg_match_all('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'(?:nya|lah)?(?![\p{L}\p{N}])/u', $normalized);
            }

            if ($hits > $bestHits) {
                [$best, $bestHits] = [$category, $hits];
            }
        }

        return $best;
    }
}
