<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Services\AuditLogger;
use App\Support\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseClassController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $periodId = $request->integer('period_id') ?: AcademicPeriod::active()?->id;

        $query = CourseClass::query()
            ->visibleTo($user)
            ->with(['course.studyProgram:id,name,degree', 'lecturers:id,name,front_title,back_title'])
            ->withCount('students')
            ->where('academic_period_id', $periodId)
            ->when($request->filled('study_program_id') && $request->input('study_program_id') !== 'all', fn (Builder $q) => $q->whereHas(
                'course', fn (Builder $course) => $course->where('study_program_id', $request->integer('study_program_id')),
            ))
            ->when($request->filled('search'), function (Builder $q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->where(fn (Builder $inner) => $inner
                    ->whereHas('course', fn (Builder $course) => $course->where('name', 'like', $term)->orWhere('code', 'like', $term))
                    ->orWhereHas('lecturers', fn (Builder $lecturer) => $lecturer->where('name', 'like', $term)));
            })
            ->join('courses', 'courses.id', '=', 'course_classes.course_id')
            ->select('course_classes.*')
            ->orderBy('courses.code')
            ->orderBy('course_classes.code');

        $classes = $query->paginate(15)->withQueryString()->through(fn (CourseClass $class): array => [
            'id' => $class->id,
            'code' => $class->code,
            'capacity' => $class->capacity,
            'course_id' => $class->course_id,
            'course_code' => $class->course->code,
            'course_name' => $class->course->name,
            'credits' => $class->course->credits,
            'semester' => $class->course->semester,
            'study_program' => $class->course->studyProgram?->full_name,
            'students_count' => $class->students_count,
            'lecturers' => $class->lecturers->map(fn ($lecturer): array => [
                'id' => $lecturer->id,
                'name' => $lecturer->full_name,
                'role' => $lecturer->pivot->role,
            ])->all(),
        ]);

        return Inertia::render('master/classes/index', [
            'classes' => $classes,
            'filters' => [...$this->filters($request, ['study_program_id']), 'period_id' => $periodId],
            'periods' => Options::periods(),
            'studyPrograms' => Options::studyPrograms($user),
            'courses' => Course::query()->visibleTo($user)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name', 'study_program_id'])
                ->map(fn (Course $course): array => ['value' => $course->id, 'label' => "{$course->code} — {$course->name}", 'study_program_id' => $course->study_program_id])->all(),
            'lecturers' => Options::lecturers($user, scoped: false),
            'canManage' => $user->can('master.manage'),
        ]);
    }

    public function show(Request $request, CourseClass $class): Response
    {
        $this->authorizeStudyProgram($request, $class->course->study_program_id);

        $class->load([
            'course.studyProgram', 'academicPeriod',
            'teachingAssignments.lecturer.studyProgram',
            'students' => fn ($query) => $query->orderBy('nim'),
            'students.studyProgram:id,name,degree',
        ]);

        return Inertia::render('master/classes/show', [
            'courseClass' => [
                'id' => $class->id,
                'code' => $class->code,
                'capacity' => $class->capacity,
                'course' => $class->course->only(['id', 'code', 'name', 'credits', 'semester']),
                'study_program' => $class->course->studyProgram->full_name,
                'study_program_id' => $class->course->study_program_id,
                'period' => $class->academicPeriod->only(['id', 'name']),
                'assignments' => $class->teachingAssignments->map(fn (TeachingAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'role' => $assignment->role,
                    'lecturer_id' => $assignment->lecturer_id,
                    'name' => $assignment->lecturer->full_name,
                    'nidn' => $assignment->lecturer->nidn,
                    'study_program' => $assignment->lecturer->studyProgram?->full_name,
                ])->all(),
                'students' => $class->students->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'nim' => $student->nim,
                    'name' => $student->name,
                    'entry_year' => $student->entry_year,
                    'study_program' => $student->studyProgram?->full_name,
                ])->all(),
            ],
            'lecturers' => Options::lecturers($request->user(), scoped: false),
            'entryYears' => Student::query()->where('study_program_id', $class->course->study_program_id)
                ->distinct()->orderByDesc('entry_year')->pluck('entry_year')
                ->map(fn ($year): array => ['value' => $year, 'label' => "Angkatan {$year}"])->all(),
            'canManage' => $request->user()->can('master.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $lecturerIds = $validated['lecturer_ids'] ?? [];
        unset($validated['lecturer_ids']);

        $class = CourseClass::query()->create($validated);

        foreach (array_values($lecturerIds) as $index => $lecturerId) {
            $class->teachingAssignments()->create(['lecturer_id' => $lecturerId, 'role' => $index === 0 ? 'koordinator' : 'anggota']);
        }

        $this->toast('Kelas berhasil dibuat. Tambahkan mahasiswa melalui halaman detail kelas.');

        return redirect()->route('classes.show', $class);
    }

    public function update(Request $request, CourseClass $class): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $class->course->study_program_id);

        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('course_classes')->where('course_id', $class->course_id)->where('academic_period_id', $class->academic_period_id)->ignore($class),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $class->update($validated);
        $this->toast('Kelas berhasil diperbarui.');

        return back();
    }

    public function destroy(Request $request, CourseClass $class): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $class->course->study_program_id);
        $this->deleteSafely($class, 'Kelas');

        return redirect()->route('classes.index');
    }

    public function assignLecturer(Request $request, CourseClass $class): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $class->course->study_program_id);

        $validated = $request->validate([
            'lecturer_id' => ['required', 'exists:lecturers,id', Rule::unique('teaching_assignments')->where('course_class_id', $class->id)],
            'role' => ['required', Rule::in(['koordinator', 'anggota', 'pengampu'])],
        ], ['lecturer_id.unique' => 'Dosen sudah terdaftar sebagai pengampu kelas ini.']);

        $class->teachingAssignments()->create($validated);
        $this->toast('Dosen pengampu ditambahkan.');

        return back();
    }

    public function removeLecturer(Request $request, CourseClass $class, TeachingAssignment $assignment): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $class->course->study_program_id);
        abort_unless($assignment->course_class_id === $class->id, 404);

        $this->deleteSafely($assignment, 'Penugasan dosen');

        return back();
    }

    /**
     * Tambahkan mahasiswa berdasarkan daftar NIM atau satu angkatan sekaligus.
     */
    public function enroll(Request $request, CourseClass $class): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $class->course->study_program_id);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['nim', 'cohort'])],
            'nims' => ['required_if:mode,nim', 'nullable', 'string'],
            'entry_year' => ['required_if:mode,cohort', 'nullable', 'integer'],
        ]);

        if ($validated['mode'] === 'cohort') {
            $students = Student::query()
                ->where('study_program_id', $class->course->study_program_id)
                ->where('entry_year', $validated['entry_year'])
                ->where('status', 'aktif')
                ->pluck('id');
            $missing = [];
        } else {
            $nims = collect(preg_split('/[\s,;]+/', (string) $validated['nims']))->filter()->unique()->values();
            $found = Student::query()->whereIn('nim', $nims)->pluck('id', 'nim');
            $students = $found->values();
            $missing = $nims->diff($found->keys())->values()->all();
        }

        $result = $class->students()->syncWithoutDetaching($students->all());
        $added = count($result['attached']);

        AuditLogger::log('enrolled', 'master', $class, "Menambahkan {$added} mahasiswa ke kelas");

        $message = "{$added} mahasiswa ditambahkan ke kelas.";
        if ($missing !== []) {
            $message .= ' NIM tidak ditemukan: '.implode(', ', array_slice($missing, 0, 10)).(count($missing) > 10 ? '…' : '');
        }
        $this->toast($message, $missing === [] ? 'success' : 'warning');

        return back();
    }

    public function unenroll(Request $request, CourseClass $class, Student $student): RedirectResponse
    {
        $this->authorizeStudyProgram($request, $class->course->study_program_id);

        $class->students()->detach($student->id);
        AuditLogger::log('unenrolled', 'master', $class, "Mengeluarkan {$student->nim} dari kelas");
        $this->toast("{$student->name} dikeluarkan dari kelas.");

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'academic_period_id' => ['required', 'exists:academic_periods,id'],
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('course_classes')
                    ->where('course_id', $request->integer('course_id'))
                    ->where('academic_period_id', $request->integer('academic_period_id')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'lecturer_ids' => ['array'],
            'lecturer_ids.*' => ['integer', 'exists:lecturers,id'],
        ], ['code.unique' => 'Kode kelas sudah dipakai untuk mata kuliah ini pada periode tersebut.']);

        $this->authorizeStudyProgram($request, Course::query()->findOrFail($validated['course_id'])->study_program_id);

        return $validated;
    }
}
