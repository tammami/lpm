<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Lecturer;
use App\Services\AccountProvisioner;
use App\Services\AuditLogger;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LecturerController extends Controller
{
    public const RANKS = ['Tenaga Pengajar', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];

    public function index(Request $request): Response
    {
        $query = Lecturer::query()
            ->visibleTo($request->user())
            ->with(['studyProgram:id,name,degree', 'user:id,is_active,last_login_at'])
            ->withCount('teachingAssignments');

        $lecturers = TableQuery::for($query, $request)
            ->search(['name', 'nidn', 'nip', 'email'])
            ->filter(['study_program_id' => 'study_program_id', 'academic_rank' => 'academic_rank', 'employment_status' => 'employment_status'])
            ->sort(['name', 'nidn', 'academic_rank'], 'name')
            ->paginate()
            ->through(fn (Lecturer $lecturer): array => [
                ...$lecturer->only([
                    'id', 'nidn', 'nip', 'name', 'front_title', 'back_title', 'full_name', 'email', 'phone', 'gender',
                    'academic_rank', 'employment_status', 'study_program_id', 'is_active', 'teaching_assignments_count',
                ]),
                'study_program' => $lecturer->studyProgram?->full_name,
                'has_account' => $lecturer->user_id !== null,
                'last_login_at' => $lecturer->user?->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('master/lecturers', [
            'lecturers' => $lecturers,
            'filters' => $this->filters($request, ['study_program_id', 'academic_rank', 'employment_status']),
            'studyPrograms' => Options::studyPrograms($request->user()),
            'ranks' => collect(self::RANKS)->map(fn (string $rank): array => ['value' => $rank, 'label' => $rank])->all(),
            'canManage' => $request->user()->can('master.manage'),
        ]);
    }

    public function store(Request $request, AccountProvisioner $accounts): RedirectResponse
    {
        $lecturer = Lecturer::query()->create($this->validated($request));

        if ($request->boolean('create_account')) {
            $accounts->forLecturer($lecturer);
        }

        $this->toast('Data dosen berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, Lecturer $lecturer, AccountProvisioner $accounts): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $lecturer->study_program_id);
        $lecturer->update($this->validated($request, $lecturer));

        if ($request->boolean('create_account') && ! $lecturer->user_id) {
            $accounts->forLecturer($lecturer);
        }

        $this->toast('Data dosen berhasil diperbarui.');

        return back();
    }

    public function resetPassword(Request $request, Lecturer $lecturer, AccountProvisioner $accounts): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $lecturer->study_program_id);
        abort_unless($lecturer->user, 422, 'Dosen belum memiliki akun.');

        $accounts->resetPassword($lecturer->user);
        AuditLogger::log('password_reset', 'master', $lecturer->user, "Reset kata sandi dosen {$lecturer->full_name}");
        $this->toast('Kata sandi direset ke NIDN. Dosen wajib menggantinya saat masuk.');

        return back();
    }

    public function destroy(Request $request, Lecturer $lecturer): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $lecturer->study_program_id);
        $this->deleteSafely($lecturer, 'Data dosen');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Lecturer $lecturer = null): array
    {
        $validated = $request->validate([
            'study_program_id' => ['required', 'exists:study_programs,id'],
            'nidn' => ['nullable', 'string', 'max:20', Rule::unique('lecturers')->ignore($lecturer)],
            'nip' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'front_title' => ['nullable', 'string', 'max:30'],
            'back_title' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'academic_rank' => ['nullable', Rule::in(self::RANKS)],
            'employment_status' => ['required', Rule::in(['tetap', 'tidak_tetap'])],
            'is_active' => ['boolean'],
        ]);

        $this->authorizeStudyProgram($request, (int) $validated['study_program_id']);

        return $validated;
    }
}
