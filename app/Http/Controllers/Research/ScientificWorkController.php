<?php

namespace App\Http\Controllers\Research;

use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Notifications\ScientificWorkRevisionNotification;
use App\Services\AuditLogger;
use App\Services\Research\ScientificWorks;
use App\Support\Options;
use App\Support\Spreadsheet;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data karya ilmiah seluruh dosen dalam cakupan pengguna: pengisian atas nama dosen, verifikasi, ekspor.
 */
class ScientificWorkController extends Controller
{
    private const FILTERS = ['study_program_id', 'lecturer_id', 'type', 'year', 'status'];

    public function __construct(private ScientificWorks $works) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $works = $this->query($request, ['lecturer.studyProgram:id,name,degree', 'verifier:id,name'])
            ->paginate()
            ->through(fn (ScientificWork $work): array => $this->works->payload($work));

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
        $this->works->save(null, [...$this->works->validate($request), 'lecturer_id' => $lecturer->id], $request->user());
        $this->toast('Karya ilmiah berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, ScientificWork $work): RedirectResponse
    {
        $this->authorizeWork($request, $work);
        $lecturer = $this->lecturer($request);
        $this->works->save($work, [...$this->works->validate($request), 'lecturer_id' => $lecturer->id], $request->user());
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
            'decision' => ['required', Rule::in([ScientificWorkStatus::Verified->value, ScientificWorkStatus::Revision->value])],
            'note' => ['nullable', 'required_if:decision,revision', 'string', 'max:1000'],
        ], ['note.required_if' => 'Tuliskan apa yang perlu diperbaiki dosen.']);

        $verified = $validated['decision'] === ScientificWorkStatus::Verified->value;

        $work->update([
            'status' => $validated['decision'],
            'verification_note' => $verified ? null : $validated['note'],
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        if (! $verified && ($owner = $work->lecturer()->with('user')->first()?->user) && $owner->id !== $request->user()->id) {
            $owner->notify(new ScientificWorkRevisionNotification($work));
        }

        $this->toast($verified ? 'Karya ilmiah terverifikasi.' : 'Dikembalikan ke dosen untuk diperbaiki.');

        return back();
    }

    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('exported', 'research', null, 'Ekspor data karya ilmiah dosen');

        return Spreadsheet::download(
            'karya-ilmiah-dosen-'.now()->format('Ymd').'.xlsx',
            ['NIDN', 'Dosen', 'Program Studi', 'Tahun', 'Judul', 'Jenis', 'Peringkat/Indeksasi', 'Jurnal/Penerbit/Kegiatan', 'Volume/Halaman', 'ISSN/ISBN/No. HKI', 'DOI/Tautan', 'Peran Penulis', 'Penulis Lain', 'Asal Kegiatan', 'Sumber Dana', 'Status Verifikasi', 'Catatan Verifikasi'],
            $this->query($request, ['lecturer.studyProgram:id,name,degree'])->builder()->lazy()
                ->map(fn (ScientificWork $work): array => array_values([
                    $work->lecturer->nidn,
                    $work->lecturer->full_name,
                    $work->lecturer->studyProgram?->full_name,
                    $work->year,
                    $work->title,
                    $work->type->label(),
                    $work->rank ? (ScientificWorkType::allRanks()[$work->rank] ?? $work->rank) : null,
                    $work->venue,
                    $work->volume,
                    $work->identifier,
                    $work->url,
                    ScientificWork::AUTHOR_ROLES[$work->author_role] ?? $work->author_role,
                    $work->co_authors,
                    ScientificWork::SOURCES[$work->source] ?? $work->source,
                    ScientificWork::FUNDINGS[$work->funding] ?? $work->funding,
                    $work->status->label(),
                    $work->verification_note,
                ])),
            [14, 30, 30, 8, 60, 34, 16, 36, 20, 20, 36, 20, 36, 22, 30, 20, 36],
            'Karya Ilmiah',
        );
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
            ->when($request->filled('study_program_id') && $request->input('study_program_id') !== 'all', fn (Builder $q) => $q->whereHas(
                'lecturer', fn (Builder $lecturer) => $lecturer->where('study_program_id', $request->integer('study_program_id')),
            ));

        return TableQuery::for($query, $request)
            ->search(['title', 'venue', 'co_authors', 'lecturer.name', 'lecturer.nidn'])
            ->filter(['lecturer_id' => 'lecturer_id', 'type' => 'type', 'year' => 'year', 'status' => 'status'])
            ->sort(['year', 'title', 'updated_at'], '-year');
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
