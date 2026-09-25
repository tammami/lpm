<?php

namespace Database\Seeders;

use App\Enums\AcademicSemester;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data akademik contoh agar dashboard & analitik langsung dapat dicoba.
 * Akun demo: dosen@simutu.test & mahasiswa@simutu.test (kata sandi "password").
 */
class AcademicDemoSeeder extends Seeder
{
    private const MALE = ['Ahmad', 'Muhammad', 'Lalu', 'Zainul', 'Hairul', 'Fathurrahman', 'Rahmatullah', 'Abdul', 'Hamzan', 'Saiful', 'Irwan', 'Zulkifli', 'Hasanuddin', 'Mahsun', 'Taufik', 'Rizal', 'Suhaimi', 'Wahyudi', 'Khairul', 'Munawir'];

    private const FEMALE = ['Siti', 'Baiq', 'Nurul', 'Aisyah', 'Fatimah', 'Khairunnisa', 'Rahmawati', 'Hidayati', 'Nurhasanah', 'Laila', 'Ummi', 'Zahratul', 'Husnul', 'Rosyidah', 'Maulida', 'Wardah', 'Syarifah', 'Azizah', 'Hilyatul', 'Nadia'];

    private const LAST = ['Hakim', 'Hidayat', 'Fauzi', 'Anwar', 'Maulana', 'Rahman', 'Hamdi', 'Ilhami', 'Mustofa', 'Sahrul', 'Azhari', 'Jayadi', 'Kurniawan', 'Nasrullah', 'Rizki', 'Ridwan', 'Supriadi', 'Wathoni', 'Yusuf', 'Zuhdi', 'Aini', 'Fitriani', 'Hayati', 'Jannah', 'Khotimah', 'Lestari', 'Mardiyah', 'Safitri', 'Ulfa', 'Wulandari'];

    /**
     * @var array<string, list<array{0: string, 1: int}>>
     */
    private const COURSES = [
        'TFIS' => [['Fisika Dasar I', 1], ['Kalkulus Fisika', 1], ['Mekanika', 3], ['Listrik Magnet', 3], ['Strategi Pembelajaran Fisika', 5], ['Evaluasi Pembelajaran Fisika', 5], ['Fisika Dasar II', 2], ['Termodinamika', 4], ['Gelombang dan Optik', 4], ['Media Pembelajaran Fisika', 6], ['Ke-NU-an', 7]],
        'TMTK' => [['Kalkulus I', 1], ['Pengantar Dasar Matematika', 1], ['Matematika Diskrit', 3], ['Aljabar Linear', 3], ['Strategi Pembelajaran Matematika', 5], ['Analisis Real', 5], ['Kalkulus II', 2], ['Statistika Dasar', 4], ['Geometri', 4], ['Struktur Aljabar', 6], ['Ke-NU-an', 7]],
        'TBIG' => [['Speaking for Everyday Communication', 1], ['Basic Reading', 1], ['English Grammar', 3], ['Introduction to Linguistics', 3], ['TEFL Methodology', 5], ['Language Assessment', 5], ['Listening Comprehension', 2], ['Writing for Academic Purposes', 4], ['Literature Appreciation', 4], ['Curriculum and Material Development', 6], ['Ke-NU-an', 7]],
        'PAI' => [['Ulumul Qur\'an', 1], ['Metodologi Studi Islam', 1], ['Fiqih Ibadah', 3], ['Sejarah Peradaban Islam', 3], ['Strategi Pembelajaran PAI', 5], ['Evaluasi Pembelajaran PAI', 5], ['Ulumul Hadis', 2], ['Akidah Akhlak', 4], ['Tafsir Tarbawi', 4], ['Pengembangan Kurikulum PAI', 6], ['Ke-NU-an', 7]],
        'PIAUD' => [['Psikologi Perkembangan Anak', 1], ['Pengantar PAUD', 1], ['Pengembangan Bahasa AUD', 3], ['Pengembangan Kognitif AUD', 3], ['Asesmen Perkembangan AUD', 5], ['Manajemen PAUD', 5], ['Bermain dan Permainan AUD', 2], ['Seni dan Kreativitas AUD', 4], ['Pendidikan Agama Islam AUD', 4], ['Parenting Islami', 6], ['Ke-NU-an', 7]],
    ];

