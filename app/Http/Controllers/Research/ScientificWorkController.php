<?php

namespace App\Http\Controllers\Research;

use App\Enums\ScientificWorkCategory;
use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\Student;
use App\Notifications\ScientificWorkReviewedNotification;
use App\Services\AuditLogger;
use App\Services\Research\ScientificWorks;
use App\Services\Research\WorkWorkbook;
use App\Support\Options;
use App\Support\Spreadsheet;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data karya ilmiah seluruh dosen dalam cakupan pengguna: pengisian atas nama dosen, verifikasi, ekspor format template LPM.
 */
class ScientificWorkController extends Controller
{
    /** Panjang minimal kata kunci pencarian orang untuk ditandai. */
    private const MIN_SEARCH = 3;

    private const FILTERS = ['study_program_id', 'lecturer_id', 'category', 'type', 'indexation_family', 'year', 'status', 'with_students'];

    public function __construct(private ScientificWorks $works) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $works = $this->query($request, ScientificWorks::payloadRelations())
            ->paginate()
            ->through(fn (ScientificWork $work): array => [
                ...$this->works->payload($work),
                'possible_duplicates' => $user->can('research.verify') && $work->status === ScientificWorkStatus::Pending
                    ? $this->works->possibleDuplicates($work, $user)
                    : [],
            ]);

