<?php

namespace App\Http\Controllers\Achievements;

use App\Enums\AchievementStatus;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Services\Achievements\Achievements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mahasiswa mencatat prestasinya sendiri; bidang kemahasiswaan yang memverifikasi.
 */
class MyAchievementController extends Controller
{
    public function __construct(private Achievements $achievements) {}

    public function index(Request $request): Response
    {
        $student = $request->user()->student()->with('studyProgram:id,name,degree')->first();

        return Inertia::render('achievements/mine', [
            'student' => $student ? [
                'name' => $student->name,
                'nim' => $student->nim,
                'study_program' => $student->studyProgram?->full_name,
            ] : null,
            'achievements' => $student
                ? $student->achievements()->with(['student.studyProgram:id,name,degree', 'verifier:id,name'])->orderByDesc('achieved_on')->orderByDesc('id')->get()
                    ->map(fn (StudentAchievement $achievement): array => $this->achievements->payload($achievement))->all()
                : [],
            ...$this->achievements->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $this->student($request);
        $this->achievements->save(null, $student, $this->achievements->validate($request), $request->file('certificate'));
        $this->toast('Prestasi tersimpan dan menunggu verifikasi bidang kemahasiswaan.');

        return back();
    }

    public function update(Request $request, StudentAchievement $achievement): RedirectResponse
    {
        $student = $this->student($request);
        abort_unless($achievement->student_id === $student->id, 403);

        if (! $achievement->status->isEditable()) {
            $this->failWith('Prestasi yang sudah diverifikasi atau ditolak tidak dapat diubah. Hubungi bidang kemahasiswaan bila ada kekeliruan.');
        }

        $this->achievements->save($achievement, $student, $this->achievements->validate($request), $request->file('certificate'));
        $this->toast('Perubahan tersimpan dan menunggu verifikasi ulang.');

        return back();
    }

    public function destroy(Request $request, StudentAchievement $achievement): RedirectResponse
    {
        $student = $this->student($request);
        abort_unless($achievement->student_id === $student->id, 403);

        if ($achievement->status === AchievementStatus::Verified) {
            $this->failWith('Prestasi yang sudah diverifikasi tidak dapat dihapus sendiri. Hubungi bidang kemahasiswaan.');
        }

        $this->achievements->delete($achievement);
        $this->toast('Prestasi dihapus.');

        return back();
    }

    private function student(Request $request): Student
    {
        $student = $request->user()->student;
        abort_unless($student !== null, 403, 'Akun Anda belum terhubung dengan data mahasiswa.');

        return $student;
    }
}
