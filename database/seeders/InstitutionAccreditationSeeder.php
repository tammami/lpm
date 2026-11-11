<?php

namespace Database\Seeders;

use App\Enums\AccreditationVersionStatus;
use App\Models\AccreditationBody;
use App\Models\AccreditationCriterion;
use App\Models\AccreditationInstrument;
use App\Models\AccreditationInstrumentVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Instrumen Akreditasi Perguruan Tinggi (APT) BAN-PT — struktur ILUSTRATIF 9 kriteria.
 * Dibuat sebagai versi DRAF agar LPM dapat menyesuaikan butir & bobot dengan dokumen resmi
 * (atau mengimpornya dari Excel) sebelum menetapkannya berlaku.
 *
 * Aman dijalankan berulang: `php artisan db:seed --class=InstitutionAccreditationSeeder`.
 */
class InstitutionAccreditationSeeder extends Seeder
{
    public const CODE = 'IAPT-BANPT';

    public function run(): void
    {
        if (AccreditationInstrument::query()->where('code', self::CODE)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $body = AccreditationBody::query()->firstOrCreate(['code' => 'BAN-PT'], [
                'name' => 'Badan Akreditasi Nasional Perguruan Tinggi',
                'description' => 'Akreditasi institusi (APT).',
            ]);

            $instrument = AccreditationInstrument::query()->create([
                'accreditation_body_id' => $body->id,
                'code' => self::CODE,
                'name' => 'Instrumen Akreditasi Perguruan Tinggi (APT) BAN-PT',
                'description' => 'Struktur ilustratif 9 kriteria tingkat institusi untuk simulasi kesiapan. Sesuaikan butir & bobot dengan instrumen resmi BAN-PT melalui menu impor sebelum ditetapkan berlaku.',
            ]);

            $version = $instrument->versions()->create([
                'version' => '1.0',
                'status' => AccreditationVersionStatus::Draft,
                'scale_max' => 4,
                'grade_thresholds' => AccreditationInstrumentVersion::DEFAULT_THRESHOLDS,
                'notes' => 'Draf awal ilustratif — wajib diverifikasi terhadap dokumen resmi BAN-PT.',
                'created_by' => User::query()->where('username', 'lpm')->value('id'),
            ]);

            $criterionOrder = 0;
            $indicatorOrder = 0;

            foreach ($this->criteria() as [$code, $title, $weight, $indicators]) {
                /** @var AccreditationCriterion $criterion */
                $criterion = $version->criteria()->create(['code' => $code, 'title' => $title, 'weight' => $weight, 'sort_order' => ++$criterionOrder]);

                foreach ($indicators as $index => $item) {
                    $criterion->indicators()->create([
                        'instrument_version_id' => $version->id,
                        'code' => "{$code}.".($index + 1),
                        'statement' => $item[0],
                        'target' => $item[1],
                        'evidence_hint' => $item[2],
                        'is_essential' => $item[3] ?? false,
                        'weight' => $item[4] ?? 1,
                        'sort_order' => ++$indicatorOrder,
                    ]);
                }
            }
        });
    }

    /**
     * [kode, judul, bobot kriteria, [[pernyataan, target, bukti, esensial?, bobot?], ...]]
     *
     * @return list<array{0: string, 1: string, 2: float, 3: list<array<int, mixed>>}>
     */
    private function criteria(): array
    {
        return [
            ['C1', 'Visi, Misi, Tujuan, dan Strategi', 5, [
                ['VMTS institusi mencerminkan visi keilmuan dan kekhasan perguruan tinggi keagamaan, disusun partisipatif.', 'VMTS ditetapkan dengan SK dan disosialisasikan ke seluruh sivitas.', 'SK penetapan VMTS; notulen penyusunan; bukti sosialisasi', true, 2],
                ['Renstra dan Renop institusi memuat indikator kinerja utama yang terukur dan dipantau.', 'Renstra & Renop berlaku dengan IKU/IKT terukur.', 'Dokumen Renstra, Renop, dan laporan capaian IKU', false, 1.5],
                ['Tingkat pemahaman VMTS oleh dosen, tendik, dan mahasiswa dievaluasi.', 'Survei pemahaman VMTS dilaksanakan berkala.', 'Laporan survei pemahaman VMTS'],
            ]],
            ['C2', 'Tata Pamong, Tata Kelola, dan Kerja Sama', 12, [
                ['Struktur organisasi, statuta, dan uraian tugas ditetapkan dan dijalankan.', 'Statuta & SOTK berlaku; uraian tugas lengkap.', 'Statuta; SK SOTK; dokumen uraian tugas', true, 2],
                ['SPMI berjalan dengan siklus PPEPP di seluruh unit.', 'AMI tahunan, RTM, dan tindak lanjut terdokumentasi.', 'Dokumen SPMI; laporan AMI; notulen RTM', true, 2],
                ['Sistem informasi manajemen mendukung pengambilan keputusan.', 'SIM akademik, keuangan, SDM, dan mutu terintegrasi.', 'Profil sistem informasi; laporan pemanfaatan'],
                ['Kerja sama dalam dan luar negeri relevan dengan tridharma dan dievaluasi manfaatnya.', 'Kerja sama aktif di bidang pendidikan, penelitian, dan PkM.', 'MoU/MoA; laporan implementasi & evaluasi kerja sama', false, 1.5],
            ]],
            ['C3', 'Mahasiswa', 8, [
                ['Kebijakan dan sistem penerimaan mahasiswa baru akuntabel serta memperhatikan keragaman.', 'Pedoman PMB berlaku; rasio keketatan terdata.', 'Pedoman PMB; data pendaftar & diterima'],
                ['Layanan kemahasiswaan (penalaran, minat-bakat, kesejahteraan, karier) tersedia dan dievaluasi.', 'Kepuasan layanan kemahasiswaan ≥ 3,0 (skala 4).', 'Laporan survei kepuasan layanan kemahasiswaan', false, 1.5],
                ['Prestasi akademik dan nonakademik mahasiswa tingkat institusi.', 'Prestasi tingkat provinsi/nasional setiap tahun.', 'Data & sertifikat prestasi mahasiswa'],
            ]],
            ['C4', 'Sumber Daya Manusia', 12, [
                ['Kecukupan dan kualifikasi dosen tetap institusi.', 'Rasio dosen:mahasiswa sesuai ketentuan; dosen doktor meningkat.', 'Data dosen tetap; ijazah & SK jabatan fungsional', true, 2],
                ['Sistem rekrutmen, pengembangan karier, dan retensi SDM terdokumentasi.', 'Rencana pengembangan SDM berjalan & dievaluasi.', 'Pedoman SDM; rencana pengembangan; laporan pelatihan'],
                ['Kecukupan dan kualifikasi tenaga kependidikan.', 'Tendik bersertifikat kompetensi sesuai bidang.', 'Data tendik; sertifikat kompetensi'],
            ]],
            ['C5', 'Keuangan, Sarana, dan Prasarana', 10, [
                ['Sumber dana beragam dan pengelolaan keuangan akuntabel.', 'Laporan keuangan diaudit akuntan publik.', 'Laporan keuangan; laporan audit eksternal', true, 1.5],
                ['Dana operasional pendidikan, penelitian, dan PkM per mahasiswa/dosen memadai.', 'Alokasi dana tridharma sesuai standar.', 'Data realisasi anggaran tridharma'],
                ['Sarana prasarana (ruang kuliah, perpustakaan, laboratorium, TIK, ibadah) memadai dan aksesibel.', 'Inventaris & pemanfaatan sarana terdokumentasi.', 'Data sarpras; SOP pemanfaatan; survei kepuasan sarpras'],
            ]],
            ['C6', 'Pendidikan', 16, [
                ['Kebijakan kurikulum institusi berbasis OBE dan dievaluasi berkala.', 'Seluruh prodi meninjau kurikulum ≤ 4 tahun.', 'Pedoman kurikulum; dokumen kurikulum prodi', true, 2],
                ['Monitoring dan evaluasi proses pembelajaran dilaksanakan dan ditindaklanjuti.', 'Monev pembelajaran tiap semester di seluruh prodi.', 'Laporan Monev pembelajaran; rencana tindak lanjut', false, 1.5],
                ['Status dan peringkat akreditasi program studi.', 'Seluruh prodi terakreditasi; proporsi Unggul/Baik Sekali meningkat.', 'Daftar SK akreditasi program studi', true, 2],
                ['Integrasi nilai keislaman dan moderasi beragama dalam pembelajaran.', 'Nilai moderasi beragama tercantum pada kurikulum.', 'Dokumen kurikulum; laporan kegiatan moderasi beragama'],
            ]],
            ['C7', 'Penelitian', 12, [
                ['Rencana induk penelitian (RIP) institusi ditetapkan dan menjadi acuan.', 'RIP berlaku dan selaras dengan VMTS.', 'Dokumen RIP; SK penetapan', true, 1.5],
                ['Pendanaan dan produktivitas penelitian dosen.', 'Penelitian per dosen per tahun meningkat.', 'Data penelitian & pendanaan dosen'],
                ['Publikasi ilmiah di jurnal nasional terakreditasi dan internasional bereputasi.', 'Publikasi Sinta/Scopus setiap tahun.', 'Data publikasi ilmiah'],
            ]],
            ['C8', 'Pengabdian kepada Masyarakat', 8, [
                ['Rencana induk PkM ditetapkan dan dilaksanakan.', 'Renstra/RIP PkM berlaku.', 'Dokumen rencana induk PkM', false, 1.5],
                ['PkM relevan dengan kebutuhan masyarakat dan melibatkan mahasiswa.', 'PkM per dosen per tahun dengan pelibatan mahasiswa.', 'Laporan PkM; data pelibatan mahasiswa'],
            ]],
            ['C9', 'Luaran dan Capaian Tridharma', 17, [
                ['Rata-rata IPK, masa studi, dan kelulusan tepat waktu.', 'Kelulusan tepat waktu ≥ 50%.', 'Data IPK, masa studi, dan kelulusan', true, 2],
                ['Tracer study institusi dan waktu tunggu lulusan.', 'Respons tracer study ≥ 30%; waktu tunggu ≤ 6 bulan.', 'Laporan tracer study', false, 1.5],
                ['Kepuasan pengguna lulusan diukur dan ditindaklanjuti.', 'Survei pengguna lulusan dilaksanakan berkala.', 'Laporan survei kepuasan pengguna lulusan'],
                ['Luaran penelitian & PkM (HKI, buku, produk) yang dimanfaatkan.', 'Luaran HKI/buku setiap tahun.', 'Data HKI, buku ber-ISBN, produk terapan'],
            ]],
        ];
    }
}
