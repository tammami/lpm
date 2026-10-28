<?php

namespace App\Http\Controllers\Activities;

use App\Enums\LpmActivityCategory;
use App\Enums\LpmActivityStatus;
use App\Http\Controllers\Controller;
use App\Models\EvidenceMapping;
use App\Models\LpmActivity;
use App\Services\AuditLogger;
use App\Services\Evidence\EvidenceService;
use App\Support\CodeGenerator;
use App\Support\EvidenceOptions;
use App\Support\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Timeline kegiatan program kerja LPM (di luar Monev & AMI) beserta bukti pelaksanaannya.
 */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $year = $this->year($request);

        $activities = $this->query($request, $year)
            ->withCount('evidenceMappings')
            ->orderBy('start_date')->orderBy('id')
            ->get();

        $all = LpmActivity::query()->where('year', $year)->get(['status', 'end_date']);

        return Inertia::render('activities/index', [
            'activities' => $activities->map(fn (LpmActivity $activity): array => $this->payload($activity))->all(),
            'filters' => [...$this->filters($request, ['status', 'category']), 'year' => (string) $year],
            'years' => $this->years($year),
            'stats' => [
                'total' => $all->count(),
                'done' => $all->where('status', LpmActivityStatus::Done)->count(),
                'ongoing' => $all->where('status', LpmActivityStatus::Ongoing)->count(),
                'planned' => $all->where('status', LpmActivityStatus::Planned)->count(),
                'overdue' => $all->filter(fn (LpmActivity $activity): bool => $activity->isOverdue())->count(),
                'cancelled' => $all->whereIn('status', [LpmActivityStatus::Cancelled, LpmActivityStatus::Postponed])->count(),
            ],
            ...$this->formOptions(),
            'canManage' => $request->user()->can('activities.manage'),
        ]);
    }

    public function show(Request $request, LpmActivity $activity): Response
    {
        $activity->load(['creator:id,name', 'evidenceMappings.evidence.currentVersion']);
        $canManage = $request->user()->can('activities.manage');

        return Inertia::render('activities/show', [
            'activity' => [
                ...$this->payload($activity),
                'creator' => $activity->creator?->name,
                'created_at' => $activity->created_at?->toIso8601String(),
                'updated_at' => $activity->updated_at?->toIso8601String(),
                'evidence' => EvidenceService::linkedPayload($activity->evidenceMappings, $request->user(), ! $canManage),
            ],
            ...$this->formOptions(),
            'evidenceOptions' => $canManage ? EvidenceOptions::for($request) : null,
            'canManage' => $canManage,
        ]);
    }

    /**
     * Seluruh bukti program kerja dalam satu tahun, dikelompokkan per kegiatan.
     */
    public function evidence(Request $request): Response
    {
        $year = $this->year($request);
        $canManage = $request->user()->can('activities.manage');
        $search = trim((string) $request->string('search'));

        $activities = LpmActivity::query()
            ->where('year', $year)
            ->when($request->filled('category') && $request->input('category') !== 'all', fn (Builder $q) => $q->where('category', $request->input('category')))
            ->when($request->input('evidence') === 'missing', fn (Builder $q) => $q->doesntHave('evidenceMappings'))
            ->when($request->input('evidence') === 'has', fn (Builder $q) => $q->has('evidenceMappings'))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
                ->orWhereHas('evidenceMappings.evidence', fn (Builder $evidence) => $evidence->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))))
            ->with('evidenceMappings.evidence.currentVersion')
            ->orderBy('start_date')->orderBy('id')
            ->get();

        $mappingCounts = EvidenceMapping::query()
            ->where('mappable_type', (new LpmActivity)->getMorphClass())
            ->whereIn('mappable_id', LpmActivity::query()->where('year', $year)->select('id'))
            ->selectRaw('count(*) as documents, count(distinct mappable_id) as activities')
            ->first();
        $doneWithout = LpmActivity::query()->where('year', $year)->where('status', LpmActivityStatus::Done)->doesntHave('evidenceMappings')->count();

        return Inertia::render('activities/evidence', [
            'activities' => $activities->map(fn (LpmActivity $activity): array => [
                ...$this->payload($activity),
                'evidence' => EvidenceService::linkedPayload($activity->evidenceMappings, $request->user(), ! $canManage),
            ])->all(),
            'filters' => [...$this->filters($request, ['category', 'evidence']), 'year' => (string) $year],
            'years' => $this->years($year),
            'stats' => [
                'documents' => (int) ($mappingCounts->documents ?? 0),
                'activities_with' => (int) ($mappingCounts->activities ?? 0),
                'total' => LpmActivity::query()->where('year', $year)->count(),
                'done_without' => $doneWithout,
            ],
            'categories' => LpmActivityCategory::options(),
            'evidenceOptions' => $canManage ? EvidenceOptions::for($request) : null,
            'canManage' => $canManage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $activity = DB::transaction(fn (): LpmActivity => LpmActivity::query()->create([
            ...$validated,
            'code' => CodeGenerator::next('PK', 'lpm_activities'),
            'year' => (int) substr($validated['start_date'], 0, 4),
            'created_by' => $request->user()->id,
        ]));

        $this->toast("Kegiatan {$activity->code} ditambahkan ke timeline.");

        return back();
    }

    public function update(Request $request, LpmActivity $activity): RedirectResponse
    {
        $validated = $this->validated($request);
        $activity->update([...$validated, 'year' => (int) substr($validated['start_date'], 0, 4)]);
        $this->toast('Kegiatan diperbarui.');

        return back();
    }

    /**
     * Ubah status dengan cepat (mis. tandai terlaksana) beserta catatan realisasinya.
     */
    public function status(Request $request, LpmActivity $activity): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(LpmActivityStatus::class)],
            'realization_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $activity->update([
            'status' => $validated['status'],
            'realization_note' => $validated['realization_note'] ?? $activity->realization_note,
        ]);

        $this->toast('Status kegiatan: '.$activity->status->label().'.');

        return back();
    }

    public function destroy(LpmActivity $activity): RedirectResponse
    {
        // Dokumennya tetap tersimpan di repositori; hanya tautannya yang dilepas.
        DB::transaction(function () use ($activity): void {
            $activity->evidenceMappings()->delete();
            $activity->delete();
        });

        $this->toast('Kegiatan dihapus. Dokumen buktinya tetap tersimpan di repositori Dokumen Bukti.');

        return redirect()->route('activities.index', ['year' => $activity->year]);
    }

    public function export(Request $request): StreamedResponse
    {
        $year = $this->year($request);
        AuditLogger::log('exported', 'activities', null, "Ekspor timeline program kerja LPM {$year}");

        $activities = $this->query($request, $year)->withCount('evidenceMappings')->orderBy('start_date')->orderBy('id')->get();

        return Spreadsheet::download(
            "program-kerja-lpm-{$year}.xlsx",
            ['No', 'Kode', 'Kegiatan', 'Jenis', 'Tanggal mulai', 'Tanggal selesai', 'Tempat', 'Penanggung jawab', 'Sasaran/peserta', 'Target/luaran', 'Anggaran (Rp)', 'Status', 'Catatan realisasi', 'Jumlah bukti'],
            $activities->values()->map(fn (LpmActivity $activity, int $index): array => [
                $index + 1,
                $activity->code,
                $activity->title,
                $activity->category->label(),
                $activity->start_date->format('d/m/Y'),
                $activity->end_date->format('d/m/Y'),
                $activity->location,
                $activity->pic,
                $activity->participants,
                $activity->target_output,
                $activity->budget,
                $activity->isOverdue() ? $activity->status->label().' (terlambat)' : $activity->status->label(),
                $activity->realization_note,
                $activity->evidence_mappings_count,
            ]),
            [6, 16, 48, 26, 14, 14, 24, 26, 26, 40, 16, 22, 48, 12],
            "Program Kerja LPM Tahun {$year}",
        );
    }

    /**
     * @return Builder<LpmActivity>
     */
    private function query(Request $request, int $year): Builder
    {
        $search = trim((string) $request->string('search'));

        return LpmActivity::query()
            ->where('year', $year)
            ->when($request->filled('category') && $request->input('category') !== 'all', fn (Builder $q) => $q->where('category', $request->input('category')))
            ->when($request->input('status') === 'overdue', fn (Builder $q) => $q
                ->whereIn('status', [LpmActivityStatus::Planned, LpmActivityStatus::Ongoing])
                ->whereDate('end_date', '<', now()->toDateString()))
            ->when($request->filled('status') && ! in_array($request->input('status'), ['all', 'overdue'], true), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")
                ->orWhere('pic', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")));
    }

    private function year(Request $request): int
    {
        $year = $request->integer('year', now()->year);

        return $year >= 2000 && $year <= 2100 ? $year : now()->year;
    }

    /**
     * Tahun yang tersedia untuk dipilih: tahun berjalan, tahun depan, dan setiap tahun yang sudah punya kegiatan.
     *
     * @return list<array{value: string, label: string}>
     */
    private function years(int $selected): array
    {
        return LpmActivity::query()->distinct()->pluck('year')
            ->push(now()->year, now()->year + 1, $selected)
            ->unique()->sortDesc()->values()
            ->map(fn (int $year): array => ['value' => (string) $year, 'label' => (string) $year])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(LpmActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'code' => $activity->code,
            'year' => $activity->year,
            'title' => $activity->title,
            'category' => $activity->category->value,
            'category_label' => $activity->category->label(),
            'description' => $activity->description,
            'target_output' => $activity->target_output,
            'start_date' => $activity->start_date->toDateString(),
            'end_date' => $activity->end_date->toDateString(),
            'location' => $activity->location,
            'pic' => $activity->pic,
            'participants' => $activity->participants,
            'budget' => $activity->budget,
            'status' => $activity->status->value,
            'status_label' => $activity->status->label(),
            'is_overdue' => $activity->isOverdue(),
            'realization_note' => $activity->realization_note,
            'evidence_count' => $activity->evidence_mappings_count ?? ($activity->relationLoaded('evidenceMappings') ? $activity->evidenceMappings->count() : 0),
            'show_url' => route('activities.show', $activity),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'categories' => LpmActivityCategory::options(),
            'statuses' => LpmActivityStatus::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(LpmActivityCategory::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'target_output' => ['nullable', 'string', 'max:2000'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:255'],
            'pic' => ['nullable', 'string', 'max:255'],
            'participants' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
            'status' => ['required', Rule::enum(LpmActivityStatus::class)],
            'realization_note' => ['nullable', 'string', 'max:5000'],
        ], [
            'title.required' => 'Isi nama kegiatan.',
            'start_date.required' => 'Isi tanggal mulai.',
            'end_date.required' => 'Isi tanggal selesai (sama dengan tanggal mulai bila kegiatan sehari).',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ]);
    }
}
