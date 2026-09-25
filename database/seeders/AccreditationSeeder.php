<?php

namespace Database\Seeders;

use App\Enums\AccreditationPeriodStatus;
use App\Enums\AccreditationVersionStatus;
use App\Models\AccreditationBody;
use App\Models\AccreditationIndicator;
use App\Models\AccreditationInstrument;
use App\Models\AccreditationInstrumentVersion;
use App\Models\AccreditationPeriod;
use App\Models\EvidenceCategory;
use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\Evidence\EvidenceService;
use Illuminate\Database\Seeder;

/**
 * Instrumen akreditasi ILUSTRATIF berstruktur 9 kriteria (dapat diedit/diimpor ulang oleh LPM sesuai dokumen resmi LAM),
 * beserta periode akreditasi prodi pada berbagai tahap kesiapan.
 */
class AccreditationSeeder extends Seeder
{
    public function run(EvidenceService $evidence): void
    {
        if (AccreditationInstrument::query()->exists()) {
            return;
        }

        $lpm = User::query()->where('username', 'lpm')->first();
        $versions = [];

        foreach (['LAMDIK' => 'Instrumen Akreditasi Program Studi LAMDIK', 'LAMGAMA' => 'Instrumen Akreditasi Program Studi LAMGAMA'] as $code => $name) {
            $body = AccreditationBody::query()->where('code', $code)->first();

            if (! $body) {
                continue;
            }

            $instrument = AccreditationInstrument::query()->create([
                'accreditation_body_id' => $body->id,
                'code' => "IAPS-{$code}",
                'name' => $name,
                'description' => 'Struktur ilustratif 9 kriteria untuk simulasi kesiapan. Sesuaikan butir & bobot dengan instrumen resmi LAM melalui menu impor.',
            ]);

            $version = $instrument->versions()->create([
                'version' => '1.0',
                'status' => AccreditationVersionStatus::Published,
                'scale_max' => 4,
                'grade_thresholds' => AccreditationInstrumentVersion::DEFAULT_THRESHOLDS,
                'published_at' => now()->subMonths(8),
                'created_by' => $lpm?->id,
            ]);

            $this->buildStructure($version, $code === 'LAMGAMA');
            $versions[$code] = $version;
        }

        if (! $lpm || $versions === []) {
            return;
        }

        mt_srand(2027);
        $categories = EvidenceCategory::query()->pluck('id', 'code');
        $plans = [
            // prodi => [versi, tahap, hari ke tenggat, rasio siap, rasio menunggu, rasio dinilai]
            'TBIG' => ['LAMDIK', AccreditationPeriodStatus::Preparing, 38, 0.66, 0.12, 0.8],
            'TFIS' => ['LAMDIK', AccreditationPeriodStatus::Preparing, 120, 0.38, 0.14, 0.45],
            'PIAUD' => ['LAMGAMA', AccreditationPeriodStatus::Preparing, 260, 0.12, 0.08, 0.15],
            'TMTK' => ['LAMDIK', AccreditationPeriodStatus::Decided, -1100, 0.95, 0.0, 1.0],
        ];

        foreach ($plans as $programCode => [$bodyCode, $status, $deadlineDays, $readyRatio, $pendingRatio, $assessedRatio]) {
            $program = StudyProgram::query()->where('code', $programCode)->first();
            $version = $versions[$bodyCode] ?? null;

            if (! $program || ! $version) {
                continue;
            }

            $pic = Lecturer::query()->where('study_program_id', $program->id)->whereNotNull('user_id')->orderBy('id')->value('user_id')
                ?? User::query()->role('admin_prodi')->where('study_program_id', $program->id)->value('id')
                ?? $lpm->id;
            $decided = $status === AccreditationPeriodStatus::Decided;

            $period = AccreditationPeriod::query()->create([
                'study_program_id' => $program->id,
                'instrument_version_id' => $version->id,
                'code' => 'AKR-'.($decided ? '2023' : now()->year).'-'.$programCode,
                'name' => ($decided ? 'Akreditasi 2023 ' : 'Reakreditasi ').$program->full_name,
                'status' => AccreditationPeriodStatus::Preparing,
                'pic_user_id' => $pic,
                'starts_on' => now()->addDays($deadlineDays - 180)->toDateString(),
                'submission_deadline' => now()->addDays($deadlineDays)->toDateString(),
                'target_score' => 301,
                'created_by' => $lpm->id,
            ]);

            $indicators = AccreditationIndicator::query()->where('instrument_version_id', $version->id)->orderBy('sort_order')->get();

            foreach ($indicators as $indicator) {
                $position = crc32($programCode.$indicator->code) % 1000 / 1000; // sebaran semu-acak yang stabil antar kriteria
                $state = $position < $readyRatio ? 'ready' : ($position < $readyRatio + $pendingRatio ? 'pending' : null);

                if ($state) {
                    $document = $evidence->create([
                        'title' => str($indicator->evidence_hint ?? $indicator->statement)->before(';')->limit(90, '')->toString().' — '.$program->code,
                        'evidence_category_id' => $categories[$this->categoryFor($indicator->evidence_hint)] ?? null,
                        'unit' => 'study_program:'.$program->id,
                        'year' => $decided ? 2023 : now()->year,
                    ], $lpm, url: 'https://drive.iaianulotim.ac.id/akreditasi/'.strtolower($programCode).'/'.strtolower($indicator->code));

                    if ($state === 'ready') {
                        $evidence->verify($document, $lpm, true, null);
                    }

                    $evidence->map($document, 'accreditation_indicator', $indicator->id, $lpm, $period->id);
                }

                if ($position < $assessedRatio) {
                    $base = $state === 'ready' ? 3.2 : ($state ? 2.6 : 2.0);
                    $period->assessments()->create([
                        'indicator_id' => $indicator->id,
                        'self_score' => min(4, (int) round($base + mt_rand(-5, 8) / 10)),
                        'notes' => $state ? null : 'Dokumen masih dalam penyusunan.',
                        'updated_by' => $pic,
                    ]);
                }
            }

            if ($decided) {
                $period->update([
                    'status' => AccreditationPeriodStatus::Decided,
                    'submitted_on' => '2023-05-02', 'visit_on' => '2023-07-18', 'decided_on' => '2023-08-20',
                    'result_grade' => $program->accreditation_status ?? 'Baik Sekali', 'result_score' => 334,
                    'sk_number' => $program->accreditation_sk_number ?? '1234/SK/LAMDIK/Ak/S/VIII/2023',
                    'certificate_number' => '0987/LAMDIK/2023', 'valid_from' => '2023-08-20',
                    'valid_until' => $program->accreditation_valid_until?->toDateString() ?? '2028-08-20',
                ]);
            }
        }
    }

