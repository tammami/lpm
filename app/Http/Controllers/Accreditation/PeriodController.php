<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\AccreditationPeriodStatus;
use App\Enums\AccreditationVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\AccreditationCriterion;
use App\Models\AccreditationIndicator;
use App\Models\AccreditationInstrumentVersion;
use App\Models\AccreditationPeriod;
use App\Models\EvidenceMapping;
use App\Models\User;
use App\Services\Accreditation\PeriodWorkflow;
use App\Services\Accreditation\ReadinessCalculator;
use App\Services\AuditLogger;
use App\Services\Evidence\EvidenceService;
use App\Services\ReportBranding;
use App\Support\CodeGenerator;
use App\Support\EvidenceOptions;
use App\Support\Options;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $periods = AccreditationPeriod::query()
            ->when(
                ! $user->can('accreditation.view'),
                fn ($q) => $q->where('pic_user_id', $user->id),
                fn ($q) => $q->when($user->accessibleStudyProgramIds() !== null, fn ($scoped) => $scoped->where(fn ($inner) => $inner->visibleTo($user)->orWhere('pic_user_id', $user->id))),
            )
            ->with(['studyProgram:id,code,name,degree,accreditation_body_id', 'version.instrument:id,code', 'pic:id,name'])
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->integer('study_program_id'), fn ($q, $id) => $q->where('study_program_id', $id))
            ->latest('id')
            ->get();

        return Inertia::render('accreditation/periods/index', [
            'periods' => $periods->map(fn (AccreditationPeriod $period): array => [
                ...self::row($period),
                'summary' => ReadinessCalculator::for($period)->summary(),
            ])->all(),
            'filters' => $this->filters($request, ['status', 'study_program_id']),
            'statuses' => AccreditationPeriodStatus::options(),
            ...$this->formOptions($request),
            'can' => ['manage' => $user->can('accreditation.manage')],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->authorizeStudyProgram($request, (int) $validated['study_program_id']);

        if (AccreditationPeriod::query()->where('study_program_id', $validated['study_program_id'])->whereIn('status', ['preparing', 'submitted', 'visitation'])->exists()) {
            $this->failWith('Prodi ini masih memiliki periode akreditasi yang berjalan.');
        }

        $period = AccreditationPeriod::query()->create([
            ...$validated,
            'code' => CodeGenerator::next('AKR', 'accreditation_periods'),
            'status' => AccreditationPeriodStatus::Preparing,
            'created_by' => $request->user()->id,
        ]);

        $this->toast('Periode akreditasi dibuat. Mulai petakan bukti untuk setiap indikator.');

        return redirect()->route('accreditation.periods.show', $period);
    }

    public function show(Request $request, AccreditationPeriod $period): Response
    {
        $user = $request->user();
        $this->authorizeView($request, $period);
        $period->load(['studyProgram.accreditationBody:id,code', 'version.instrument.body:id,code', 'pic:id,name']);

        $calculator = ReadinessCalculator::for($period);
        $states = $calculator->states();
        $assessments = $period->assessments()->get()->keyBy('indicator_id');
        $mappings = EvidenceMapping::query()
            ->where('mappable_type', (new AccreditationIndicator)->getMorphClass())
            ->where('context_id', $period->id)
            ->with('evidence.currentVersion')
            ->get()
            ->groupBy('mappable_id');
        $locked = ! $period->status->isOpen();
        $criteria = AccreditationCriterion::query()->where('instrument_version_id', $period->instrument_version_id)->with('indicators')->orderBy('sort_order')->get();

        return Inertia::render('accreditation/periods/show', [
            'period' => [
                ...self::row($period),
                ...$period->only(['notes', 'sk_number', 'certificate_number', 'result_grade', 'result_score', 'target_score', 'pic_user_id', 'instrument_version_id', 'study_program_id']),
                'starts_on' => $period->starts_on?->toDateString(),
                'submitted_on' => $period->submitted_on?->toDateString(),
                'visit_on' => $period->visit_on?->toDateString(),
                'decided_on' => $period->decided_on?->toDateString(),
                'valid_from' => $period->valid_from?->toDateString(),
                'valid_until' => $period->valid_until?->toDateString(),
                'current_status' => $period->studyProgram->accreditation_status,
                'current_valid_until' => $period->studyProgram->accreditation_valid_until?->toDateString(),
                'scale_max' => $period->version->scale_max,
                'thresholds' => $period->version->thresholds(),
            ],
            'summary' => $calculator->summary(),
            'byCriterion' => $calculator->byCriterion(),
            'gaps' => $calculator->gaps(),
            'criteria' => $criteria->map(fn (AccreditationCriterion $criterion): array => [
                ...$criterion->only(['id', 'parent_id', 'code', 'title', 'description', 'weight']),
                'indicators' => $criterion->indicators->map(fn (AccreditationIndicator $indicator): array => [
                    ...$indicator->only(['id', 'code', 'statement', 'target', 'evidence_hint', 'weight', 'is_essential']),
                    ...$states[$indicator->id],
                    'notes' => $assessments[$indicator->id]->notes ?? null,
                    'evidence' => EvidenceService::linkedPayload($mappings[$indicator->id] ?? [], $user, $locked),
                ])->all(),
            ])->all(),
            'evidenceOptions' => EvidenceOptions::for($request),
            ...$this->formOptions($request),
            'can' => [
                'manage' => $user->can('accreditation.manage'),
                'contribute' => $period->canContribute($user),
            ],
        ]);
    }

    public function update(Request $request, AccreditationPeriod $period): RedirectResponse
    {
        $validated = $this->validated($request, $period);
        $this->authorizeStudyProgram($request, (int) $validated['study_program_id']);
        $period->update($validated);
        $this->toast('Periode akreditasi diperbarui.');

        return back();
    }

    public function advance(Request $request, AccreditationPeriod $period, PeriodWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['date' => ['nullable', 'date']]);
        $workflow->advance($period, $validated['date'] ?? null);
        $this->toast("Tahap diperbarui: {$period->status->label()}.");

        return back();
    }

    public function decide(Request $request, AccreditationPeriod $period, PeriodWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate([
            'result_grade' => ['required', 'string', 'max:50'],
            'result_score' => ['nullable', 'numeric', 'between:0,400'],
            'sk_number' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'decided_on' => ['required', 'date'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['required', 'date', 'after:decided_on'],
        ]);

        $workflow->decide($period, $validated);
        $this->toast('Keputusan akreditasi dicatat dan status prodi diperbarui.');

        return back();
    }

    public function cancel(Request $request, AccreditationPeriod $period, PeriodWorkflow $workflow): RedirectResponse
    {
        $workflow->cancel($period, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);
        $this->toast('Periode akreditasi dibatalkan.');

        return back();
    }

    /**
     * Penilaian diri (self-assessment) per indikator oleh tim penyusun.
     */
    public function assess(Request $request, AccreditationPeriod $period): RedirectResponse
    {
        abort_unless($period->canContribute($request->user()), 403, 'Anda bukan tim penyusun periode ini.');

        $validated = $request->validate([
            'indicator_id' => ['required', Rule::exists('accreditation_indicators', 'id')->where('instrument_version_id', $period->instrument_version_id)],
            'self_score' => ['nullable', 'numeric', 'min:0', 'max:'.$period->version->scale_max],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $period->assessments()->updateOrCreate(
            ['indicator_id' => $validated['indicator_id']],
            ['self_score' => $validated['self_score'] ?? null, 'notes' => $validated['notes'] ?? null, 'updated_by' => $request->user()->id],
        );

        return back();
    }

    public function report(Request $request, AccreditationPeriod $period): HttpResponse
    {
        $this->authorizeView($request, $period);
        $period->load(['studyProgram', 'version.instrument.body', 'pic:id,name']);
        $calculator = ReadinessCalculator::for($period);

        AuditLogger::log('exported', 'report', $period, "Mengunduh laporan kesiapan akreditasi {$period->code}");

        return Pdf::loadView('reports.accreditation', [
            'title' => "Laporan Kesiapan Akreditasi {$period->studyProgram->full_name}",
            'brand' => ReportBranding::data(),
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
            'generatedBy' => $request->user()->name,
            'period' => $period,
            'summary' => $calculator->summary(),
            'criteria' => $calculator->byCriterion(),
            'gaps' => $calculator->gaps(),
        ])->setPaper('a4')->download(Str::slug("kesiapan-akreditasi-{$period->studyProgram->code}-{$period->code}").'.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(AccreditationPeriod $period): array
    {
        return [
            'id' => $period->id,
            'code' => $period->code,
            'name' => $period->name,
            'status' => $period->status->value,
            'status_label' => $period->status->label(),
            'study_program' => $period->studyProgram?->full_name,
            'study_program_code' => $period->studyProgram?->code,
            'instrument' => $period->version?->instrument?->code.' v'.$period->version?->version,
            'pic' => $period->pic?->name,
            'submission_deadline' => $period->submission_deadline?->toDateString(),
            'days_to_deadline' => $period->status->isOpen() ? $period->daysToDeadline() : null,
        ];
    }

    private function authorizeView(Request $request, AccreditationPeriod $period): void
    {
        $user = $request->user();
        abort_unless(($user->can('accreditation.view') && $period->isVisibleTo($user)) || $period->pic_user_id === $user->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'studyPrograms' => Options::studyPrograms($request->user()),
            'versions' => AccreditationInstrumentVersion::query()
                ->where('status', AccreditationVersionStatus::Published)
                ->with('instrument.body:id,code')
                ->get()
                ->map(fn (AccreditationInstrumentVersion $version): array => [
                    'value' => $version->id,
                    'label' => "{$version->instrument->code} v{$version->version} — {$version->instrument->name}",
                    'body_id' => $version->instrument->accreditation_body_id,
                ])->all(),
            'picOptions' => User::query()->where('is_active', true)->whereDoesntHave('roles', fn ($q) => $q->where('name', 'mahasiswa'))->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AccreditationPeriod $period = null): array
    {
        return $request->validate([
            'study_program_id' => ['required', 'exists:study_programs,id'],
            'instrument_version_id' => [
                'required',
                Rule::exists('accreditation_instrument_versions', 'id')->where('status', AccreditationVersionStatus::Published->value),
                function (string $attribute, mixed $value, \Closure $fail) use ($period): void {
                    if ($period && (int) $value !== $period->instrument_version_id && ($period->assessments()->exists() || EvidenceMapping::query()->where('context_id', $period->id)->where('mappable_type', 'accreditation_indicator')->exists())) {
                        $fail('Instrumen tidak dapat diganti karena penilaian/bukti sudah diisi.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'pic_user_id' => ['nullable', 'exists:users,id'],
            'starts_on' => ['nullable', 'date'],
            'submission_deadline' => ['nullable', 'date'],
            'visit_on' => ['nullable', 'date'],
            'target_score' => ['nullable', 'numeric', 'between:0,400'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