    private string $passwordHash;

    public function run(): void
    {
        mt_srand(2026);
        $this->passwordHash = Hash::make('password');

        $periods = $this->seedPeriods();

        DB::transaction(function () use ($periods): void {
            $programs = StudyProgram::query()->orderBy('id')->get();

            foreach ($programs as $programIndex => $program) {
                $lecturers = $this->seedLecturers($program, $programIndex);
                $students = $this->seedStudents($program, $programIndex);
                $courses = $this->seedCourses($program);

                foreach ($periods as $period) {
                    $this->seedClasses($period, $courses, $lecturers, $students);
                }
            }
        });
    }

    /**
     * @return Collection<int, AcademicPeriod>
     */
    private function seedPeriods(): Collection
    {
        $definitions = [
            ['code' => '20251', 'name' => 'Ganjil 2025/2026', 'academic_year' => '2025/2026', 'semester' => AcademicSemester::Ganjil, 'starts_on' => '2025-09-01', 'ends_on' => '2026-01-31'],
            ['code' => '20252', 'name' => 'Genap 2025/2026', 'academic_year' => '2025/2026', 'semester' => AcademicSemester::Genap, 'starts_on' => '2026-02-01', 'ends_on' => '2026-07-31'],
            ['code' => '20261', 'name' => 'Ganjil 2026/2027', 'academic_year' => '2026/2027', 'semester' => AcademicSemester::Ganjil, 'starts_on' => '2026-09-01', 'ends_on' => '2027-01-31'],
        ];

        AcademicPeriod::query()->update(['is_active' => false]);

        return collect($definitions)->map(fn (array $definition): AcademicPeriod => AcademicPeriod::query()->updateOrCreate(
            ['code' => $definition['code']],
            [...$definition, 'is_active' => $definition['code'] === '20261'],
        ));
    }

    /**
     * @return Collection<int, Lecturer>
     */
    private function seedLecturers(StudyProgram $program, int $programIndex): Collection
    {
        $titles = [
            'TFIS' => 'M.Pd.', 'TMTK' => 'M.Pd.', 'TBIG' => 'M.Pd.', 'PAI' => 'M.Pd.I.', 'PIAUD' => 'M.Pd.',
        ];
        $ranks = ['Asisten Ahli', 'Lektor', 'Lektor', 'Lektor Kepala', 'Tenaga Pengajar'];

        return collect(range(1, 7))->map(function (int $number) use ($program, $programIndex, $titles, $ranks): Lecturer {
            $female = $number % 3 === 0;
            $name = $this->randomName($female);
            $nidn = sprintf('08%02d%06d', $programIndex + 10, 1000 + $number * 37);
            $isDemo = $program->code === 'TMTK' && $number === 1;

            $user = User::query()->updateOrCreate(['username' => $nidn], [
                'name' => $name,
                'email' => $isDemo ? 'dosen@simutu.test' : Str::slug($name, '.').".{$number}{$programIndex}@simutu.test",
                'password' => $this->passwordHash,
                'is_active' => true,
            ]);
            $user->syncRoles([UserRole::Dosen->value]);

            return Lecturer::query()->updateOrCreate(['nidn' => $nidn], [
                'user_id' => $user->id,
                'study_program_id' => $program->id,
                'name' => $name,
                'front_title' => $number <= 2 ? 'Dr.' : null,
                'back_title' => $number === 7 ? 'S.Pd., M.Ed.' : $titles[$program->code] ?? 'M.Pd.',
                'email' => $user->email,
                'gender' => $female ? 'P' : 'L',
                'academic_rank' => $ranks[$number % count($ranks)],
                'employment_status' => $number <= 5 ? 'tetap' : 'tidak_tetap',
                'is_active' => true,
            ]);
        });
    }