    private function buildStructure(AccreditationInstrumentVersion $version, bool $lamgama): void
    {
        $order = 0;
        $indicatorOrder = 0;

        foreach ($this->criteria($lamgama) as [$code, $title, $weight, $children]) {
            $criterion = $version->criteria()->create(['code' => $code, 'title' => $title, 'weight' => $weight, 'sort_order' => ++$order]);

            foreach ($children as $key => $child) {
                // Sub-kriteria: ['C6.1', 'Kurikulum', [indikator...]]
                if (is_string($key)) {
                    $sub = $version->criteria()->create(['code' => $key, 'title' => $child[0], 'parent_id' => $criterion->id, 'sort_order' => ++$order]);
                    foreach ($child[1] as $i => $item) {
                        $this->indicator($sub, $version, "{$key}.".($i + 1), $item, ++$indicatorOrder);
                    }

                    continue;
                }

                $this->indicator($criterion, $version, "{$code}.".($key + 1), $child, ++$indicatorOrder);
            }
        }
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3?: bool, 4?: float}  $item
     */
    private function indicator($criterion, AccreditationInstrumentVersion $version, string $code, array $item, int $order): void
    {
        $criterion->indicators()->create([
            'instrument_version_id' => $version->id,
            'code' => $code,
            'statement' => $item[0],
            'target' => $item[1],
            'evidence_hint' => $item[2],
            'is_essential' => $item[3] ?? false,
            'weight' => $item[4] ?? 1,
            'sort_order' => $order,
        ]);
    }

