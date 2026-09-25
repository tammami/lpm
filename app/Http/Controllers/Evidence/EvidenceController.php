<?php

namespace App\Http\Controllers\Evidence;

use App\Enums\EvidenceStatus;
use App\Http\Controllers\Controller;
use App\Models\AccreditationIndicator;
use App\Models\AccreditationPeriod;
use App\Models\Evidence;
use App\Models\EvidenceCategory;
use App\Models\EvidenceMapping;
use App\Models\EvidenceVersion;
use App\Models\Finding;
use App\Models\Recommendation;
use App\Notifications\EvidenceRejectedNotification;
use App\Services\AuditLogger;
use App\Services\Evidence\EvidenceService;
use App\Services\Settings;
use App\Support\Auditee;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $base = Evidence::query()->visibleTo($user, $user->can('evidence.view'));

        $query = (clone $base)
            ->with(['category:id,name', 'currentVersion', 'owner:id,name'])
            ->withCount('mappings')
            ->when($request->input('status') === 'expired', fn ($q) => $q->where(fn ($inner) => $inner->where('status', EvidenceStatus::Expired)->orWhereDate('valid_until', '<', now())));

        $evidence = TableQuery::for($query, $request)
            ->search(['title', 'code', 'description', 'unit_name'])
            ->filter(['evidence_category_id' => 'evidence_category_id', 'year' => 'year', 'study_program_id' => 'study_program_id'])
            ->when($request->filled('status') && ! in_array($request->input('status'), ['all', 'expired'], true), fn (TableQuery $table) => $table->filter(['status' => 'status']))
            ->sort(['updated_at', 'title', 'code', 'year'], '-updated_at')
            ->paginate(15)
            ->through(fn (Evidence $item): array => $this->row($item));

        $stats = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('evidence/index', [
            'evidence' => $evidence,
            'filters' => $this->filters($request, ['evidence_category_id', 'year', 'status', 'study_program_id']),
            'stats' => [
                'total' => (int) $stats->sum(),
                'verified' => (int) ($stats[EvidenceStatus::Verified->value] ?? 0),
                'pending' => (int) ($stats[EvidenceStatus::Pending->value] ?? 0),
                'rejected' => (int) ($stats[EvidenceStatus::Rejected->value] ?? 0),
            ],
            ...$this->formOptions($request),
            'canManage' => $user->can('evidence.manage'),
        ]);
    }

    public function store(Request $request, EvidenceService $service): RedirectResponse
    {
        $validated = $this->validated($request);

        if (! empty($validated['map_type']) && ! empty($validated['map_id'])) {
            $this->authorizeAccreditationContext($request, $validated['map_type'], (int) $validated['map_id'], $validated['map_context'] ?? null);
        }

        $evidence = DB::transaction(function () use ($service, $validated, $request): Evidence {
            $evidence = $service->create($validated, $request->user(), $request->file('file'), $validated['url'] ?? null);

            if (! empty($validated['map_type']) && ! empty($validated['map_id'])) {
                $service->map($evidence, $validated['map_type'], (int) $validated['map_id'], $request->user(), $validated['map_context'] ?? null);
            }

            return $evidence;
        });

        if (! empty($validated['map_type']) && ! empty($validated['map_id'])) {
            $this->toast('Dokumen diunggah dan ditautkan.');

            return back();
        }

        $this->toast("Dokumen {$evidence->code} tersimpan dan menunggu verifikasi.");

        return redirect()->route('evidence.show', $evidence);
    }

    public function show(Request $request, Evidence $evidence): Response
    {
        $this->authorizeView($request, $evidence);
        $evidence->load(['category', 'owner:id,name', 'versions.uploader:id,name', 'verifications.verifier:id,name', 'mappings.mappable', 'mappings.creator:id,name']);

        return Inertia::render('evidence/show', [
            'evidence' => [
                ...$this->row($evidence),
                'description' => $evidence->description,
                'valid_until' => $evidence->valid_until?->toDateString(),
                'confidentiality' => $evidence->confidentiality,
                'academic_period_id' => $evidence->academic_period_id,
                'unit' => $evidence->unit_type ? "{$evidence->unit_type}:{$evidence->unit_id}" : null,
                'versions' => $evidence->versions->map(fn (EvidenceVersion $version): array => [
                    ...$version->only(['id', 'version', 'original_name', 'mime_type', 'size', 'url', 'notes', 'checksum']),
                    'uploader' => $version->uploader?->name,
                    'created_at' => $version->created_at?->toIso8601String(),
                    'is_current' => $version->id === $evidence->current_version_id,
                ])->all(),
                'verifications' => $evidence->verifications->map(fn ($verification): array => [
                    'decision' => $verification->decision,
                    'notes' => $verification->notes,
                    'verifier' => $verification->verifier?->name,
                    'verified_at' => $verification->verified_at->toIso8601String(),
                ])->all(),
                'mappings' => $evidence->mappings->map(fn (EvidenceMapping $mapping): array => [
                    'id' => $mapping->id,
                    ...self::describeMappable($mapping),
                    'note' => $mapping->note,
                    'creator' => $mapping->creator?->name,
                    'created_at' => $mapping->created_at?->toIso8601String(),
                ])->all(),
            ],
            ...$this->formOptions($request),
            'can' => [
                'manage' => $request->user()->can('evidence.manage') && ($evidence->owner_user_id === $request->user()->id || $request->user()->isInstitutionWide() || $request->user()->can('evidence.verify')),
                'verify' => $request->user()->can('evidence.verify'),
            ],
        ]);
    }

    public function update(Request $request, Evidence $evidence): RedirectResponse
    {
        $this->authorizeManage($request, $evidence);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:40', Rule::unique('evidence')->ignore($evidence)],
            'description' => ['nullable', 'string', 'max:2000'],
            'evidence_category_id' => ['nullable', 'exists:evidence_categories,id'],
            'unit' => ['nullable', 'string'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'academic_period_id' => ['nullable', 'exists:academic_periods,id'],
            'valid_until' => ['nullable', 'date'],
            'confidentiality' => ['required', Rule::in(['public', 'internal', 'restricted'])],
        ]);

        $unit = ! empty($validated['unit']) ? Auditee::resolve(...Auditee::parse($validated['unit'])) : null;
        unset($validated['unit']);

        $evidence->update([
            ...$validated,
            'unit_type' => $unit['type'] ?? null,
            'unit_id' => $unit['id'] ?? null,
            'unit_name' => $unit['name'] ?? null,
            'study_program_id' => $unit['study_program_id'] ?? null,
            'faculty_id' => $unit['faculty_id'] ?? null,
        ]);

        $this->toast('Metadata dokumen diperbarui.');

        return back();
    }

    public function addVersion(Request $request, Evidence $evidence, EvidenceService $service): RedirectResponse
    {
        $this->authorizeManage($request, $evidence);

        $validated = $request->validate([
            'file' => EvidenceService::uploadRules(required: false),
            'url' => ['nullable', 'required_without:file', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['url.required_without' => 'Unggah berkas atau isi tautan dokumen.']);

        $version = $service->addVersion($evidence, $request->user(), $request->file('file'), $validated['url'] ?? null, $validated['notes'] ?? null);
        $this->toast("Versi {$version->version} tersimpan. Versi lama tetap tersedia.");

        return back();
    }

    public function verify(Request $request, Evidence $evidence, EvidenceService $service): RedirectResponse
    {
        $this->authorizeView($request, $evidence);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'notes' => ['nullable', 'required_if:decision,rejected', 'string', 'max:1000'],
        ], ['notes.required_if' => 'Sertakan alasan penolakan agar pemilik dapat memperbaiki.']);

        $service->verify($evidence, $request->user(), $validated['decision'] === 'accepted', $validated['notes'] ?? null);

        if ($validated['decision'] === 'rejected' && $evidence->owner && $evidence->owner_user_id !== $request->user()->id) {
            $evidence->owner->notify(new EvidenceRejectedNotification($evidence, $validated['notes']));
        }

        $this->toast($validated['decision'] === 'accepted' ? 'Dokumen terverifikasi.' : 'Dokumen ditolak. Pemilik diberi tahu.');

        return back();
    }

    /**
     * Unduhan terlindungi: autentikasi → otorisasi → stream dari disk privat.
     */
    public function download(Request $request, Evidence $evidence, ?EvidenceVersion $version = null): StreamedResponse|RedirectResponse
    {
        $this->authorizeView($request, $evidence);
        $version ??= $evidence->currentVersion;

        abort_unless($version && $version->evidence_id === $evidence->id, 404);

        if ($version->url) {
            return redirect()->away($version->url);
        }

        abort_unless($version->file_path && Storage::disk('local')->exists($version->file_path), 404);

        AuditLogger::log('downloaded', 'evidence', $evidence, "Mengunduh {$evidence->code} v{$version->version}");

        $disposition = $request->boolean('inline') && in_array($version->mime_type, ['application/pdf', 'image/png', 'image/jpeg'], true) ? 'inline' : 'attachment';

        return Storage::disk('local')->response($version->file_path, $version->original_name, [
            'Content-Type' => $version->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }

    public function destroy(Request $request, Evidence $evidence): RedirectResponse
    {
        $this->authorizeManage($request, $evidence);

        if ($evidence->mappings()->exists() || $evidence->status === EvidenceStatus::Verified) {
            $this->failWith('Dokumen yang sudah terverifikasi atau dipakai sebagai bukti tidak dapat dihapus.');
        }

        $paths = $evidence->versions()->whereNotNull('file_path')->pluck('file_path')->all();
        $evidence->delete();
        Storage::disk('local')->delete($paths);
        $this->toast('Dokumen dihapus.');

        return redirect()->route('evidence.index');
    }

    public function map(Request $request, EvidenceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'evidence_id' => ['required', 'exists:evidence,id'],
            'mappable_type' => ['required', Rule::in(array_keys(EvidenceService::mappableTypes()))],
            'mappable_id' => ['required', 'integer'],
            'context_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $evidence = Evidence::query()->findOrFail($validated['evidence_id']);
        $this->authorizeView($request, $evidence);
        $this->authorizeAccreditationContext($request, $validated['mappable_type'], (int) $validated['mappable_id'], $validated['context_id'] ?? null);
        $service->map($evidence, $validated['mappable_type'], (int) $validated['mappable_id'], $request->user(), $validated['context_id'] ?? null, $validated['note'] ?? null);
        $this->toast('Dokumen ditautkan sebagai bukti.');

        return back();
    }

    public function unmap(Request $request, EvidenceMapping $mapping): RedirectResponse
    {
        abort_unless($mapping->created_by === $request->user()->id || $request->user()->can('evidence.verify'), 403);
        $this->authorizeAccreditationContext($request, $mapping->mappable_type, $mapping->mappable_id, $mapping->context_id);
        EvidenceService::ensureUnlocked($mapping->load('mappable')->mappable, $mapping->context_id);
        $mapping->delete();
        $this->toast('Tautan bukti dilepas. Dokumen tetap tersimpan di repositori.');

        return back();
    }

    /**
     * Pencarian dokumen untuk dialog pemilihan bukti.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();
        $term = trim((string) $request->string('q'));

        $items = Evidence::query()
            ->visibleTo($user, $user->can('evidence.view'))
            ->with(['currentVersion', 'category:id,name', 'owner:id,name'])
            ->when($term !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('title', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (Evidence $item): array => $this->row($item));

        return response()->json($items);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Evidence $evidence): array
    {
        $version = $evidence->currentVersion;
        $status = $evidence->isExpired() ? EvidenceStatus::Expired : $evidence->status;

        return [
            'id' => $evidence->id,
            'code' => $evidence->code,
            'title' => $evidence->title,
            'category' => $evidence->category?->name,
            'evidence_category_id' => $evidence->evidence_category_id,
            'document_type' => $evidence->document_type,
            'unit_name' => $evidence->unit_name,
            'year' => $evidence->year,
            'status' => $status->value,
            'status_label' => $status->label(),
            'owner' => $evidence->owner?->name,
            'mappings_count' => $evidence->mappings_count ?? null,
            'version' => $version?->version,
            'file_name' => $version?->original_name,
            'mime_type' => $version?->mime_type,
            'size' => $version?->size,
            'url' => $version?->url,
            'download_url' => route('evidence.download', $evidence),
            'show_url' => route('evidence.show', $evidence),
            'updated_at' => $evidence->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Label ringkas objek yang ditautkan dokumen.
     *
     * @return array{type: string, type_label: string, label: string, url: string|null}
     */
    public static function describeMappable(EvidenceMapping $mapping): array
    {
        $item = $mapping->mappable;
        $labels = [
            'finding' => 'Temuan AMI', 'corrective_action' => 'Tindakan koreksi', 'recommendation' => 'Rekomendasi',
            'action_plan' => 'Rencana aksi', 'audit' => 'Audit', 'accreditation_indicator' => 'Indikator akreditasi',
        ];

        [$label, $url] = match ($mapping->mappable_type) {
            'finding' => [$item ? "{$item->code} — {$item->title}" : 'Temuan dihapus', $item ? route('ami.findings.show', $item) : null],
            'corrective_action' => [$item ? 'Tindakan koreksi temuan #'.$item->finding_id : 'Dihapus', $item ? route('ami.findings.show', $item->finding_id) : null],
            'recommendation' => [$item ? "{$item->code} — {$item->title}" : 'Dihapus', $item ? route('improvement.recommendations.show', $item) : null],
            'action_plan' => [$item?->title ?? 'Dihapus', $item ? route('improvement.recommendations.show', $item->recommendation_id) : null],
            'audit' => [$item ? "{$item->code} — {$item->auditee_name}" : 'Dihapus', $item ? route('ami.audits.show', $item) : null],
            'accreditation_indicator' => [
                $item ? "{$item->code} — ".Str::limit($item->statement, 80) : 'Dihapus',
                $mapping->context_id ? route('accreditation.periods.show', $mapping->context_id) : null,
            ],
            default => ['—', null],
        };

        return ['type' => $mapping->mappable_type, 'type_label' => $labels[$mapping->mappable_type] ?? $mapping->mappable_type, 'label' => $label, 'url' => $url];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'categories' => EvidenceCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (EvidenceCategory $category): array => ['value' => $category->id, 'label' => $category->name])->all(),
            'units' => Auditee::options($request->user()),
            'periods' => Options::periods(),
            'studyPrograms' => Options::studyPrograms($request->user(), activeOnly: false),
            'years' => DB::table('evidence')->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year')
                ->map(fn ($year): array => ['value' => $year, 'label' => (string) $year])->all(),
            'maxUploadMb' => (int) Settings::get('evidence.max_upload_mb', 20),
            'allowedExtensions' => EvidenceService::ALLOWED_EXTENSIONS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:40', 'unique:evidence,code'],
            'description' => ['nullable', 'string', 'max:2000'],
            'evidence_category_id' => ['nullable', 'exists:evidence_categories,id'],
            'unit' => ['nullable', 'string'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'academic_period_id' => ['nullable', 'exists:academic_periods,id'],
            'valid_until' => ['nullable', 'date'],
            'confidentiality' => ['nullable', Rule::in(['public', 'internal', 'restricted'])],
            'file' => EvidenceService::uploadRules(required: false),
            'url' => ['nullable', 'required_without:file', 'url', 'max:2048'],
            'map_type' => ['nullable', Rule::in(array_keys(EvidenceService::mappableTypes()))],
            'map_id' => ['nullable', 'integer'],
            'map_context' => ['nullable', 'integer'],
        ], ['url.required_without' => 'Unggah berkas atau isi tautan dokumen.', 'file.mimes' => 'Format berkas tidak diizinkan.']);
    }

    /**
     * Bukti indikator akreditasi hanya dapat diatur oleh kontributor periode (pengelola, PIC, admin prodi terkait).
     */
    private function authorizeAccreditationContext(Request $request, string $type, int $id, ?int $contextId): void
    {
        if ($type !== 'accreditation_indicator') {
            return;
        }

        $period = EvidenceService::accreditationPeriod(AccreditationIndicator::query()->findOrFail($id), $contextId);
        abort_unless($period->canContribute($request->user()) || ! $period->status->isOpen(), 403, 'Anda bukan tim penyusun periode akreditasi ini.');
    }

    private function authorizeView(Request $request, Evidence $evidence): void
    {
        $user = $request->user();
        abort_unless(Evidence::query()->visibleTo($user, $user->can('evidence.view'))->whereKey($evidence->id)->exists()
            || $this->mappedToVisibleWork($request, $evidence), 403, 'Anda tidak berhak mengakses dokumen ini.');
    }

    private function authorizeManage(Request $request, Evidence $evidence): void
    {
        $user = $request->user();
        abort_unless($user->can('evidence.manage') && ($evidence->owner_user_id === $user->id || $user->isInstitutionWide() || $user->can('evidence.verify')), 403);
    }

    /**
     * Auditor/PIC boleh melihat dokumen yang ditautkan ke temuan/rencana aksi yang menjadi tugasnya.
     */
    private function mappedToVisibleWork(Request $request, Evidence $evidence): bool
    {
        $user = $request->user();

        return $evidence->mappings()->get()->contains(function (EvidenceMapping $mapping) use ($user): bool {
            return match ($mapping->mappable_type) {
                'finding' => Finding::query()->visibleTo($user, $user->can('ami.view'))->whereKey($mapping->mappable_id)->exists(),
                'corrective_action' => Finding::query()->visibleTo($user, $user->can('ami.view'))
                    ->whereHas('correctiveActions', fn ($q) => $q->whereKey($mapping->mappable_id))->exists(),
                'recommendation' => Recommendation::query()->visibleTo($user, $user->can('improvement.view'))->whereKey($mapping->mappable_id)->exists(),
                'action_plan' => Recommendation::query()->visibleTo($user, $user->can('improvement.view'))
                    ->whereHas('actionPlans', fn ($q) => $q->whereKey($mapping->mappable_id))->exists(),
                'accreditation_indicator' => $mapping->context_id !== null && AccreditationPeriod::query()->whereKey($mapping->context_id)
                    ->where(fn ($q) => $q->where('pic_user_id', $user->id)->when($user->can('accreditation.view'), fn ($inner) => $inner->orWhere(fn ($scoped) => $scoped->visibleTo($user))))
                    ->exists(),
                default => false,
            };
        });
    }
}
