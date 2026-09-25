<?php

namespace Database\Seeders;

use App\Enums\ParticipationStatus;
use App\Enums\QuestionType;
use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Models\AcademicPeriod;
use App\Models\Instrument;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentVersion;
use App\Models\Student;
use App\Models\Survey;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Monev\ScoreCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Kegiatan Monev contoh beserta respons simulasi (untuk demo dashboard & analitik).
 * Respons dibuat langsung (tanpa melewati portal) dengan pola mutu per dosen yang realistis.
 */
class MonevDemoSeeder extends Seeder
{
    /**
     * Kecenderungan per indikator (negatif = cenderung lebih rendah).
     *
     * @var array<string, float>
     */
    private const ITEM_BIAS = [
        'A1' => 0.15, 'A2' => 0.05, 'A3' => 0.1, 'A4' => -0.2,
        'B1' => 0.1, 'B2' => 0.25, 'B3' => -0.05, 'B4' => -0.25,
        'C1' => 0.05, 'C2' => -0.1, 'C3' => -0.45,
        'D1' => -0.15, 'D2' => -0.5,
    ];

    private const POSITIVE = [
        'Penjelasan dosen runtut dan mudah dipahami.',
        'Dosen sabar menjawab pertanyaan mahasiswa.',
        'Contoh-contoh yang diberikan dekat dengan kehidupan sehari-hari.',
        'Diskusi kelompok membuat materi lebih mudah dipahami.',
        'RPS dijelaskan sejak awal sehingga kami tahu target setiap pertemuan.',
        'Dosen selalu datang tepat waktu dan disiplin.',
        'Materi dikaitkan dengan nilai-nilai keislaman dan praktik mengajar di sekolah.',
    ];

    private const SUGGESTIONS = [
        'Mohon umpan balik tugas diberikan lebih cepat.',
        'LMS sebaiknya dimanfaatkan untuk berbagi materi dan tugas.',
        'Perbanyak latihan soal sebelum ujian.',
        'Slide presentasi terlalu padat, mohon disederhanakan.',
        'Kriteria penilaian tugas mohon dijelaskan di awal.',
        'Metode pembelajaran bisa lebih bervariasi, tidak hanya ceramah.',
        'Jadwal pengganti perkuliahan mohon diinformasikan lebih awal.',
    ];

    /**
     * @var array<int, float>
     */
    private array $lecturerQuality = [];

    public function __construct(private ScoreCalculator $calculator) {}

    public function run(): void
    {
        mt_srand(4242);

        $instrument = Instrument::query()->where('code', 'MONEV-PBM')->first();

        if (! $instrument || Survey::query()->exists()) {
            return;
        }

        $v10 = $instrument->versions()->where('version', '1.0')->with('questions.options')->first();
        $v11 = $instrument->versions()->where('version', '1.1')->with('questions.options')->first();
        $admin = User::query()->where('username', 'lpm')->first();

        $periods = AcademicPeriod::query()->orderBy('starts_on')->get()->keyBy('code');

        $plan = [
            ['period' => '20251', 'version' => $v10, 'status' => SurveyStatus::Archived, 'rate' => 0.86, 'drift' => -0.12, 'start' => '2025-12-01', 'end' => '2025-12-20'],
            ['period' => '20252', 'version' => $v10, 'status' => SurveyStatus::Closed, 'rate' => 0.81, 'drift' => 0.0, 'start' => '2026-06-01', 'end' => '2026-06-21'],
            ['period' => '20261', 'version' => $v11, 'status' => SurveyStatus::Active, 'rate' => 0.56, 'drift' => 0.08, 'start' => now()->subDays(10)->toDateString(), 'end' => now()->addDays(12)->toDateString()],
        ];

        foreach ($plan as $item) {
            $period = $periods->get($item['period']);

            if (! $period) {
                continue;
            }

            $survey = Survey::query()->create([
                'code' => "EVP-{$period->code}-".Str::upper(Str::random(4)),
                'title' => "Monev Pembelajaran {$period->name}",
                'description' => 'Isilah evaluasi untuk setiap dosen pengampu secara jujur dan objektif. Jawaban Anda anonim dan digunakan untuk peningkatan mutu pembelajaran.',
                'instrument_version_id' => $item['version']->id,
                'academic_period_id' => $period->id,
                'mode' => SurveyMode::TeachingEvaluation,
                'respondent_type' => 'mahasiswa',
                'is_anonymous' => true,
                'starts_at' => Carbon::parse($item['start'])->setTime(8, 0),
                'ends_at' => Carbon::parse($item['end'])->setTime(23, 59),
                'status' => $item['status'],
                'created_by' => $admin?->id,
                'opened_at' => Carbon::parse($item['start']),
                'closed_at' => $item['status'] === SurveyStatus::Active ? null : Carbon::parse($item['end']),
            ]);

            $this->generateTeachingResponses($survey, $item['version'], $item['rate'], $item['drift']);
        }

        $this->seedSatisfactionSurvey($periods->get('20252'), $admin);
    }

