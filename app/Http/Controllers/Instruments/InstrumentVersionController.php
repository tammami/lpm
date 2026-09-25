<?php

namespace App\Http\Controllers\Instruments;

use App\Enums\InstrumentVersionStatus;
use App\Enums\QuestionType;
use App\Enums\ScoringMethod;
use App\Http\Controllers\Controller;
use App\Models\AnswerScale;
use App\Models\ClassificationScheme;
use App\Models\InstrumentQuestion;
use App\Models\InstrumentSection;
use App\Models\InstrumentVersion;
use App\Models\Response as SurveyResponse;
use App\Services\InstrumentVersioning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentVersionController extends Controller
{
    public function show(Request $request, InstrumentVersion $version, InstrumentVersioning $versioning): Response
    {
        $version->load(['instrument', 'sections.questions.options', 'classificationScheme.classifications', 'publisher:id,name', 'parentVersion:id,version']);
        $user = $request->user();

        return Inertia::render('instruments/builder', [
            'instrument' => [
                ...$version->instrument->only(['id', 'code', 'name', 'description']),
                'type' => $version->instrument->type->value,
                'type_label' => $version->instrument->type->label(),
                'respondent_type' => $version->instrument->respondent_type->value,
                'respondent_label' => $version->instrument->respondent_type->label(),
                'archived' => $version->instrument->archived_at !== null,
            ],
            'version' => [
                'id' => $version->id,
                'version' => $version->version,
                'status' => $version->status->value,
                'status_label' => $version->status->label(),
                'is_editable' => $version->isEditable(),
                'scoring_method' => $version->scoring_method->value,
                'scale_min' => $version->scale_min,
                'scale_max' => $version->scale_max,
                'classification_scheme_id' => $version->classification_scheme_id,
                'changelog' => $version->changelog,
                'review_notes' => $version->review_notes,
                'parent_version' => $version->parentVersion?->version,
                'submitted_at' => $version->submitted_at?->toIso8601String(),
                'approved_at' => $version->approved_at?->toIso8601String(),
                'published_at' => $version->published_at?->toIso8601String(),
                'publisher' => $version->publisher?->name,
                'surveys_count' => $version->surveys()->count(),
                'responses_count' => SurveyResponse::query()->where('instrument_version_id', $version->id)->count(),
            ],
            'versions' => $version->instrument->versions()->get(['id', 'version', 'status', 'published_at', 'created_at'])
                ->map(fn (InstrumentVersion $item): array => [
                    'id' => $item->id,
                    'version' => $item->version,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'published_at' => $item->published_at?->toIso8601String(),
                    'created_at' => $item->created_at?->toIso8601String(),
                ])->all(),
            'sections' => $version->sections->map(fn (InstrumentSection $section): array => [
                ...$section->only(['id', 'code', 'title', 'description', 'sort_order']),
                'questions' => $section->questions->map(fn (InstrumentQuestion $question): array => $this->questionPayload($question))->all(),
            ])->all(),
            'publishIssues' => $version->status === InstrumentVersionStatus::Published || $version->status === InstrumentVersionStatus::Archived
                ? []
                : $versioning->publishIssues($version),
            'questionTypes' => collect(QuestionType::cases())->map(fn (QuestionType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'has_options' => $type->hasOptions(),
                'scorable' => $type->isScorable(),
            ])->all(),
            'scoringMethods' => collect(ScoringMethod::cases())->map(fn (ScoringMethod $method): array => [
                'value' => $method->value,
                'label' => $method->label(),
                'formula' => $method->formula(),
            ])->all(),
            'classificationSchemes' => ClassificationScheme::query()->with('classifications')->orderBy('name')->get()
                ->map(fn (ClassificationScheme $scheme): array => [
                    'value' => $scheme->id,
                    'label' => $scheme->name,
                    'classifications' => $scheme->classifications->map->only(['label', 'min_score', 'max_score', 'color'])->all(),
                ])->all(),
            'answerScales' => AnswerScale::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'question_type', 'options'])->all(),
            'can' => [
                'manage' => $user->can('instruments.manage'),
                'approve' => $user->can('instruments.approve'),
                'publish' => $user->can('instruments.publish'),
            ],
        ]);
    }

    /**
     * Tampilan instrumen seperti yang dilihat responden (tanpa menyimpan jawaban).
     */
    public function preview(InstrumentVersion $version): Response
    {
        $version->load(['instrument', 'sections.questions' => fn ($q) => $q->where('is_active', true), 'sections.questions.options']);

        return Inertia::render('instruments/preview', [
            'instrument' => $version->instrument->only(['id', 'name', 'description']),
            'version' => ['id' => $version->id, 'version' => $version->version, 'status_label' => $version->status->label()],
            'sections' => $version->sections->map(fn (InstrumentSection $section): array => [
                ...$section->only(['id', 'code', 'title', 'description']),
                'questions' => $section->questions->map(fn (InstrumentQuestion $question): array => $this->questionPayload($question))->all(),
            ])->all(),
        ]);
    }

    public function update(Request $request, InstrumentVersion $version): RedirectResponse
    {
        $this->ensureEditable($version);

        $validated = $request->validate([
            'scoring_method' => ['required', Rule::enum(ScoringMethod::class)],
            'scale_min' => ['required', 'numeric', 'min:0', 'lt:scale_max'],
            'scale_max' => ['required', 'numeric', 'max:100'],
            'classification_scheme_id' => ['nullable', 'exists:classification_schemes,id'],
            'changelog' => ['nullable', 'string', 'max:2000'],
        ]);

        $version->update($validated);
        $this->toast('Pengaturan versi disimpan.');

        return back();
    }

    public function transition(Request $request, InstrumentVersion $version, InstrumentVersioning $versioning): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(InstrumentVersionStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = InstrumentVersionStatus::from($validated['status']);

        $permission = match ($target) {
            InstrumentVersionStatus::Approved => 'instruments.approve',
            InstrumentVersionStatus::Published, InstrumentVersionStatus::Archived => 'instruments.publish',
            InstrumentVersionStatus::Draft => $version->status === InstrumentVersionStatus::Review ? 'instruments.approve' : 'instruments.manage',
            default => 'instruments.manage',
        };

        abort_unless($request->user()->can($permission), 403, 'Anda tidak berwenang melakukan tindakan ini.');

        $versioning->transition($version, $target, $request->user(), $validated['notes'] ?? null);

        $this->toast(match ($target) {
            InstrumentVersionStatus::Review => 'Versi diajukan untuk ditinjau.',
            InstrumentVersionStatus::Approved => 'Versi disetujui dan siap diterbitkan.',
            InstrumentVersionStatus::Published => 'Versi diterbitkan. Kini dapat dipakai pada kegiatan Monev.',
            InstrumentVersionStatus::Archived => 'Versi diarsipkan.',
            InstrumentVersionStatus::Draft => 'Versi dikembalikan ke draf.',
        });

        return back();
    }

    public function duplicate(Request $request, InstrumentVersion $version, InstrumentVersioning $versioning): RedirectResponse
    {
        $validated = $request->validate([
            'major' => ['boolean'],
            'changelog' => ['nullable', 'string', 'max:2000'],
        ]);

        $newVersion = $versioning->cloneVersion($version, $request->user(), (bool) ($validated['major'] ?? false), $validated['changelog'] ?? null);
        $this->toast("Versi {$newVersion->version} dibuat sebagai draf. Versi {$version->version} tetap utuh.");

        return redirect()->route('instrument-versions.show', $newVersion);
    }

    public function destroy(InstrumentVersion $version): RedirectResponse
    {
        if ($version->status !== InstrumentVersionStatus::Draft) {
            $this->failWith('Hanya versi draf yang dapat dihapus.');
        }

        $instrument = $version->instrument;

        if ($instrument->versions()->count() === 1) {
            $this->toast('Versi satu-satunya tidak dapat dihapus. Hapus instrumennya bila tidak diperlukan.', 'error');

            return back();
        }

        $this->deleteSafely($version, "Versi {$version->version}");

        return redirect()->route('instruments.show', $instrument);
    }

    /**
     * @return array<string, mixed>
     */
    public static function questionPayload(InstrumentQuestion $question): array
    {
        return [
            ...$question->only([
                'id', 'instrument_section_id', 'code', 'label', 'description', 'category', 'indicator', 'is_required',
                'is_scored', 'weight', 'min_score', 'max_score', 'requires_evidence', 'visible_to_evaluatee', 'is_active',
                'settings', 'sort_order',
            ]),
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'options' => $question->options->map->only(['id', 'label', 'value', 'score'])->values()->all(),
        ];
    }

    private function ensureEditable(InstrumentVersion $version): void
    {
        if (! $version->isEditable()) {
            $this->failWith('Versi ini terkunci. Buat versi baru untuk melakukan perubahan.');
        }
    }
}
