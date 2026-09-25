<?php

namespace Database\Seeders;

use App\Enums\InstrumentType;
use App\Enums\InstrumentVersionStatus;
use App\Enums\QuestionType;
use App\Enums\RespondentType;
use App\Models\AnswerScale;
use App\Models\EvidenceCategory;
use App\Models\FindingSeverity;
use App\Models\Instrument;
use App\Models\QualityStandard;
use App\Models\RootCauseCategory;
use App\Models\User;
use App\Services\InstrumentVersioning;
use Illuminate\Database\Seeder;

/**
 * Referensi awal AMI & dokumen bukti. Seluruhnya dapat diubah LPM sesuai dokumen SPMI institusi.
 */
class AmiReferenceSeeder extends Seeder
{
    public function run(InstrumentVersioning $versioning): void
    {
        $standards = [
            ['STD-01', 'Standar Kompetensi Lulusan', 'pendidikan', 'Lulusan memiliki sikap, pengetahuan, dan keterampilan sesuai CPL prodi.', 'Persentase lulusan tepat waktu; rata-rata IPK; masa tunggu kerja.', '≥ 80% lulus tepat waktu'],
            ['STD-02', 'Standar Isi Pembelajaran', 'pendidikan', 'Kurikulum disusun berbasis OBE dan ditinjau secara berkala.', 'Dokumen kurikulum & peninjauan minimal 4 tahun sekali.', 'Kurikulum ditinjau ≤ 4 tahun'],
            ['STD-03', 'Standar Proses Pembelajaran', 'pendidikan', 'Setiap mata kuliah memiliki RPS dan dilaksanakan sesuai RPS.', 'Persentase mata kuliah ber-RPS; kehadiran dosen.', '100% MK memiliki RPS'],
            ['STD-04', 'Standar Penilaian Pembelajaran', 'pendidikan', 'Penilaian dilakukan secara edukatif, objektif, akuntabel, dan transparan.', 'Rubrik penilaian tersedia & dikomunikasikan.', '100% MK memiliki rubrik'],
            ['STD-05', 'Standar Dosen dan Tenaga Kependidikan', 'pendidikan', 'Dosen memiliki kualifikasi akademik dan kompetensi sesuai bidang.', 'Rasio dosen:mahasiswa; persentase dosen berjabatan fungsional.', 'Rasio ≤ 1:30'],
            ['STD-06', 'Standar Sarana dan Prasarana', 'pendidikan', 'Sarana pembelajaran memadai, termasuk sarana ibadah dan akses digital.', 'Ketersediaan ruang, laboratorium, perpustakaan, LMS.', 'Sesuai kebutuhan prodi'],
            ['STD-07', 'Standar Pengelolaan Pembelajaran', 'tata_kelola', 'Prodi melaksanakan monitoring dan evaluasi pembelajaran secara berkala.', 'Laporan Monev per semester & tindak lanjutnya.', 'Monev setiap semester'],
            ['STD-08', 'Standar Pembiayaan', 'tata_kelola', 'Anggaran pembelajaran dialokasikan dan dipertanggungjawabkan.', 'Realisasi anggaran pendidikan per mahasiswa.', 'Sesuai RKAT'],
            ['STD-09', 'Standar Penelitian', 'penelitian', 'Dosen melaksanakan penelitian sesuai roadmap prodi.', 'Jumlah penelitian per dosen per tahun.', '≥ 1 penelitian/dosen/tahun'],
            ['STD-10', 'Standar Pengabdian kepada Masyarakat', 'pkm', 'Dosen melaksanakan PkM yang relevan dengan kebutuhan masyarakat.', 'Jumlah PkM per dosen per tahun.', '≥ 1 PkM/dosen/tahun'],
            ['STD-11', 'Standar Moderasi Beragama & Integrasi Ilmu', 'tambahan', 'Pembelajaran mengintegrasikan nilai keislaman ahlussunnah wal jama\'ah dan moderasi beragama.', 'Muatan moderasi beragama & integrasi ilmu dalam RPS/kegiatan.', 'Terintegrasi pada kurikulum'],
        ];

        foreach ($standards as $index => [$code, $name, $category, $statement, $indicator, $target]) {
            QualityStandard::query()->updateOrCreate(['code' => $code], [
                'name' => $name, 'category' => $category, 'statement' => $statement, 'indicator' => $indicator, 'target' => $target,
                'reference' => 'Dokumen SPMI IAIA NU (contoh, sesuaikan)', 'sort_order' => $index + 1,
            ]);
        }

        $severities = [
            ['MAYOR', 'KTS Mayor', 'Ketidaksesuaian yang berdampak sistemik terhadap pencapaian standar.', 'danger', true, 30],
            ['MINOR', 'KTS Minor', 'Ketidaksesuaian parsial yang tidak berdampak sistemik.', 'warning', true, 60],
            ['OB', 'Observasi', 'Kondisi yang berpotensi menjadi ketidaksesuaian bila tidak dikelola.', 'info', false, 90],
            ['PP', 'Peluang Peningkatan', 'Saran perbaikan untuk meningkatkan capaian di atas standar.', 'neutral', false, 120],
        ];

        foreach ($severities as $index => [$code, $name, $description, $color, $requires, $days]) {
            FindingSeverity::query()->updateOrCreate(['code' => $code], [
                'name' => $name, 'description' => $description, 'color' => $color, 'requires_corrective_action' => $requires,
                'default_due_days' => $days, 'sort_order' => $index + 1,
            ]);
        }

        foreach (['Sumber Daya Manusia', 'Proses/Prosedur', 'Teknologi', 'Kebijakan', 'Sarana Prasarana', 'Dokumentasi'] as $index => $name) {
            RootCauseCategory::query()->updateOrCreate(['name' => $name], ['sort_order' => $index + 1]);
        }

        $categories = [
            ['RPS', 'Rencana Pembelajaran Semester'], ['KUR', 'Dokumen Kurikulum'], ['SK', 'Surat Keputusan'],
            ['LAP', 'Laporan Kegiatan'], ['NOT', 'Notulen & Daftar Hadir'], ['FOTO', 'Dokumentasi Foto'],
            ['SERT', 'Sertifikat'], ['DATA', 'Data Pendukung'], ['SOP', 'SOP & Pedoman'],
        ];

        foreach ($categories as [$code, $name]) {
            EvidenceCategory::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }

        $this->seedAmiInstrument($versioning);
    }

