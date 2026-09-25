<?php

namespace App\Services\Monev;

use App\Enums\ParticipationStatus;
use App\Enums\QuestionType;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentVersion;
use App\Models\Response;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Menyimpan pengisian responden: validasi jawaban, hitung skor, simpan respons anonim,
 * dan tandai partisipasi — semuanya dalam satu transaksi.
 */
class ResponseSubmitter
{
    public function __construct(private EligibilityService $eligibility, private ScoreCalculator $calculator) {}

    /**
     * @param  array<int|string, mixed>  $answers  question_id => nilai
     *
     * @throws ValidationException
     */
    public function submit(Survey $survey, User $user, ?int $teachingAssignmentId, array $answers): SurveyParticipation
    {
        if (! $survey->isOpen()) {
            throw ValidationException::withMessages(['survey' => 'Monev ini tidak sedang dibuka untuk pengisian.']);
        }

        $target = $this->eligibility->findTarget($survey, $user, $teachingAssignmentId);

        if (! $target) {
            throw ValidationException::withMessages(['survey' => 'Anda tidak terdaftar sebagai responden untuk evaluasi ini.']);
        }

        $version = $survey->instrumentVersion()->with(['questions' => fn ($q) => $q->where('is_active', true), 'questions.options'])->firstOrFail();
        $rows = $this->validateAnswers($version, $answers);

        try {
            return DB::transaction(function () use ($survey, $user, $target, $version, $rows): SurveyParticipation {
                $participation = SurveyParticipation::query()
                    ->where('survey_id', $survey->id)
                    ->where('user_id', $user->id)
                    ->where('target_key', $target['target_key'])
                    ->lockForUpdate()
                    ->first();

                if ($participation && $participation->status === ParticipationStatus::Submitted) {
                    throw ValidationException::withMessages(['survey' => 'Evaluasi ini sudah Anda kirim dan tidak dapat diubah.']);
                }

                $assignment = $target['teaching_assignment_id']
                    ? TeachingAssignment::query()->with('courseClass')->find($target['teaching_assignment_id'])
                    : null;

                $items = array_map(fn (array $row): array => ['score' => $row['score'], 'weight' => $row['weight']], $rows);

                $response = Response::query()->create([
                    'survey_id' => $survey->id,
                    'instrument_version_id' => $version->id,
                    'teaching_assignment_id' => $assignment?->id,
                    'course_class_id' => $assignment?->course_class_id,
                    'lecturer_id' => $assignment?->lecturer_id,
                    'study_program_id' => $target['study_program_id'],
                    'respondent_user_id' => $survey->is_anonymous ? null : $user->id,
                    'scoring_method' => $version->scoring_method,
                    'score' => $this->calculator->responseScore($items, $version->scoring_method),
                    'submitted_on' => now()->toDateString(),
                ]);

                $response->answers()->createMany(array_map(
                    fn (array $row): array => array_diff_key($row, ['weight' => true]),
                    $rows,
                ));

                if ($participation) {
                    $participation->update([
                        'status' => ParticipationStatus::Submitted,
                        'response_ref' => $response->id,
                        'submitted_at' => now(),
                        'submission_count' => $participation->submission_count + 1,
                    ]);

                    return $participation;
                }

                return SurveyParticipation::query()->create([
                    'survey_id' => $survey->id,
                    'user_id' => $user->id,
                    'teaching_assignment_id' => $assignment?->id,
                    'target_key' => $target['target_key'],
                    'status' => ParticipationStatus::Submitted,
                    'response_ref' => $response->id,
                    'submitted_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw ValidationException::withMessages(['survey' => 'Evaluasi ini sudah Anda kirim dan tidak dapat diubah.']);
            }

            throw $exception;
        }
    }

    /**
     * Buka kembali pengisian secara resmi (BR-002): respons lama dibatalkan, responden dapat mengisi ulang.
     */
    public function reopen(SurveyParticipation $participation, User $admin, string $reason): void
    {
        DB::transaction(function () use ($participation, $admin, $reason): void {
            if ($participation->response_ref) {
                Response::query()->whereKey($participation->response_ref)->update(['voided_at' => now()]);
            }

            $participation->update([
                'status' => ParticipationStatus::Reopened,
                'response_ref' => null,
                'reopened_at' => now(),
                'reopened_by' => $admin->id,
                'reopen_reason' => $reason,
            ]);
        });
    }

    /**
     * @param  array<int|string, mixed>  $answers
     * @return list<array{instrument_question_id: int, instrument_question_option_id: int|null, value_text: string|null, value_number: float|null, value_json: list<int>|null, score: float|null, weight: float}>
     *
     * @throws ValidationException
     */
    private function validateAnswers(InstrumentVersion $version, array $answers): array
    {
        $errors = [];
        $rows = [];

        foreach ($version->questions as $question) {
            /** @var InstrumentQuestion $question */
            $key = "answers.{$question->id}";
            $raw = $answers[$question->id] ?? $answers[(string) $question->id] ?? null;
            $empty = $raw === null || $raw === '' || $raw === [];

            if ($empty) {
                if ($question->is_required && $question->type !== QuestionType::FileUpload) {
                    $errors[$key] = 'Butir ini wajib diisi.';
                }

                continue;
            }

            $row = [
                'instrument_question_id' => $question->id,
                'instrument_question_option_id' => null,
                'value_text' => null,
                'value_number' => null,
                'value_json' => null,
            ];

            switch ($question->type) {
                case QuestionType::Likert:
                case QuestionType::SingleChoice:
                case QuestionType::YesNo:
                    $option = $question->options->firstWhere('id', (int) $raw);
                    if (! $option || is_array($raw)) {
                        $errors[$key] = 'Pilihan jawaban tidak valid.';

                        continue 2;
                    }
                    $row['instrument_question_option_id'] = $option->id;
                    $row['value_text'] = $option->value;
                    break;

                case QuestionType::MultipleChoice:
                    $ids = array_values(array_unique(array_map('intval', (array) $raw)));
                    if (array_diff($ids, $question->options->pluck('id')->all()) !== []) {
                        $errors[$key] = 'Pilihan jawaban tidak valid.';

                        continue 2;
                    }
                    $row['value_json'] = $ids;
                    break;

                case QuestionType::Rating:
                    $max = (int) ($question->settings['max_rating'] ?? 5);
                    if (! is_numeric($raw) || (int) $raw < 1 || (int) $raw > $max) {
                        $errors[$key] = "Pilih 1 sampai {$max} bintang.";

                        continue 2;
                    }
                    $row['value_number'] = (int) $raw;
                    break;

                case QuestionType::Numeric:
                    $min = $question->settings['min'] ?? null;
                    $max = $question->settings['max'] ?? null;
                    if (! is_numeric($raw) || ($min !== null && $raw < $min) || ($max !== null && $raw > $max)) {
                        $errors[$key] = 'Nilai angka di luar rentang yang diperbolehkan.';

                        continue 2;
                    }
                    $row['value_number'] = (float) $raw;
                    break;

                case QuestionType::Date:
                    if (! is_string($raw) || strtotime($raw) === false) {
                        $errors[$key] = 'Tanggal tidak valid.';

                        continue 2;
                    }
                    $row['value_text'] = date('Y-m-d', strtotime($raw));
                    break;

                default:
                    $text = trim((string) (is_array($raw) ? '' : $raw));
                    $limit = (int) ($question->settings['max_length'] ?? ($question->type === QuestionType::LongText ? 2000 : 255));
                    if (mb_strlen($text) > $limit) {
                        $errors[$key] = "Maksimal {$limit} karakter.";

                        continue 2;
                    }
                    if ($text === '') {
                        continue 2;
                    }
                    $row['value_text'] = strip_tags($text);
            }

            $value = $row['instrument_question_option_id'] ?? $row['value_json'] ?? $row['value_number'] ?? $row['value_text'];
            $row['score'] = $this->calculator->answerScore($question, $version, $value);
            $row['weight'] = $question->weight;
            $rows[] = $row;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $rows;
    }
}