        $statusCounts = ScientificWork::query()->visibleTo($user)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('research/works', [
            'works' => $works,
            'filters' => $this->filters($request, self::FILTERS),
            'studyPrograms' => Options::studyPrograms($user),
            'lecturers' => Options::lecturers($user),
            'years' => ScientificWork::query()->visibleTo($user)->distinct()->orderByDesc('year')->pluck('year')
                ->map(fn (int $year): array => ['value' => $year, 'label' => (string) $year])->all(),
            'statusCounts' => collect(ScientificWorkStatus::cases())->mapWithKeys(fn (ScientificWorkStatus $status): array => [$status->value => (int) ($statusCounts[$status->value] ?? 0)]),
            ...$this->works->formOptions(),
            'can' => [
                'manage' => $user->can('research.manage'),
                'verify' => $user->can('research.verify'),
                'analysis' => $user->can('research.view'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        $this->works->save(null, [...$this->works->validate($request, $lecturer->id), 'lecturer_id' => $lecturer->id], $request->user());
        $this->toast('Karya ilmiah berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, ScientificWork $work): RedirectResponse
    {
        $this->authorizeWork($request, $work);
        $lecturer = $this->lecturer($request);
        $this->works->save($work, [...$this->works->validate($request, $lecturer->id), 'lecturer_id' => $lecturer->id], $request->user());
        $this->toast('Karya ilmiah berhasil diperbarui.');

        return back();
    }

    public function destroy(Request $request, ScientificWork $work): RedirectResponse
    {
        $this->authorizeWork($request, $work);
        $this->deleteSafely($work, 'Karya ilmiah');

        return back();
    }

    public function verify(Request $request, ScientificWork $work): RedirectResponse
    {
        $this->authorizeWork($request, $work);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(array_map(fn (ScientificWorkStatus $status): string => $status->value, ScientificWorkStatus::decisions()))],
            'note' => ['nullable', Rule::requiredIf(fn (): bool => $request->input('decision') !== ScientificWorkStatus::Verified->value), 'string', 'max:1000'],
        ], ['note.required' => 'Tuliskan catatan untuk dosen (apa yang perlu diperbaiki, atau mengapa tidak valid/duplikat).']);

        $status = ScientificWorkStatus::from($validated['decision']);

        $work->update([
            'status' => $status,
            'verification_note' => $validated['note'] ?? null,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        if ($status !== ScientificWorkStatus::Verified && ($owner = $work->lecturer()->with('user')->first()?->user) && $owner->id !== $request->user()->id) {
            $owner->notify(new ScientificWorkReviewedNotification($work));
        }

        $this->toast(match ($status) {
            ScientificWorkStatus::Verified => 'Karya ilmiah dinyatakan valid.',
            ScientificWorkStatus::Revision => 'Dikembalikan ke dosen untuk diperbaiki.',
            ScientificWorkStatus::Invalid => 'Karya ilmiah dinyatakan tidak valid.',
            default => 'Karya ilmiah ditandai duplikat.',
        });

        return back();
    }

    public function export(Request $request, WorkWorkbook $workbook): StreamedResponse
    {
        AuditLogger::log('exported', 'research', null, 'Ekspor data karya ilmiah dosen');

        $works = $this->query($request, WorkWorkbook::relations())->builder()->get();
        $lecturers = Lecturer::query()
            ->visibleTo($request->user())
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $works->pluck('lecturer_id')))
            ->when($this->filled($request, 'study_program_id'), fn (Builder $q) => $q->where('study_program_id', $request->integer('study_program_id')))
            ->when($this->filled($request, 'lecturer_id'), fn (Builder $q) => $q->whereKey($request->integer('lecturer_id')))
            ->with('studyProgram:id,name,degree')
            ->orderBy('name')
            ->get();

        return Spreadsheet::downloadSheets('karya-dosen-'.now()->format('Ymd').'.xlsx', $workbook->sheets($works, $lecturers));
    }

    /**
     * Pencarian dosen rekan / mahasiswa untuk ditandai sebagai penulis bersama.
     */
    public function people(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'jenis' => ['required', Rule::in(['dosen', 'mahasiswa'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $term = trim($validated['q'] ?? '');

        // Wajib ada kata kunci agar daftar mahasiswa/dosen tidak dapat ditelusuri seluruhnya.
        if (mb_strlen($term) < self::MIN_SEARCH) {
            return response()->json([]);
        }

        if ($validated['jenis'] === 'dosen') {
            $items = Lecturer::query()
                ->where('is_active', true)
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")->orWhere('nidn', 'like', "{$term}%")->orWhere('nuptk', 'like', "{$term}%"))
                ->with('studyProgram:id,name,degree')
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn (Lecturer $lecturer): array => [
                    'id' => $lecturer->id,
                    'name' => $lecturer->full_name,
                    'number' => $lecturer->identifier,
                    'study_program' => $lecturer->studyProgram?->full_name,
                ]);
        } else {
            $items = Student::query()
                ->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', "%{$term}%")->orWhere('nim', 'like', "{$term}%"))
                ->with('studyProgram:id,name,degree')
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'number' => $student->nim,
                    'study_program' => $student->studyProgram?->full_name,
                ]);
        }

        return response()->json($items);
    }

    /**
     * Query daftar sesuai filter halaman dan cakupan pengguna.
     *
     * @param  list<string>  $with
     * @return TableQuery<ScientificWork>
     */
    private function query(Request $request, array $with = []): TableQuery
    {
        $query = ScientificWork::query()
            ->with($with)
            ->visibleTo($request->user())
            ->when($this->filled($request, 'study_program_id'), fn (Builder $q) => $q->creditedTo(
                Lecturer::query()->where('study_program_id', $request->integer('study_program_id'))->pluck('id')->all(),
            ))
            ->when($this->filled($request, 'lecturer_id'), fn (Builder $q) => $q->creditedTo($request->integer('lecturer_id')))
            ->when($this->filled($request, 'category'), fn (Builder $q) => $q->whereIn('type', $this->typesOf((string) $request->input('category'))))
            ->when($this->filled($request, 'indexation_family'), fn (Builder $q) => $q->whereIn('indexation', $this->indexationsOf((string) $request->input('indexation_family'))))
            ->when($request->boolean('with_students'), fn (Builder $q) => $q->has('studentMembers'));

        return TableQuery::for($query, $request)
            ->search(['title', 'venue', 'doi', 'co_authors', 'lecturer.name', 'lecturer.nidn', 'lecturer.nuptk', 'members.name', 'members.nim'])
            ->filter(['type' => 'type', 'year' => 'year', 'status' => 'status'])
            ->sort(['year', 'title', 'updated_at'], '-year');
    }

    private function filled(Request $request, string $key): bool
    {
        return $request->filled($key) && $request->input($key) !== 'all';
    }

    /**
     * @return list<string>
     */
    private function typesOf(string $category): array
    {
        return collect(ScientificWorkType::cases())
            ->filter(fn (ScientificWorkType $type): bool => $type->category() === ScientificWorkCategory::tryFrom($category))
            ->map->value->values()->all();
    }

    /**
     * @return list<string>
     */
    private function indexationsOf(string $family): array
    {
        return collect(ScientificWorkIndexation::cases())
            ->filter(fn (ScientificWorkIndexation $case): bool => $case->family() === $family)
            ->map->value->values()->all();
    }

    /**
     * Dosen tujuan harus berada dalam cakupan pengguna.
     */
    private function lecturer(Request $request): Lecturer
    {
        $request->validate(['lecturer_id' => ['required', 'integer', 'exists:lecturers,id']], ['lecturer_id.required' => 'Pilih dosen pemilik karya.']);
        $lecturer = Lecturer::query()->findOrFail($request->integer('lecturer_id'));
        $this->authorizeStudyProgram($request, $lecturer->study_program_id);

        return $lecturer;
    }

    private function authorizeWork(Request $request, ScientificWork $work): void
    {
        $this->authorizeStudyProgram($request, (int) $work->lecturer()->value('study_program_id'));
    }
}
