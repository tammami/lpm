<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\StudyProgram;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StudyProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $query = StudyProgram::query()
            ->visibleTo($request->user())
            ->with(['faculty:id,name,code', 'accreditationBody:id,code'])
            ->withCount(['lecturers', 'students', 'courses']);

        $programs = TableQuery::for($query, $request)
            ->search(['name', 'code', 'head_name'])
            ->filter(['faculty_id' => 'faculty_id', 'accreditation_body_id' => 'accreditation_body_id'])
            ->sort(['name', 'code', 'accreditation_valid_until'], 'name')
            ->paginate()
            ->through(fn (StudyProgram $program): array => [
                ...$program->only([
                    'id', 'code', 'name', 'degree', 'faculty_id', 'accreditation_body_id', 'accreditation_status',
                    'accreditation_sk_number', 'head_name', 'is_active', 'full_name',
                    'lecturers_count', 'students_count', 'courses_count',
                ]),
                'accreditation_valid_until' => $program->accreditation_valid_until?->toDateString(),
                'faculty' => $program->faculty?->name,
                'accreditation_body' => $program->accreditationBody?->code,
            ]);

        return Inertia::render('master/study-programs', [
            'programs' => $programs,
            'filters' => $this->filters($request, ['faculty_id', 'accreditation_body_id']),
            'faculties' => Options::faculties(),
            'accreditationBodies' => Options::accreditationBodies(),
            'canManage' => $request->user()->can('organization.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        StudyProgram::query()->create($this->validated($request));
        $this->toast('Program studi berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, StudyProgram $studyProgram): RedirectResponse
    {
        $studyProgram->update($this->validated($request, $studyProgram));
        $this->toast('Program studi berhasil diperbarui.');

        return back();
    }

    public function destroy(StudyProgram $studyProgram): RedirectResponse
    {
        $this->deleteSafely($studyProgram, 'Program studi');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?StudyProgram $program = null): array
    {
        return $request->validate([
            'faculty_id' => ['required', 'exists:faculties,id'],
            'accreditation_body_id' => ['nullable', 'exists:accreditation_bodies,id'],
            'code' => ['required', 'string', 'max:20', Rule::unique('study_programs')->ignore($program)],
            'name' => ['required', 'string', 'max:255'],
            'degree' => ['required', Rule::in(['D3', 'D4', 'S1', 'S2', 'S3', 'Profesi'])],
            'accreditation_status' => ['nullable', 'string', 'max:50'],
            'accreditation_valid_until' => ['nullable', 'date'],
            'accreditation_sk_number' => ['nullable', 'string', 'max:255'],
            'head_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);
    }
}
