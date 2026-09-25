<?php

namespace App\Http\Controllers\Monev;

use App\Enums\InstrumentType;
use App\Enums\InstrumentVersionStatus;
use App\Enums\ParticipationStatus;
use App\Enums\RespondentType;
use App\Enums\SurveyMode;
use App\Enums\SurveyStatus;
use App\Events\SurveyOpened;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\InstrumentVersion;
use App\Models\Survey;
use App\Models\SurveyParticipation;
use App\Services\AuditLogger;
use App\Services\Monev\ResponseSubmitter;
use App\Services\Monev\SurveyProgress;
use App\Services\Settings;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    public function index(Request $request, SurveyProgress $progress): Response
    {
        $query = Survey::query()->with(['instrumentVersion.instrument', 'academicPeriod', 'studyPrograms:id,name,degree']);

        $surveys = TableQuery::for($query, $request)
            ->search(['title', 'code'])
            ->filter(['status' => 'status', 'academic_period_id' => 'academic_period_id', 'mode' => 'mode'])
            ->sort(['starts_at', 'title', 'ends_at'], '-starts_at')
            ->paginate(10)
            ->through(fn (Survey $survey): array => [
                ...$this->surveySummary($survey),
                'progress' => $progress->summary($survey),
            ]);

        return Inertia::render('monev/surveys/index', [
            'surveys' => $surveys,
            'filters' => $this->filters($request, ['status', 'academic_period_id', 'mode']),
            'statuses' => SurveyStatus::options(),
            'periods' => Options::periods(),
            'modes' => SurveyMode::options(),
            'canManage' => $request->user()->can('surveys.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('monev/surveys/form', [
            'survey' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $programIds = $validated['study_program_ids'] ?? [];
        unset($validated['study_program_ids']);

        $survey = DB::transaction(function () use ($validated, $programIds, $request): Survey {
            $survey = Survey::query()->create([
                ...$validated,
                'code' => $this->generateCode($validated),
                'status' => SurveyStatus::Draft,
                'created_by' => $request->user()->id,
            ]);
            $survey->studyPrograms()->sync($programIds);

            return $survey;
        });

        $this->toast('Kegiatan Monev dibuat sebagai draf. Periksa kembali lalu buka pengisian.');

        return redirect()->route('surveys.show', $survey);
    }

    public function show(Request $request, Survey $survey, SurveyProgress $progress): Response
    {
        $survey->load(['instrumentVersion.instrument', 'academicPeriod', 'studyPrograms:id,name,degree', 'creator:id,name']);
        $visible = $request->user()->accessibleStudyProgramIds();

        return Inertia::render('monev/surveys/show', [
            'survey' => [
                ...$this->surveySummary($survey),
                'description' => $survey->description,
                'creator' => $survey->creator?->name,
                'opened_at' => $survey->opened_at?->toIso8601String(),
                'closed_at' => $survey->closed_at?->toIso8601String(),
                'reopened_count' => $survey->participations()
                    ->where(fn (Builder $q) => $q->where('submission_count', '>', 1)->orWhere('status', ParticipationStatus::Reopened))
                    ->count(),
            ],
            'progress' => $progress->summary($survey),
            'byStudyProgram' => $progress->byStudyProgram($survey, $visible),
            'byAssignment' => $progress->byAssignment($survey, $visible)->all(),
            'can' => [
                'manage' => $request->user()->can('surveys.manage'),
                'reopen' => $request->user()->can('surveys.reopen'),
                'analytics' => $request->user()->can('analytics.view'),
            ],
        ]);
    }

    public function edit(Request $request, Survey $survey): Response
    {
        $survey->load('studyPrograms:id');

        return Inertia::render('monev/surveys/form', [
            'survey' => [
                ...$survey->only(['id', 'title', 'description', 'instrument_version_id', 'academic_period_id', 'is_anonymous', 'min_responses']),
                'mode' => $survey->mode->value,
                'respondent_type' => $survey->respondent_type->value,
                'status' => $survey->status->value,
                'starts_at' => $survey->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $survey->ends_at->format('Y-m-d\TH:i'),
                'study_program_ids' => $survey->studyPrograms->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ],
            ...$this->formOptions($request),
        ]);
    }

    public function update(Request $request, Survey $survey): RedirectResponse
    {
        if ($survey->status === SurveyStatus::Draft) {
            $validated = $this->validated($request);
            $programIds = $validated['study_program_ids'] ?? [];
            unset($validated['study_program_ids']);

            DB::transaction(function () use ($survey, $validated, $programIds): void {
                $survey->update($validated);
                $survey->studyPrograms()->sync($programIds);
            });
        } else {
            // Setelah dibuka, instrumen & sasaran dikunci agar data tetap konsisten.
            $survey->update($request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'ends_at' => ['required', 'date', 'after:'.$survey->starts_at->toDateTimeString()],
                'min_responses' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]));
        }

        $this->toast('Kegiatan Monev diperbarui.');

        return redirect()->route('surveys.show', $survey);
    }

    public function transition(Request $request, Survey $survey): RedirectResponse
    {
        $target = SurveyStatus::from($request->validate(['status' => ['required', Rule::enum(SurveyStatus::class)]])['status']);

        if (! $survey->status->canTransitionTo($target)) {
            $this->failWith("Status {$survey->status->label()} tidak dapat diubah menjadi {$target->label()}.");
        }

        if ($target === SurveyStatus::Active) {
            if (! $survey->instrumentVersion->isUsable()) {
                $this->failWith('Versi instrumen belum terbit sehingga Monev belum dapat dibuka.');
            }

            if ($survey->ends_at->isPast()) {
                $this->failWith('Jadwal berakhir sudah lewat. Perpanjang tanggal berakhir terlebih dahulu.');
            }
        }

        $previous = $survey->status;
        $survey->update([
            'status' => $target,
            ...match ($target) {
                SurveyStatus::Active => ['opened_at' => $survey->opened_at ?? now()],
                SurveyStatus::Closed => ['closed_at' => now()],
                default => [],
            },
        ]);

        AuditLogger::log('status_changed', 'monev', $survey, "Monev {$survey->title}: {$previous->label()} → {$target->label()}");

        if ($target === SurveyStatus::Active && $previous === SurveyStatus::Draft) {
            SurveyOpened::dispatch($survey);
        }

        $this->toast(match ($target) {
            SurveyStatus::Active => 'Monev dibuka. Responden kini dapat mengisi melalui portal.',
            SurveyStatus::Closed => 'Monev ditutup. Hasil siap dianalisis.',
            SurveyStatus::Archived => 'Monev diarsipkan.',
            default => 'Status diperbarui.',
        });

        return back();
    }

    public function destroy(Survey $survey): RedirectResponse
    {
        if ($survey->status !== SurveyStatus::Draft || $survey->participations()->exists()) {
            $this->failWith('Hanya Monev berstatus draf tanpa pengisian yang dapat dihapus.');
        }

        $survey->delete();
        $this->toast('Kegiatan Monev dihapus.');

        return redirect()->route('surveys.index');
    }

    /**
     * Cari status pengisian responden (tanpa isi jawaban) untuk keperluan buka-kembali.
     */
    public function participations(Request $request, Survey $survey): Response
    {
        $term = trim((string) $request->string('search'));
        $visible = $request->user()->accessibleStudyProgramIds();

        $participations = SurveyParticipation::query()
            ->where('survey_id', $survey->id)
            ->with(['user:id,name,username', 'teachingAssignment.lecturer', 'teachingAssignment.courseClass.course', 'reopener:id,name'])
            ->when($term !== '', fn (Builder $q) => $q->whereHas('user', fn (Builder $user) => $user
                ->where('name', 'like', "%{$term}%")->orWhere('username', 'like', "%{$term}%")))
            ->when($visible !== null, fn (Builder $q) => $q->whereHas('user', fn (Builder $user) => $user
                ->whereHas('student', fn (Builder $student) => $student->whereIn('study_program_id', $visible))
                ->orWhereHas('lecturer', fn (Builder $lecturer) => $lecturer->whereIn('study_program_id', $visible))))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SurveyParticipation $participation): array => [
                'id' => $participation->id,
                'name' => $participation->user->name,
                'username' => $participation->user->username,
                'target' => $participation->teachingAssignment
                    ? "{$participation->teachingAssignment->courseClass->course->name} ({$participation->teachingAssignment->courseClass->code}) — {$participation->teachingAssignment->lecturer->full_name}"
                    : 'Survei umum',
                'status' => $participation->status->value,
                'status_label' => $participation->status->label(),
                'submitted_at' => $participation->submitted_at?->toIso8601String(),
                'reopened_at' => $participation->reopened_at?->toIso8601String(),
                'reopen_reason' => $participation->reopen_reason,
                'reopener' => $participation->reopener?->name,
                'submission_count' => $participation->submission_count,
            ]);

        return Inertia::render('monev/surveys/participations', [
            'survey' => $this->surveySummary($survey->load(['instrumentVersion.instrument', 'academicPeriod', 'studyPrograms'])),
            'participations' => $participations,
            'filters' => $this->filters($request, []),
            'canReopen' => $request->user()->can('surveys.reopen'),
        ]);
    }

    public function reopen(Request $request, Survey $survey, SurveyParticipation $participation, ResponseSubmitter $submitter): RedirectResponse
    {
        abort_unless($participation->survey_id === $survey->id, 404);

        if ($participation->status !== ParticipationStatus::Submitted) {
            $this->failWith('Pengisian ini sudah dalam status dibuka kembali.');
        }

        $reason = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:255']])['reason'];
        $submitter->reopen($participation, $request->user(), $reason);

        AuditLogger::log('reopened', 'monev', $participation, "Membuka kembali pengisian {$participation->user->username}", null, ['reason' => $reason]);
        $this->toast('Pengisian dibuka kembali. Respons sebelumnya dibatalkan dan responden dapat mengisi ulang.');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function surveySummary(Survey $survey): array
    {
        $version = $survey->instrumentVersion;

        return [
            'id' => $survey->id,
            'code' => $survey->code,
            'title' => $survey->title,
            'mode' => $survey->mode->value,
            'mode_label' => $survey->mode->label(),
            'respondent_type' => $survey->respondent_type->value,
            'respondent_label' => $survey->respondent_type->label(),
            'status' => $survey->status->value,
            'status_label' => $survey->status->label(),
            'is_open' => $survey->isOpen(),
            'is_anonymous' => $survey->is_anonymous,
            'min_responses' => $survey->minimumResponses(),
            'starts_at' => $survey->starts_at->toIso8601String(),
            'ends_at' => $survey->ends_at->toIso8601String(),
            'period' => $survey->academicPeriod?->name,
            'instrument' => $version->instrument->name,
            'instrument_version' => $version->version,
            'instrument_version_id' => $version->id,
            'study_programs' => $survey->studyPrograms->map(fn ($program) => $program->full_name)->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'instrumentVersions' => InstrumentVersion::query()
                ->where('status', InstrumentVersionStatus::Published)
                ->whereHas('instrument', fn (Builder $q) => $q->whereNull('archived_at')->where('type', '!=', InstrumentType::Ami))
                ->with('instrument')
                ->orderByDesc('published_at')
                ->get()
                ->map(fn (InstrumentVersion $version): array => [
                    'value' => $version->id,
                    'label' => "{$version->instrument->name} — v{$version->version}",
                    'instrument_type' => $version->instrument->type->value,
                    'respondent_type' => $version->instrument->respondent_type->value,
                ])->all(),
            'periods' => Options::periods(),
            'activePeriodId' => AcademicPeriod::active()?->id,
            'studyPrograms' => Options::studyPrograms($request->user()),
            'modes' => SurveyMode::options(),
            'respondentTypes' => collect([RespondentType::Mahasiswa, RespondentType::Dosen])
                ->map(fn (RespondentType $type): array => ['value' => $type->value, 'label' => $type->label()])->all(),
            'defaultMinResponses' => (int) Settings::get('monev.min_responses', 5),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instrument_version_id' => [
                'required',
                Rule::exists('instrument_versions', 'id')->where('status', InstrumentVersionStatus::Published->value),
            ],
            'mode' => ['required', Rule::enum(SurveyMode::class)],
            'respondent_type' => ['required', Rule::in([RespondentType::Mahasiswa->value, RespondentType::Dosen->value])],
            'academic_period_id' => ['nullable', 'required_if:mode,teaching_evaluation', 'exists:academic_periods,id'],
            'is_anonymous' => ['boolean'],
            'min_responses' => ['nullable', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'study_program_ids' => ['array'],
            'study_program_ids.*' => ['integer', 'exists:study_programs,id'],
        ], [
            'instrument_version_id.exists' => 'Pilih versi instrumen yang sudah terbit.',
            'academic_period_id.required_if' => 'Evaluasi pembelajaran membutuhkan periode akademik.',
        ]);

        if ($validated['mode'] === SurveyMode::TeachingEvaluation->value && $validated['respondent_type'] !== RespondentType::Mahasiswa->value) {
            throw ValidationException::withMessages(['respondent_type' => 'Evaluasi pembelajaran diisi oleh mahasiswa.']);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function generateCode(array $validated): string
    {
        $period = isset($validated['academic_period_id']) ? AcademicPeriod::query()->find($validated['academic_period_id'])?->code : now()->format('Ym');
        $prefix = $validated['mode'] === SurveyMode::TeachingEvaluation->value ? 'EVP' : 'SRV';

        do {
            $code = "{$prefix}-{$period}-".Str::upper(Str::random(4));
        } while (Survey::query()->where('code', $code)->exists());

        return $code;
    }
}
