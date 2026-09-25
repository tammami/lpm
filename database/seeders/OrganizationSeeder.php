<?php

namespace Database\Seeders;

use App\Models\AccreditationBody;
use App\Models\Faculty;
use App\Models\Institution;
use App\Models\StudyProgram;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Struktur awal IAIA NU Lombok Timur. Seluruhnya dapat diubah melalui master data.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->updateOrCreate(['short_name' => 'IAIA NU'], [
            'name' => 'IAIA NU Lombok Timur',
            'address' => 'Kabupaten Lombok Timur, Nusa Tenggara Barat',
            'rector_name' => null,
            'lpm_head_name' => null,
            'is_active' => true,
        ]);

        $lamdik = AccreditationBody::query()->updateOrCreate(['code' => 'LAMDIK'], [
            'name' => 'Lembaga Akreditasi Mandiri Kependidikan',
            'description' => 'Akreditasi program studi kependidikan (Tadris/Pendidikan). Instrumen aktif: IAPSK 3.0.',
        ]);

        $lamgama = AccreditationBody::query()->updateOrCreate(['code' => 'LAMGAMA'], [
            'name' => 'Lembaga Akreditasi Mandiri Perguruan Tinggi Keagamaan',
            'description' => 'Akreditasi program studi keagamaan dengan kerangka CRAM (Culture, Relevance, Accountability, Mission Differentiation).',
        ]);

        AccreditationBody::query()->updateOrCreate(['code' => 'BAN-PT'], [
            'name' => 'Badan Akreditasi Nasional Perguruan Tinggi',
            'description' => 'Akreditasi institusi (APT).',
        ]);

        $faculty = Faculty::query()->updateOrCreate(['code' => 'FTK'], [
            'institution_id' => $institution->id,
            'name' => 'Fakultas Tarbiyah dan Keguruan',
            'dean_name' => 'Dekan FTK',
        ]);

        $programs = [
            ['code' => 'TFIS', 'name' => 'Tadris Fisika', 'body' => $lamdik, 'status' => 'Baik', 'until' => '2027-03-14'],
            ['code' => 'TMTK', 'name' => 'Tadris Matematika', 'body' => $lamdik, 'status' => 'Baik Sekali', 'until' => '2028-08-20'],
            ['code' => 'TBIG', 'name' => 'Tadris Bahasa Inggris', 'body' => $lamdik, 'status' => 'Baik', 'until' => '2026-12-31'],
            ['code' => 'PAI', 'name' => 'Pendidikan Agama Islam', 'body' => $lamgama, 'status' => 'Baik Sekali', 'until' => '2029-05-10'],
            ['code' => 'PIAUD', 'name' => 'Pendidikan Islam Anak Usia Dini', 'body' => $lamgama, 'status' => 'Baik', 'until' => '2027-09-30'],
        ];

        foreach ($programs as $program) {
            StudyProgram::query()->updateOrCreate(['code' => $program['code']], [
                'faculty_id' => $faculty->id,
                'accreditation_body_id' => $program['body']->id,
                'name' => $program['name'],
                'degree' => 'S1',
                'accreditation_status' => $program['status'],
                'accreditation_valid_until' => $program['until'],
                'head_name' => 'Kaprodi '.$program['name'],
            ]);
        }

        $units = [
            ['code' => 'LPM', 'name' => 'Lembaga Penjaminan Mutu', 'type' => 'lembaga'],
            ['code' => 'LP2M', 'name' => 'Lembaga Penelitian dan Pengabdian kepada Masyarakat', 'type' => 'lembaga'],
            ['code' => 'BAAK', 'name' => 'Biro Administrasi Akademik dan Kemahasiswaan', 'type' => 'biro'],
            ['code' => 'BAUK', 'name' => 'Biro Administrasi Umum dan Keuangan', 'type' => 'biro'],
            ['code' => 'UPT-PUS', 'name' => 'UPT Perpustakaan', 'type' => 'upt'],
            ['code' => 'UPT-TIK', 'name' => 'UPT Teknologi Informasi dan Pangkalan Data', 'type' => 'upt'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(['code' => $unit['code']], [...$unit, 'institution_id' => $institution->id]);
        }
    }
}
