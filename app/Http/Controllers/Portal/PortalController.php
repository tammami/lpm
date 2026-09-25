<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ParticipationStatus;
use App\Enums\SurveyStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Instruments\InstrumentVersionController;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentSection;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Services\Monev\EligibilityService;
use App\Services\Monev\ResponseSubmitter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal responden (mahasiswa & dosen) untuk mengisi Monev/survei.
 */
class PortalController extends Controller
{
    public function home(Request $request, EligibilityService $eligibility): Response
    {
        $user = $request->user()->load(['student.studyProgram', 'lecturer.studyProgram']);

        $surveys = Survey::query()
            ->whereIn('status', [SurveyStatus::Active, SurveyStatus::Closed])
            ->where('ends_at', '>=', now()->subDays(45))
            ->with(['instrumentVersion.instrument', 'academicPeriod', 'studyPrograms:id'])
            ->orderByDesc('status')
            ->orderBy('ends_at')
            ->get();

        $participations = SurveyParticipation::query()
            ->where('user_id', $user->id)
            ->whereIn('survey_id', $surveys->pluck('id'))
            ->get()
            ->keyBy(fn (SurveyParticipation $p): string => "{$p->survey_id}|{$p->target_key}");

        $items = $surveys
            ->map(function (Survey $survey) use ($eligibility, $user, $participations): ?array {
                $targets = $eligibility->targetsFor($survey, $user)->map(function (array $target) use ($survey, $participations): array {
                    $participation = $participations->get("{$survey->id}|{$target['target_key']}");

                    return [
                        ...$target,
                        'status' => $participation?->status === ParticipationStatus::Submitted ? 'submitted' : ($participation ? 'reopened' : 'pending'),
                        'submitted_at' => $participation?->status === ParticipationStatus::Submitted ? $participation->submitted_at?->toIso8601String() : null,
                        'reopen_reason' => $participation?->status === ParticipationStatus::Reopened ? $participation->reopen_reason : null,
                    ];
                });

                if ($targets->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'description' => $survey->description,
                    'mode' => $survey->mode->value,
                    'is_open' => $survey->isOpen(),
                    'is_anonymous' => $survey->is_anonymous,
                    'starts_at' => $survey->starts_at->toIso8601String(),
                    'ends_at' => $survey->ends_at->toIso8601String(),
                    'period' => $survey->academicPeriod?->name,
                    'instrument' => $survey->instrumentVersion->instrument->name,
                    'questions_count' => $survey->instrumentVersion->questions()->where('is_active', true)->count(),
                    'targets' => $targets->values()->all(),
                ];
            })
            ->filter()
            ->values();

        $profile = $user->student ?? $user->lecturer;

        return Inertia::render('portal/home', [
            'profile' => [
                'name' => $user->name,
                'identifier' => $user->student?->nim ?? $user->lecturer?->nidn ?? $user->username,
                'study_program' => $profile?->studyProgram?->full_name,
                'semester' => $user->student?->semester,
            ],
            'surveys' => $items->all(),
            'highlight' => $request->session()->get('submitted_target'),
        ]);
    }

    public function show(Request $request, Survey $survey, EligibilityService $eligibility): Response|RedirectResponse
    {
        $teachingAssignmentId = $request->integer('target') ?: null;
        $target = $eligibility->findTarget($survey, $request->user(), $teachingAssignmentId);

        abort_unless($target !== null, 403, 'Anda tidak terdaftar sebagai responden untuk evaluasi ini.');

        $participation = SurveyParticipation::query()
            ->where('survey_id', $survey->id)
            ->where('user_id', $request->user()->id)
            ->where('target_key', $target['target_key'])
            ->first();

        if ($participation?->status === ParticipationStatus::Submitted) {
            $this->toast('Evaluasi ini sudah Anda kirim. Terima kasih!', 'info');

            return redirect()->route('portal.home');
        }

        if (! $survey->isOpen()) {
            $this->toast('Monev ini sedang tidak dibuka untuk pengisian.', 'warning');

            return redirect()->route('portal.home');
        }

        $version = $survey->instrumentVersion()->with([
            'instrument',
            'sections.questions' => fn ($query) => $query->where('is_active', true),
            'sections.questions.options',
        ])->firstOrFail();

        return Inertia::render('portal/fill', [
            'survey' => [
                'id' => $survey->id,
                'title' => $survey->title,
                'description' => $survey->description,
                'is_anonymous' => $survey->is_anonymous,
                'ends_at' => $survey->ends_at->toIso8601String(),
                'instrument' => $version->instrument->name,
                'version' => $version->version,
            ],
            'target' => $target,
            'reopenReason' => $participation?->reopen_reason,
            'sections' => $version->sections
                ->filter(fn (InstrumentSection $section): bool => $section->questions->isNotEmpty())
                ->map(fn (InstrumentSection $section): array => [
                    ...$section->only(['id', 'code', 'title', 'description']),
                    'questions' => $section->questions
                        ->map(fn (InstrumentQuestion $question): array => InstrumentVersionController::questionPayload($question))
                        ->all(),
                ])->values()->all(),
        ]);
    }

    public function submit(Request $request, Survey $survey, ResponseSubmitter $submitter): RedirectResponse
    {
        $validated = $request->validate([
            'target' => ['nullable', 'integer'],
            'answers' => ['array'],
        ]);

        $submitter->submit($survey, $request->user(), $validated['target'] ?? null, $validated['answers'] ?? []);

        $this->toast($survey->is_anonymous
            ? 'Terima kasih! Evaluasi Anda berhasil dikirim secara anonim.'
            : 'Terima kasih! Jawaban Anda berhasil dikirim.');

        return redirect()->route('portal.home')->with('submitted_target', "{$survey->id}|".($validated['target'] ?? 'general'));
    }
}
