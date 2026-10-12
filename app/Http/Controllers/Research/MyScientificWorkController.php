<?php

namespace App\Http\Controllers\Research;

use App\Enums\ScientificWorkStatus;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\ScientificWorkMember;
use App\Services\AuditLogger;
use App\Services\Research\ScientificWorks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dosen mengisi dan memelihara data karyanya sendiri, serta melihat karya dosen lain yang menandainya.
 */
class MyScientificWorkController extends Controller
{
    public function __construct(private ScientificWorks $works) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user()->lecturer()->with('studyProgram:id,name,degree')->first();
        $payload = fn (ScientificWork $work): array => $this->works->payload($work);

        return Inertia::render('research/mine', [
            'lecturer' => $lecturer ? [
                'id' => $lecturer->id,
                'name' => $lecturer->full_name,
                'nidn' => $lecturer->nidn,
                'nuptk' => $lecturer->nuptk,
                'study_program' => $lecturer->studyProgram?->full_name,
            ] : null,
            'works' => $lecturer
                ? $lecturer->scientificWorks()->with(ScientificWorks::payloadRelations())->orderByDesc('year')->orderByDesc('id')->get()->map($payload)->all()
                : [],
            'tagged' => $lecturer
                ? ScientificWork::query()
                    ->whereHas('lecturerMembers', fn ($query) => $query->where('lecturer_id', $lecturer->id))
                    ->with(ScientificWorks::payloadRelations())
                    ->orderByDesc('year')->orderByDesc('id')->get()->map($payload)->all()
                : [],
            'tab' => $request->query('tab') === 'ditandai' ? 'tagged' : 'own',
            ...$this->works->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        $this->works->save(null, [...$this->works->validate($request, $lecturer->id), 'lecturer_id' => $lecturer->id], $request->user());
        $this->toast('Karya ilmiah tersimpan dan menunggu verifikasi LPM.');

        return back();
    }

    public function update(Request $request, ScientificWork $work): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        abort_unless($work->lecturer_id === $lecturer->id, 403);

        $this->works->save($work, $this->works->validate($request, $lecturer->id), $request->user());
        $this->toast('Perubahan tersimpan dan menunggu verifikasi ulang.');

        return back();
    }

    public function destroy(Request $request, ScientificWork $work): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        abort_unless($work->lecturer_id === $lecturer->id, 403);

        if ($work->status === ScientificWorkStatus::Verified) {
            $this->failWith('Karya yang sudah valid tidak dapat dihapus sendiri. Hubungi admin LPM/prodi.');
        }

        $this->deleteSafely($work, 'Karya ilmiah');

        return back();
    }

    /**
     * "Bukan saya": dosen melepas tanda dirinya dari karya dosen lain.
     */
    public function untag(Request $request, ScientificWork $work): RedirectResponse
    {
        $lecturer = $this->lecturer($request);

        $removed = $work->members()
            ->where('kind', ScientificWorkMember::LECTURER)
            ->where('lecturer_id', $lecturer->id)
            ->delete();

        abort_unless($removed > 0, 404);

        AuditLogger::log('updated', 'research', $work, "{$lecturer->full_name} melepas tanda penulis bersama pada karya \"{$work->title}\"");
        $this->toast('Tanda Anda pada karya tersebut sudah dilepas.');

        return back();
    }

    private function lecturer(Request $request): Lecturer
    {
        $lecturer = $request->user()->lecturer;
        abort_unless($lecturer !== null, 403, 'Akun Anda belum terhubung dengan data dosen.');

        return $lecturer;
    }
}
