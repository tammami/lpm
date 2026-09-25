<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\Options;
use App\Support\TableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Course::query()
            ->visibleTo($request->user())
            ->with('studyProgram:id,name,degree')
            ->withCount('classes');

        $courses = TableQuery::for($query, $request)
            ->search(['name', 'code'])
            ->filter(['study_program_id' => 'study_program_id', 'semester' => 'semester', 'type' => 'type'])
            ->sort(['code', 'name', 'semester', 'credits'], 'code')
            ->paginate()
            ->through(fn (Course $course): array => [
                ...$course->only(['id', 'code', 'name', 'credits', 'semester', 'type', 'study_program_id', 'is_active', 'classes_count']),
                'study_program' => $course->studyProgram?->full_name,
            ]);

        return Inertia::render('master/courses', [
            'courses' => $courses,
            'filters' => $this->filters($request, ['study_program_id', 'semester', 'type']),
            'studyPrograms' => Options::studyPrograms($request->user()),
            'canManage' => $request->user()->can('master.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Course::query()->create($this->validated($request));
        $this->toast('Mata kuliah berhasil ditambahkan.');

        return back();
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $course->study_program_id);
        $course->update($this->validated($request, $course));
        $this->toast('Mata kuliah berhasil diperbarui.');

        return back();
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $course->study_program_id);
        $this->deleteSafely($course, 'Mata kuliah');

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Course $course = null): array
    {
        $validated = $request->validate([
            'study_program_id' => ['required', 'exists:study_programs,id'],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('courses')->where('study_program_id', $request->integer('study_program_id'))->ignore($course),
            ],
            'name' => ['required', 'string', 'max:255'],
            'credits' => ['required', 'integer', 'between:1,24'],
            'semester' => ['required', 'integer', 'between:1,14'],
            'type' => ['required', Rule::in(['wajib', 'pilihan'])],
            'is_active' => ['boolean'],
        ]);

        $this->authorizeStudyProgram($request, (int) $validated['study_program_id']);

        return $validated;
    }
}
