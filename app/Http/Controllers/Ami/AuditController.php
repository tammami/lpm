<?php

namespace App\Http\Controllers\Ami;

use App\Enums\AuditStatus;
use App\Enums\FindingStatus;
use App\Enums\InstrumentType;
use App\Enums\InstrumentVersionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Instruments\InstrumentVersionController;
use App\Models\Audit;
use App\Models\AuditAnswer;
use App\Models\Auditor;
use App\Models\AuditProgram;
use App\Models\Finding;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentSection;
use App\Models\InstrumentVersion;
use App\Models\User;
use App\Notifications\AuditScheduledNotification;
use App\Services\AuditLogger;
use App\Services\Evidence\EvidenceService;
use App\Services\Monev\ScoreCalculator;
use App\Services\ReportBranding;
use App\Support\Auditee;
use App\Support\CodeGenerator;
use App\Support\EvidenceOptions;
use App\Support\TableQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = Audit::query()
            ->visibleTo($user, $user->can('ami.view'))
            ->with(['program:id,name,code', 'auditors.user:id,name', 'auditeePic:id,name', 'instrumentVersion.instrument:id,name'])
            ->withCount(['findings', 'findings as open_findings_count' => fn ($q) => $q->whereIn('status', FindingStatus::unresolved())])
            ->when($request->boolean('mine'), fn ($q) => $q->whereHas('auditors', fn ($a) => $a->where('auditors.user_id', $user->id)));

        $audits = TableQuery::for($query, $request)
            ->search(['code', 'auditee_name'])
            ->filter(['status' => 'status', 'audit_program_id' => 'audit_program_id'])
            ->sort(['scheduled_on', 'code', 'auditee_name'], '-scheduled_on')
            ->paginate()
            ->through(fn (Audit $audit): array => self::summary($audit));

        return Inertia::render('ami/audits/index', [
            'audits' => $audits,
            'filters' => [...$this->filters($request, ['status', 'audit_program_id']), 'mine' => $request->boolean('mine')],
            'statuses' => AuditStatus::options(),
            'programs' => AuditProgram::query()->orderByDesc('year')->get(['id', 'name'])->map(fn ($p): array => ['value' => $p->id, 'label' => $p->name])->all(),
            'isAuditor' => $user->auditor()->exists(),
        ]);
    }

    public function store(Request $request, AuditProgram $program): RedirectResponse
    {
        $validated = $this->validated($request);
        $auditee = Auditee::resolve(...Auditee::parse($validated['auditee']));

        $audit = DB::transaction(function () use ($validated, $auditee, $program): Audit {
            $audit = $program->audits()->create([
                'code' => CodeGenerator::next('AUD', 'audits', pad: 3),
                'auditee_type' => $auditee['type'],
                'auditee_id' => $auditee['id'],
                'auditee_name' => $auditee['name'],
                'study_program_id' => $auditee['study_program_id'],
                'faculty_id' => $auditee['faculty_id'],
                'instrument_version_id' => $validated['instrument_version_id'],
                'auditee_pic_user_id' => $validated['auditee_pic_user_id'] ?? null,
                'desk_review_due' => $validated['desk_review_due'] ?? null,
                'scheduled_on' => $validated['scheduled_on'],
                'location' => $validated['location'] ?? null,
                'status' => AuditStatus::Planned,
            ]);

            $this->syncAuditors($audit, (int) $validated['lead_auditor_id'], $validated['member_auditor_ids'] ?? []);

            return $audit;
        });

        if ($program->status->value === 'draft') {
            $program->update(['status' => 'planned']);
        }

        $recipients = $audit->auditors()->with('user')->get()->pluck('user')->push($audit->auditeePic)->filter()->unique('id');
        Notification::send($recipients, new AuditScheduledNotification($audit));

        $this->toast("Audit {$audit->code} dijadwalkan. Auditor & PIC auditee telah diberi tahu.");

        return back();
    }

    public function show(Request $request, Audit $audit): Response
    {
        $user = $request->user();
        $this->authorizeView($request, $audit);

        $audit->load(['program', 'auditors.user:id,name,email', 'auditeePic:id,name,email', 'answers', 'evidenceMappings.evidence.currentVersion']);
        $version = $audit->instrumentVersion()->with(['sections.questions' => fn ($q) => $q->where('is_active', true), 'sections.questions.options', 'sections.questions.standard'])->first();
        $answers = $audit->answers->keyBy('instrument_question_id');

        $findings = Finding::query()->where('audit_id', $audit->id)->with(['severity', 'pic:id,name', 'standard:id,code,name'])->orderBy('code')->get();

        return Inertia::render('ami/audits/show', [
            'audit' => [
                ...self::summary($audit),
                'program' => $audit->program->only(['id', 'code', 'name', 'year']),
                'summary_text' => $audit->summary,
                'strengths' => $audit->strengths,
                'conclusion' => $audit->conclusion,
                'auditee_key' => $audit->auditee_type->value.':'.$audit->auditee_id,
                'pic_email' => $audit->auditeePic?->email,
            ],
            'checklist' => $version ? $version->sections->map(fn (InstrumentSection $section): array => [
                ...$section->only(['id', 'code', 'title', 'description']),
                'questions' => $section->questions->map(fn (InstrumentQuestion $question): array => [
                    ...InstrumentVersionController::questionPayload($question),
                    'standard' => $question->standard?->only(['id', 'code', 'name']),
                    'answer' => ($answer = $answers->get($question->id)) ? [
                        'option_id' => $answer->instrument_question_option_id,
                        'value_text' => $answer->value_text,
                        'auditor_note' => $answer->auditor_note,
                        'score' => $answer->score,
                    ] : null,
                ])->all(),
            ])->all() : [],
            'compliance' => $this->compliance($audit, $version),
            'findings' => $findings->map(fn (Finding $finding): array => FindingController::row($finding))->all(),
            'evidence' => EvidenceService::linkedPayload($audit->evidenceMappings, $user),
            ...self::formOptions($request),
            ...FindingController::formOptions($audit),
            ...EvidenceOptions::for($request),
            'can' => [
                'work' => $this->canWork($user, $audit),
                'manage' => $user->can('ami.manage'),
                'lead' => $audit->isLeadAuditor($user) || $user->can('ami.manage'),
                'attach' => $this->canWork($user, $audit) || $audit->auditee_pic_user_id === $user->id || $user->can('ami.manage'),
            ],
        ]);
    }

    public function update(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless($request->user()->can('ami.manage'), 403);
        $validated = $this->validated($request, $audit);
        $auditee = Auditee::resolve(...Auditee::parse($validated['auditee']));

        DB::transaction(function () use ($audit, $validated, $auditee): void {
            $audit->update([
                'auditee_type' => $auditee['type'],
                'auditee_id' => $auditee['id'],
                'auditee_name' => $auditee['name'],
                'study_program_id' => $auditee['study_program_id'],
                'faculty_id' => $auditee['faculty_id'],
                'instrument_version_id' => $audit->answers()->exists() ? $audit->instrument_version_id : $validated['instrument_version_id'],
                'auditee_pic_user_id' => $validated['auditee_pic_user_id'] ?? null,
                'desk_review_due' => $validated['desk_review_due'] ?? null,
                'scheduled_on' => $validated['scheduled_on'],
                'location' => $validated['location'] ?? null,
            ]);
            $this->syncAuditors($audit, (int) $validated['lead_auditor_id'], $validated['member_auditor_ids'] ?? []);
        });

        $this->toast('Jadwal audit diperbarui.');

        return back();
    }

    /**
     * Lanjut ke tahap berikutnya: terjadwal → desk evaluation → lapangan → pelaporan → selesai.
     */
    public function advance(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless($audit->isLeadAuditor($request->user()) || $request->user()->can('ami.manage'), 403, 'Hanya ketua auditor yang dapat memindahkan tahapan audit.');
        $next = $audit->status->next();

        if (! $next) {
            $this->failWith('Audit sudah berada di tahap akhir.');
        }

        if ($next === AuditStatus::Completed) {
            if (blank($audit->conclusion)) {
                $this->failWith('Isi kesimpulan audit pada tab Laporan sebelum menyelesaikan audit.');
            }

            if ($audit->findings()->where('status', FindingStatus::Open)->exists()) {
                $this->failWith('Terbitkan seluruh temuan kepada auditee sebelum audit diselesaikan.');
            }
        }

        $audit->update([
            'status' => $next,
            'started_at' => $audit->started_at ?? ($next === AuditStatus::DeskReview ? now() : null),
            'completed_at' => $next === AuditStatus::Completed ? now() : null,
        ]);

        if ($audit->program->status->value === 'planned') {
            $audit->program->update(['status' => 'ongoing']);
        }

        AuditLogger::log('status_changed', 'ami', $audit, "Audit {$audit->code}: {$next->label()}");
        $this->toast("Audit masuk tahap: {$next->label()}.");

        return back();
    }

    public function cancel(Audit $audit): RedirectResponse
    {
        if ($audit->status === AuditStatus::Completed) {
            $this->failWith('Audit yang sudah selesai tidak dapat dibatalkan.');
        }

        $audit->update(['status' => AuditStatus::Cancelled]);
        $this->toast('Audit dibatalkan.');

        return back();
    }

    /**
     * Simpan jawaban checklist per butir (disimpan otomatis dari UI).
     */
    public function saveAnswer(Request $request, Audit $audit, ScoreCalculator $calculator): RedirectResponse
    {
        abort_unless($this->canWork($request->user(), $audit), 403, 'Hanya auditor yang ditugaskan yang dapat mengisi instrumen.');

        if (! $audit->status->isEditable()) {
            $this->failWith('Instrumen hanya dapat diisi pada tahap desk evaluation hingga pelaporan.');
        }

        $validated = $request->validate([
            'instrument_question_id' => ['required', Rule::exists('instrument_questions', 'id')->where('instrument_version_id', $audit->instrument_version_id)],
            'instrument_question_option_id' => ['nullable', 'integer'],
            'value_text' => ['nullable', 'string', 'max:3000'],
            'auditor_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $question = InstrumentQuestion::query()->with('options')->findOrFail($validated['instrument_question_id']);
        $optionId = $validated['instrument_question_option_id'] ?? null;

        if ($optionId && ! $question->options->contains('id', $optionId)) {
            $this->failWith('Pilihan jawaban tidak valid.');
        }

        AuditAnswer::query()->updateOrCreate(
            ['audit_id' => $audit->id, 'instrument_question_id' => $question->id],
            [
                'instrument_question_option_id' => $optionId,
                'value_text' => $validated['value_text'] ?? null,
                'auditor_note' => $validated['auditor_note'] ?? null,
                'score' => $optionId ? $calculator->answerScore($question, $audit->instrumentVersion, $optionId) : null,
                'updated_by' => $request->user()->id,
            ],
        );

        return back();
    }

    public function saveReport(Request $request, Audit $audit): RedirectResponse
    {
        abort_unless($this->canWork($request->user(), $audit), 403);

        $audit->update($request->validate([
            'summary' => ['nullable', 'string', 'max:5000'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'conclusion' => ['nullable', 'string', 'max:5000'],
        ]));

        $this->toast('Laporan audit disimpan.');

        return back();
    }

    public function report(Request $request, Audit $audit): HttpResponse
    {
        $this->authorizeView($request, $audit);
        $audit->load(['program', 'auditors.user', 'auditeePic', 'instrumentVersion.instrument']);
        $version = $audit->instrumentVersion()->with(['sections.questions.options', 'sections.questions.standard'])->first();
        $answers = $audit->answers()->with('option')->get()->keyBy('instrument_question_id');

        AuditLogger::log('exported', 'report', $audit, "Mengunduh laporan audit {$audit->code}");

        return Pdf::loadView('reports.audit', [
            'title' => "Laporan Audit {$audit->code}",
            'brand' => ReportBranding::data(),
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
            'generatedBy' => $request->user()->name,
            'signature' => false,
            'audit' => $audit,
            'version' => $version,
            'answers' => $answers,
            'compliance' => $this->compliance($audit, $version),
            'findings' => $audit->findings()->with(['severity', 'standard', 'pic', 'correctiveActions.pic'])->orderBy('code')->get(),
        ])->setPaper('a4')->download("laporan-audit-{$audit->code}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(Audit $audit): array
    {
        $audit->loadMissing(['auditors.user:id,name', 'auditeePic:id,name', 'instrumentVersion.instrument:id,name']);
        $lead = $audit->auditors->firstWhere('pivot.role', 'lead');

        return [
            'id' => $audit->id,
            'code' => $audit->code,
            'audit_program_id' => $audit->audit_program_id,
            'auditee_type' => $audit->auditee_type->value,
            'auditee_type_label' => $audit->auditee_type->label(),
            'auditee_name' => $audit->auditee_name,
            'auditee_key' => $audit->auditee_type->value.':'.$audit->auditee_id,
            'status' => $audit->status->value,
            'status_label' => $audit->status->label(),
            'scheduled_on' => $audit->scheduled_on?->toDateString(),
            'desk_review_due' => $audit->desk_review_due?->toDateString(),
            'location' => $audit->location,
            'instrument' => $audit->instrumentVersion ? $audit->instrumentVersion->instrument->name.' v'.$audit->instrumentVersion->version : null,
            'instrument_version_id' => $audit->instrument_version_id,
            'lead_auditor' => $lead?->user?->name,
            'lead_auditor_id' => $lead?->id,
            'member_auditor_ids' => $audit->auditors->where('pivot.role', 'member')->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
            'auditors' => $audit->auditors->map(fn (Auditor $auditor): array => ['id' => $auditor->id, 'name' => $auditor->user?->name, 'role' => $auditor->pivot->role])->all(),
            'auditee_pic' => $audit->auditeePic?->name,
            'auditee_pic_user_id' => $audit->auditee_pic_user_id,
            'findings_count' => $audit->findings_count ?? $audit->findings()->count(),
            'open_findings_count' => $audit->open_findings_count ?? $audit->findings()->whereIn('status', FindingStatus::unresolved())->count(),
            'completed_at' => $audit->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function formOptions(Request $request): array
    {
        return [
            'auditees' => Auditee::options($request->user()),
            'auditorOptions' => Auditor::query()->where('is_active', true)->with('user:id,name')->get()->sortBy('user.name')->values()
                ->map(fn (Auditor $auditor): array => ['value' => $auditor->id, 'label' => $auditor->user->name])->all(),
            'instrumentOptions' => InstrumentVersion::query()
                ->where('status', InstrumentVersionStatus::Published)
                ->whereHas('instrument', fn ($q) => $q->where('type', InstrumentType::Ami))
                ->with('instrument:id,name')->get()
                ->map(fn (InstrumentVersion $version): array => ['value' => $version->id, 'label' => "{$version->instrument->name} — v{$version->version}"])->all(),
            'picOptions' => User::query()->where('is_active', true)->whereDoesntHave('roles', fn ($q) => $q->where('name', UserRole::Mahasiswa->value))
                ->orderBy('name')->get(['id', 'name'])->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])->all(),
        ];
    }

    /**
     * Rekap kepatuhan: jumlah jawaban per opsi & persentase skor.
     *
     * @return array{answered: int, total: int, score_percent: float|null, breakdown: list<array{label: string, value: string, count: int}>, by_standard: list<array{code: string, name: string, score_percent: float|null, answered: int}>}
     */
    private function compliance(Audit $audit, ?InstrumentVersion $version): array
    {
        if (! $version) {
            return ['answered' => 0, 'total' => 0, 'score_percent' => null, 'breakdown' => [], 'by_standard' => []];
        }

        $version->loadMissing(['questions.options', 'questions.standard']);
        $questions = $version->questions->where('is_active', true);
        $answers = $audit->answers()->get()->keyBy('instrument_question_id');
        $scaleMax = max(1, $version->scale_max);

        $breakdown = [];
        foreach ($questions as $question) {
            $option = $question->options->firstWhere('id', $answers->get($question->id)?->instrument_question_option_id);
            if ($option) {
                $breakdown[$option->value] ??= ['label' => $option->label, 'value' => $option->value, 'count' => 0];
                $breakdown[$option->value]['count']++;
            }
        }

        $scored = $answers->whereNotNull('score');
        $byStandard = $questions->groupBy(fn ($q) => $q->standard?->code ?? '—')->map(function ($group, $code) use ($answers, $scaleMax): array {
            $scores = $group->map(fn ($q) => $answers->get($q->id)?->score)->filter(fn ($s) => $s !== null);

            return [
                'code' => $code,
                'name' => $group->first()->standard?->name ?? 'Tanpa standar',
                'score_percent' => $scores->isNotEmpty() ? round($scores->avg() / $scaleMax * 100, 1) : null,
                'answered' => $group->filter(fn ($q) => $answers->has($q->id))->count(),
                'total' => $group->count(),
            ];
        })->sortBy('code')->values()->all();

        return [
            'answered' => $answers->filter(fn ($a) => $a->instrument_question_option_id || $a->value_text)->count(),
            'total' => $questions->count(),
            'score_percent' => $scored->isNotEmpty() ? round($scored->avg('score') / $scaleMax * 100, 1) : null,
            'breakdown' => array_values($breakdown),
            'by_standard' => $byStandard,
        ];
    }

    private function syncAuditors(Audit $audit, int $leadId, array $memberIds): void
    {
        $sync = [$leadId => ['role' => 'lead']];

        foreach ($memberIds as $memberId) {
            if ((int) $memberId !== $leadId) {
                $sync[(int) $memberId] = ['role' => 'member'];
            }
        }

        $audit->auditors()->sync($sync);
    }

    private function canWork(User $user, Audit $audit): bool
    {
        return $user->can('ami.audit') && $audit->isAssignedTo($user);
    }

    private function authorizeView(Request $request, Audit $audit): void
    {
        $user = $request->user();
        abort_unless(Audit::query()->visibleTo($user, $user->can('ami.view'))->whereKey($audit->id)->exists(), 403, 'Audit ini berada di luar cakupan akses Anda.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Audit $audit = null): array
    {
        return $request->validate([
            'auditee' => ['required', 'string'],
            'instrument_version_id' => ['required', Rule::exists('instrument_versions', 'id')->where('status', InstrumentVersionStatus::Published->value)],
            'lead_auditor_id' => ['required', 'exists:auditors,id'],
            'member_auditor_ids' => ['array'],
            'member_auditor_ids.*' => ['exists:auditors,id'],
            'auditee_pic_user_id' => ['nullable', 'exists:users,id'],
            'desk_review_due' => ['nullable', 'date'],
            'scheduled_on' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
        ], ['lead_auditor_id.required' => 'Tentukan ketua auditor.']);
    }
}
