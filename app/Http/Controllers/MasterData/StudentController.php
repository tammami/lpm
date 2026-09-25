<?php

namespace App\Http\Controllers\MasterData;

use App\Enums\StudentStatus;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\AccountProvisioner;
use App\Services\AuditLogger;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Student::query()
            ->visibleTo($request->user())
            ->with(['studyProgram:id,name,degree', 'user:id,last_login_at'])
            ->withCount('classes');

        $students = TableQuery::for($query, $request)
            ->search(['name', 'nim', 'email'])
            ->filter(['study_program_id' => 'study_program_id', 'entry_year' => 'entry_year', 'status' => 'status'])
            ->sort(['name', 'nim', 'entry_year', 'semester'], 'nim')
            ->paginate()
            ->through(fn (Student $student): array => [
                ...$student->only(['id', 'nim', 'name', 'email', 'phone', 'gender', 'entry_year', 'semester', 'study_program_id', 'classes_count']),
                'status' => $student->status->value,
                'status_label' => $student->status->label(),
                'study_program' => $student->studyProgram?->full_name,
                'has_account' => $student->user_id !== null,
                'last_login_at' => $student->user?->last_login_at?->toIso8601String(),
            ]);

        return Inertia::render('master/students', [
            'students' => $students,
            'filters' => $this->filters($request, ['study_program_id', 'entry_year', 'status']),
            'studyPrograms' => Options::studyPrograms($request->user()),
            'statuses' => StudentStatus::options(),
            'entryYears' => Student::query()->visibleTo($request->user())->distinct()->orderByDesc('entry_year')->pluck('entry_year')
                ->map(fn ($year): array => ['value' => $year, 'label' => (string) $year])->all(),
            'canManage' => $request->user()->can('master.manage'),
        ]);
    }

    public function store(Request $request, AccountProvisioner $accounts): RedirectResponse
    {
        $student = Student::query()->create($this->validated($request));

        if ($request->boolean('create_account', true)) {
            $accounts->forStudent($student);
        }

        $this->toast('Data mahasiswa berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, Student $student, AccountProvisioner $accounts): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $student->study_program_id);
        $student->update($this->validated($request, $student));

        if ($request->boolean('create_account') && ! $student->user_id) {
            $accounts->forStudent($student);
        }

        $this->toast('Data mahasiswa berhasil diperbarui.');

        return back();
    }

    public function resetPassword(Request $request, Student $student, AccountProvisioner $accounts): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $student->study_program_id);
        abort_unless($student->user, 422, 'Mahasiswa belum memiliki akun.');

        $accounts->resetPassword($student->user);
        AuditLogger::log('password_reset', 'master', $student->user, "Reset kata sandi mahasiswa {$student->nim}");
        $this->toast('Kata sandi direset ke NIM. Mahasiswa wajib menggantinya saat masuk.');

        return back();
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $student->study_program_id);
        $this->deleteSafely($student, 'Data mahasiswa');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Student $student = null): array
    {
        $validated = $request->validate([
            'study_program_id' => ['required', 'exists:study_programs,id'],
            'nim' => ['required', 'string', 'max:20', Rule::unique('students')->ignore($student)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::in(['L', 'P'])],
            'entry_year' => ['required', 'integer', 'between:1990,2100'],
            'semester' => ['required', 'integer', 'between:1,14'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
        ]);

        $this->authorizeStudyProgram($request, (int) $validated['study_program_id']);

        return $validated;
    }
}
