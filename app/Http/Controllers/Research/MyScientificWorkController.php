<?php

namespace App\Http\Controllers\Research;

use App\Enums\ScientificWorkStatus;
use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Services\Research\ScientificWorks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dosen mengisi dan memelihara data karya ilmiahnya sendiri.
 */
class MyScientificWorkController extends Controller
{
    public function __construct(private ScientificWorks $works) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user()->lecturer()->with('studyProgram:id,name,degree')->first();

        $works = $lecturer
            ? $lecturer->scientificWorks()->with('verifier:id,name')->orderByDesc('year')->orderByDesc('id')->get()
                ->map(fn (ScientificWork $work): array => $this->works->payload($work))->all()
            : [];

        return Inertia::render('research/mine', [
            'lecturer' => $lecturer ? ['name' => $lecturer->full_name, 'nidn' => $lecturer->nidn, 'study_program' => $lecturer->studyProgram?->full_name] : null,
            'works' => $works,
            ...$this->works->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        $this->works->save(null, [...$this->works->validate($request), 'lecturer_id' => $lecturer->id], $request->user());
        $this->toast('Karya ilmiah tersimpan dan menunggu verifikasi LPM.');

        return back();
    }

    public function update(Request $request, ScientificWork $work): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        abort_unless($work->lecturer_id === $lecturer->id, 403);

        $this->works->save($work, $this->works->validate($request), $request->user());
        $this->toast('Perubahan tersimpan dan menunggu verifikasi ulang.');

        return back();
    }

    public function destroy(Request $request, ScientificWork $work): RedirectResponse
    {
        $lecturer = $this->lecturer($request);
        abort_unless($work->lecturer_id === $lecturer->id, 403);

        if ($work->status === ScientificWorkStatus::Verified) {
            $this->failWith('Karya yang sudah terverifikasi tidak dapat dihapus sendiri. Hubungi admin LPM/prodi.');
        }

        $this->deleteSafely($work, 'Karya ilmiah');

        return back();
    }

    private function lecturer(Request $request): Lecturer
    {
        $lecturer = $request->user()->lecturer;
        abort_unless($lecturer !== null, 403, 'Akun Anda belum terhubung dengan data dosen.');

        return $lecturer;
    }
}