    private function seedAmiInstrument(InstrumentVersioning $versioning): void
    {
        if (Instrument::query()->where('code', 'AMI-PRODI')->exists()) {
            return;
        }

        $admin = User::query()->where('username', 'lpm')->first() ?? User::query()->firstOrFail();
        $instrument = $versioning->createInstrument([
            'code' => 'AMI-PRODI',
            'name' => 'Instrumen AMI Program Studi',
            'type' => InstrumentType::Ami,
            'respondent_type' => RespondentType::Auditor,
            'description' => 'Daftar tilik kepatuhan program studi terhadap standar SPMI.',
        ], $admin);

        $version = $instrument->versions()->first();
        $version->sections()->delete();
        $version->update(['scale_min' => 0, 'scale_max' => 4]);
        $compliance = AnswerScale::query()->where('name', 'Kepatuhan AMI')->value('options');
        $standardIds = QualityStandard::query()->pluck('id', 'code');

        $sections = [
            ['A', 'Pendidikan & Pembelajaran', [
                ['STD-02', 'Kurikulum prodi disusun berbasis OBE dan ditinjau paling lama 4 tahun sekali dengan melibatkan pemangku kepentingan.'],
                ['STD-03', 'Seluruh mata kuliah memiliki RPS yang disahkan dan dapat diakses mahasiswa.'],
                ['STD-03', 'Pelaksanaan perkuliahan sesuai RPS (minimal 14 pertemuan) dibuktikan dengan berita acara.'],
                ['STD-04', 'Rubrik penilaian tersedia dan disampaikan kepada mahasiswa di awal perkuliahan.'],
                ['STD-07', 'Prodi melaksanakan Monev pembelajaran setiap semester dan menindaklanjuti hasilnya.'],
            ]],
            ['B', 'Sumber Daya', [
                ['STD-05', 'Rasio dosen tetap terhadap mahasiswa memenuhi standar.'],
                ['STD-05', 'Dosen mengampu mata kuliah sesuai bidang keahliannya.'],
                ['STD-06', 'Sarana pembelajaran, termasuk sarana ibadah dan akses internet, memadai dan terpelihara.'],
                ['STD-08', 'Anggaran kegiatan akademik prodi direncanakan dan realisasinya dilaporkan.'],
            ]],
            ['C', 'Penelitian & Pengabdian', [
                ['STD-09', 'Prodi memiliki roadmap penelitian dan dosen melaksanakan penelitian sesuai roadmap.'],
                ['STD-10', 'Dosen melaksanakan PkM yang hasilnya dipublikasikan atau dimanfaatkan masyarakat.'],
            ]],
            ['D', 'Kekhasan Institusi', [
                ['STD-11', 'Nilai moderasi beragama dan integrasi keilmuan tercermin dalam RPS dan kegiatan akademik.'],
                ['STD-01', 'Prodi menelusuri lulusan (tracer study) dan memanfaatkan hasilnya untuk perbaikan kurikulum.'],
            ]],
        ];

        foreach ($sections as $sectionIndex => [$code, $title, $items]) {
            $section = $version->sections()->create(['code' => $code, 'title' => $title, 'sort_order' => $sectionIndex + 1]);

            foreach ($items as $index => [$standard, $label]) {
                $question = $section->questions()->create([
                    'instrument_version_id' => $version->id,
                    'code' => $code.($index + 1),
                    'label' => $label,
                    'type' => QuestionType::SingleChoice,
                    'quality_standard_id' => $standardIds[$standard] ?? null,
                    'requires_evidence' => true,
                    'is_required' => true,
                    'is_scored' => true,
                    'weight' => 1,
                    'sort_order' => $index + 1,
                ]);
                $question->options()->createMany(collect($compliance)->map(fn (array $o, int $i): array => [...$o, 'sort_order' => $i + 1])->all());
            }
        }

        $version->update([
            'status' => InstrumentVersionStatus::Published,
            'published_by' => $admin->id,
            'published_at' => now()->subDays(30),
            'approved_by' => $admin->id,
            'approved_at' => now()->subDays(31),
        ]);
    }
}