    /**
     * @return Collection<int, Student>
     */
    private function seedStudents(StudyProgram $program, int $programIndex): Collection
    {
        $students = collect();

        foreach ([2023, 2024, 2025, 2026] as $cohort) {
            foreach (range(1, 12) as $number) {
                $female = mt_rand(0, 100) < 58;
                $name = $this->randomName($female);
                $nim = sprintf('%02d%02d%03d', $cohort % 100, $programIndex + 11, $number);
                $isDemo = $program->code === 'TMTK' && $cohort === 2025 && $number === 1;
                $semester = (2026 - $cohort) * 2 + 1;

                $user = User::query()->updateOrCreate(['username' => $nim], [
                    'name' => $name,
                    'email' => $isDemo ? 'mahasiswa@simutu.test' : "{$nim}@student.simutu.test",
                    'password' => $this->passwordHash,
                    'is_active' => true,
                ]);
                $user->syncRoles([UserRole::Mahasiswa->value]);

                $students->push(Student::query()->updateOrCreate(['nim' => $nim], [
                    'user_id' => $user->id,
                    'study_program_id' => $program->id,
                    'name' => $name,
                    'email' => $user->email,
                    'gender' => $female ? 'P' : 'L',
                    'entry_year' => $cohort,
                    'semester' => $semester,
                    'status' => StudentStatus::Aktif,
                ]));
            }
        }

        return $students;
    }

    /**
     * @return Collection<int, Course>
     */
    private function seedCourses(StudyProgram $program): Collection
    {
        return collect(self::COURSES[$program->code] ?? [])->values()->map(function (array $course, int $index) use ($program): Course {
            [$name, $semester] = $course;

            return Course::query()->updateOrCreate(
                ['study_program_id' => $program->id, 'code' => sprintf('%s%d%02d', $program->code, $semester, $index + 1)],
                ['name' => $name, 'credits' => $name === 'Ke-NU-an' ? 2 : (mt_rand(0, 1) ? 3 : 2), 'semester' => $semester, 'type' => 'wajib', 'is_active' => true],
            );
        });
    }

    /**
     * @param  Collection<int, Course>  $courses
     * @param  Collection<int, Lecturer>  $lecturers
     * @param  Collection<int, Student>  $students
     */
    private function seedClasses(AcademicPeriod $period, Collection $courses, Collection $lecturers, Collection $students): void
    {
        $startYear = (int) substr($period->academic_year, 0, 4);
        $odd = $period->semester === AcademicSemester::Ganjil;

        foreach ($courses as $index => $course) {
            if (($course->semester % 2 === 1) !== $odd) {
                continue;
            }

            $cohort = $startYear - intdiv($course->semester - 1, 2);
            $enrolled = $students->where('entry_year', $cohort);

            if ($enrolled->isEmpty()) {
                continue;
            }

            $class = CourseClass::query()->updateOrCreate(
                ['course_id' => $course->id, 'academic_period_id' => $period->id, 'code' => 'A'],
                ['capacity' => 40],
            );

            $primary = $lecturers[($index + $startYear) % $lecturers->count()];
            $class->lecturers()->syncWithoutDetaching([$primary->id => ['role' => 'koordinator']]);

            if ($index % 4 === 1) {
                $partner = $lecturers[($index + $startYear + 3) % $lecturers->count()];
                if ($partner->id !== $primary->id) {
                    $class->lecturers()->syncWithoutDetaching([$partner->id => ['role' => 'anggota']]);
                }
            }

            $class->students()->syncWithoutDetaching($enrolled->pluck('id')->all());
        }
    }

    private function randomName(bool $female): string
    {
        $first = $female ? self::FEMALE : self::MALE;

        return $first[mt_rand(0, count($first) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)];
    }
}