    private function categoryFor(?string $hint): string
    {
        $hint = strtolower((string) $hint);

        return match (true) {
            str_contains($hint, 'sk ') || str_starts_with($hint, 'sk') => 'SK',
            str_contains($hint, 'kurikulum') => 'KUR',
            str_contains($hint, 'rps') => 'RPS',
            str_contains($hint, 'notulen') => 'NOT',
            str_contains($hint, 'sop') || str_contains($hint, 'pedoman') => 'SOP',
            str_contains($hint, 'sertifikat') => 'SERT',
            str_contains($hint, 'data') || str_contains($hint, 'tabel') => 'DATA',
            default => 'LAP',
        };
    }

    /**
     * @return list<array{0: string, 1: string, 2: float, 3: array<int|string, mixed>}>
     */
    private function criteria(bool $lamgama): array
    {
        $education = [
            'C6.1' => ['Kurikulum', [
                ['Kurikulum dikembangkan berbasis OBE dan ditinjau berkala dengan melibatkan pemangku kepentingan.', 'Peninjauan kurikulum ≤ 4 tahun dengan masukan pengguna lulusan & asosiasi.', 'Dokumen kurikulum; notulen peninjauan kurikulum', true, 2],
                ['Capaian pembelajaran lulusan (CPL) dipetakan ke mata kuliah dan asesmen.', 'Matriks CPL–MK–asesmen lengkap untuk seluruh MK.', 'Matriks pemetaan CPL; RPS'],
            ]],
            'C6.2' => ['Pembelajaran', [
                ['RPS tersedia untuk seluruh mata kuliah dan ditinjau setiap tahun.', '100% MK memiliki RPS mutakhir.', 'Kumpulan RPS; SK penetapan RPS', true, 2],
                ['Monitoring dan evaluasi pembelajaran dilaksanakan setiap semester dan ditindaklanjuti.', 'Laporan monev tiap semester beserta tindak lanjut.', 'Laporan Monev pembelajaran; rencana tindak lanjut', false, 1.5],
                ['Penilaian pembelajaran menggunakan rubrik dan umpan balik kepada mahasiswa.', 'Rubrik penilaian terdokumentasi untuk seluruh MK.', 'Contoh rubrik & portofolio penilaian'],
            ]],
            'C6.3' => ['Suasana akademik', [
                ['Kegiatan ilmiah terjadwal melibatkan dosen dan mahasiswa.', 'Minimal 1 kegiatan ilmiah per bulan.', 'Laporan kegiatan seminar/kolokium'],
            ]],
        ];

        if ($lamgama) {
            $education['C6.2'][1][] = ['Integrasi nilai keislaman dan moderasi beragama dalam pembelajaran.', 'Nilai moderasi beragama tercantum pada RPS MK relevan.', 'RPS terintegrasi; laporan kegiatan moderasi beragama'];
        }

        return [
            ['C1', 'Visi, Misi, Tujuan, dan Strategi', 4, [
                ['Visi keilmuan program studi selaras dengan visi institusi dan disusun secara partisipatif.', 'VMTS ditetapkan dengan SK dan tersosialisasi.', 'SK penetapan VMTS; notulen penyusunan', true],
                ['Strategi pencapaian VMTS memiliki indikator kinerja yang terukur.', 'Renstra prodi dengan IKU terukur.', 'Dokumen Renstra dan IKU prodi'],
            ]],
            ['C2', 'Tata Pamong, Tata Kelola, dan Kerja Sama', 10, [
                ['Sistem penjaminan mutu internal (SPMI) berjalan dengan siklus PPEPP.', 'Siklus PPEPP terdokumentasi lengkap.', 'Dokumen SPMI; laporan AMI; RTM', true, 2],
                ['Kerja sama pendidikan, penelitian, dan PkM dengan mitra relevan.', 'Minimal 3 kerja sama aktif per tahun.', 'Dokumen MoU/MoA; laporan implementasi kerja sama'],
                ['Kepemimpinan operasional, organisasi, dan publik berjalan efektif.', 'Struktur organisasi & uraian tugas ditetapkan.', 'SK struktur organisasi; SOP tata kelola'],
            ]],
            ['C3', 'Mahasiswa', 8, [
                ['Sistem penerimaan mahasiswa baru terdokumentasi dan akuntabel.', 'Rasio seleksi dan kebijakan penerimaan tersedia.', 'Pedoman PMB; data seleksi mahasiswa baru'],
                ['Layanan kemahasiswaan (bimbingan, beasiswa, karier) tersedia dan dievaluasi.', 'Kepuasan layanan ≥ 3,0 (skala 4).', 'Laporan survei kepuasan layanan kemahasiswaan'],
            ]],
            ['C4', 'Sumber Daya Manusia', 16, [
                ['Kecukupan dosen tetap sesuai bidang keahlian program studi.', 'Minimal 5 dosen tetap sesuai bidang.', 'Tabel data dosen tetap; SK pengangkatan', true, 2],
                ['Kualifikasi akademik dan jabatan fungsional dosen.', '≥ 30% dosen bergelar doktor atau lektor kepala.', 'Data kualifikasi & jabatan fungsional dosen', false, 1.5],
                ['Pengembangan kompetensi dosen terencana dan terdokumentasi.', 'Setiap dosen mengikuti ≥ 1 pengembangan per tahun.', 'Sertifikat pelatihan; rencana pengembangan SDM'],
            ]],
            ['C5', 'Keuangan, Sarana, dan Prasarana', 8, [
                ['Kecukupan dana operasional pendidikan per mahasiswa.', 'Dana operasional ≥ standar minimum.', 'Laporan keuangan prodi; data dana operasional'],
                ['Sarana laboratorium dan perpustakaan memadai serta dapat diakses.', 'Ketersediaan & aksesibilitas sarana terdokumentasi.', 'Data sarana prasarana; SOP laboratorium'],
            ]],
            ['C6', 'Pendidikan', 20, $education],
            ['C7', 'Penelitian', 8, [
                ['Roadmap penelitian prodi selaras dengan visi keilmuan.', 'Roadmap penelitian ditetapkan.', 'Dokumen roadmap penelitian'],
                ['Penelitian dosen melibatkan mahasiswa.', '≥ 25% penelitian melibatkan mahasiswa.', 'Tabel data penelitian dosen-mahasiswa'],
            ]],
            ['C8', 'Pengabdian kepada Masyarakat', 6, [
                ['PkM dosen relevan dengan bidang keilmuan dan melibatkan mahasiswa.', 'Minimal 1 PkM per dosen per tahun.', 'Laporan PkM; tabel data PkM'],
            ]],
            ['C9', 'Luaran dan Capaian Tridharma', 20, [
                ['IPK lulusan dan masa studi sesuai target.', 'Rata-rata IPK ≥ 3,25; masa studi ≤ 4,5 tahun.', 'Tabel data IPK & masa studi lulusan', true, 2],
                ['Tracer study dilaksanakan dan hasilnya digunakan untuk perbaikan.', 'Respons tracer study ≥ 30% lulusan.', 'Laporan tracer study; notulen tindak lanjut', false, 1.5],
                ['Publikasi ilmiah dosen dan mahasiswa.', 'Publikasi nasional terakreditasi setiap tahun.', 'Data publikasi ilmiah'],
            ]],
        ];
    }
}