    private function generateTeachingResponses(Survey $survey, InstrumentVersion $version, float $rate, float $drift): void
    {
        $assignments = TeachingAssignment::query()
            ->whereHas('courseClass', fn ($q) => $q->where('academic_period_id', $survey->academic_period_id))
            ->with(['courseClass.course', 'courseClass.students:id,user_id,status'])
            ->get();

        $questions = $version->questions->where('is_active', true)->values();
        // Akun demo mahasiswa dibiarkan belum mengisi Monev yang sedang berjalan agar alur pengisian dapat dicoba.
        $demoUserId = $survey->status === SurveyStatus::Active ? User::query()->where('email', 'mahasiswa@simutu.test')->value('id') : null;

        foreach ($assignments as $assignment) {
            $quality = $this->qualityOf($assignment->lecturer_id) + $drift;
            // Beberapa kelas sengaja rendah partisipasinya agar ambang minimum terlihat.
            $classRate = mt_rand(0, 100) < 12 ? $rate * 0.25 : $rate;

            foreach ($assignment->courseClass->students as $student) {
                /** @var Student $student */
                if (! $student->user_id || $student->user_id === $demoUserId || mt_rand(0, 1000) / 1000 > $classRate) {
                    continue;
                }

                $this->storeResponse($survey, $version, $questions->all(), $assignment, $student->user_id, $quality);
            }
        }
    }

    /**
     * @param  list<InstrumentQuestion>  $questions
     */
    private function storeResponse(Survey $survey, InstrumentVersion $version, array $questions, ?TeachingAssignment $assignment, int $userId, float $quality, ?int $studyProgramId = null): void
    {
        $window = (int) max(60, $survey->starts_at->diffInMinutes(min(now(), $survey->ends_at)));
        $submittedAt = Carbon::parse($survey->starts_at)->addMinutes(mt_rand(0, $window));
        $rows = [];
        $items = [];

        foreach ($questions as $question) {
            if ($question->type === QuestionType::LongText) {
                $pool = str_ends_with($question->code, '1') ? self::POSITIVE : self::SUGGESTIONS;

                if (mt_rand(0, 100) < 28) {
                    $rows[] = ['instrument_question_id' => $question->id, 'instrument_question_option_id' => null, 'value_text' => $pool[array_rand($pool)], 'value_number' => null, 'value_json' => null, 'score' => null];
                }

                continue;
            }

            $target = $quality + (self::ITEM_BIAS[$question->code] ?? 0) + (mt_rand(-100, 100) / 100) * 0.55;
            $scaleMax = (int) $version->scale_max;
            $value = max(1, min($scaleMax, (int) round($target * ($scaleMax / 4))));
            $option = $question->options->firstWhere('score', (float) $value) ?? $question->options->last();
            $score = $this->calculator->answerScore($question, $version, $option->id);

            $rows[] = ['instrument_question_id' => $question->id, 'instrument_question_option_id' => $option->id, 'value_text' => $option->value, 'value_number' => null, 'value_json' => null, 'score' => $score];
            $items[] = ['score' => $score, 'weight' => $question->weight];
        }

        $responseId = (string) Str::uuid();

        DB::table('responses')->insert([
            'id' => $responseId,
            'survey_id' => $survey->id,
            'instrument_version_id' => $version->id,
            'teaching_assignment_id' => $assignment?->id,
            'course_class_id' => $assignment?->course_class_id,
            'lecturer_id' => $assignment?->lecturer_id,
            'study_program_id' => $studyProgramId ?? $assignment?->courseClass->course->study_program_id,
            'respondent_user_id' => null,
            'scoring_method' => $version->scoring_method->value,
            'score' => $this->calculator->responseScore($items, $version->scoring_method),
            'submitted_on' => $submittedAt->toDateString(),
        ]);

        DB::table('response_answers')->insert(array_map(fn (array $row): array => [...$row, 'response_id' => $responseId], $rows));

        DB::table('survey_participations')->insert([
            'survey_id' => $survey->id,
            'user_id' => $userId,
            'teaching_assignment_id' => $assignment?->id,
            'target_key' => $assignment ? "ta:{$assignment->id}" : 'general',
            'status' => ParticipationStatus::Submitted->value,
            'response_ref' => encrypt($responseId, false),
            'submitted_at' => $submittedAt,
            'submission_count' => 1,
            'created_at' => $submittedAt,
            'updated_at' => $submittedAt,
        ]);
    }

    private function seedSatisfactionSurvey(?AcademicPeriod $period, ?User $admin): void
    {
        $instrument = Instrument::query()->where('code', 'SURVEI-LAYANAN')->first();

        if (! $instrument || ! $period) {
            return;
        }

        $version = $instrument->versions()->with('questions.options')->first();
        $survey = Survey::query()->create([
            'code' => "SRV-{$period->code}-".Str::upper(Str::random(4)),
            'title' => "Survei Kepuasan Layanan Akademik {$period->academic_year}",
            'description' => 'Bantu kami meningkatkan layanan akademik dan sarana kampus.',
            'instrument_version_id' => $version->id,
            'academic_period_id' => $period->id,
            'mode' => SurveyMode::General,
            'respondent_type' => 'mahasiswa',
            'is_anonymous' => true,
            'starts_at' => Carbon::parse('2026-05-10 08:00'),
            'ends_at' => Carbon::parse('2026-05-31 23:59'),
            'status' => SurveyStatus::Closed,
            'created_by' => $admin?->id,
            'opened_at' => Carbon::parse('2026-05-10'),
            'closed_at' => Carbon::parse('2026-05-31'),
        ]);

        $questions = $version->questions->values()->all();
        $students = Student::query()->whereNotNull('user_id')->where('entry_year', '<', 2026)->get();

        foreach ($students as $student) {
            if (mt_rand(0, 100) > 72) {
                continue;
            }

            $this->storeResponse($survey, $version, $questions, null, $student->user_id, 3.05 + mt_rand(-40, 40) / 100, $student->study_program_id);
        }
    }

    private function qualityOf(int $lecturerId): float
    {
        return $this->lecturerQuality[$lecturerId] ??= round(2.55 + mt_rand(0, 125) / 100, 2);
    }
}
