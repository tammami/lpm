<?php

namespace App\Http\Controllers\Achievements;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementStatus;
use App\Http\Controllers\Controller;
use App\Models\StudentAchievement;
use App\Notifications\AchievementReviewedNotification;
use App\Services\Achievements\Achievements;
use App\Services\AuditLogger;
use App\Support\Options;
use App\Support\Spreadsheet;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data prestasi mahasiswa dalam cakupan pengguna: verifikasi oleh bidang kemahasiswaan, rekap, dan ekspor format LKPS.
 */
class AchievementController extends Controller
{
    private const FILTERS = ['status', 'category', 'level', 'study_program_id', 'year'];

    public function __construct(private Achievements $achievements) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Verifikator langsung melihat antrean yang menunggu bila belum memilih status ("any" = semua status).
        if (! $request->has('status') && $user->can('achievements.verify') && StudentAchievement::query()->visibleTo($user)->where('status', AchievementStatus::Pending)->exists()) {
            $request->merge(['status' => AchievementStatus::Pending->value]);
        }

        $achievements = $this->query($request)
            ->paginate()
            ->through(fn (StudentAchievement $achievement): array => $this->achievements->payload($achievement));

        $base = StudentAchievement::query()->visibleTo($user);
        $statusCounts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $levelCounts = (clone $base)->where('status', AchievementStatus::Verified)->selectRaw('level, count(*) as total')->groupBy('level')->pluck('total', 'level');

        return Inertia::render('achievements/index', [
            'achievements' => $achievements,
            'filters' => $this->filters($request, self::FILTERS),
            'studyPrograms' => Options::studyPrograms($user),
            'years' => (clone $base)->selectRaw('distinct year(achieved_on) as year')->orderByDesc('year')->pluck('year')
                ->map(fn ($year): array => ['value' => (string) $year, 'label' => (string) $year])->all(),
            'statusCounts' => collect(AchievementStatus::cases())->mapWithKeys(fn (AchievementStatus $status): array => [$status->value => (int) ($statusCounts[$status->value] ?? 0)]),
            'levelCounts' => collect(AchievementLevel::cases())->map(fn (AchievementLevel $level): array => [
                'value' => $level->value, 'label' => $level->label(), 'total' => (int) ($levelCounts[$level->value] ?? 0),
            ])->all(),
            ...$this->achievements->formOptions(),
            'canVerify' => $user->can('achievements.verify'),
        ]);
    }

    public function verify(Request $request, StudentAchievement $achievement): RedirectResponse
    {
        $this->authorizeAchievement($request, $achievement);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(array_map(fn (AchievementStatus $status): string => $status->value, AchievementStatus::decisions()))],
            'note' => ['nullable', Rule::requiredIf(fn (): bool => $request->input('decision') !== AchievementStatus::Verified->value), 'string', 'max:1000'],
        ], ['note.required' => 'Tuliskan catatan untuk mahasiswa (apa yang perlu diperbaiki atau alasan penolakan).']);

        $status = AchievementStatus::from($validated['decision']);

        $achievement->update([
            'status' => $status,
            'verification_note' => $validated['note'] ?? null,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        if (($owner = $achievement->student()->with('user')->first()?->user) && $owner->isNot($request->user())) {
            $owner->notify(new AchievementReviewedNotification($achievement));
        }

        $this->toast(match ($status) {
            AchievementStatus::Verified => 'Prestasi diverifikasi.',
            AchievementStatus::Revision => 'Dikembalikan ke mahasiswa untuk diperbaiki.',
            default => 'Prestasi ditolak.',
        });

        return back();
    }

    /**
     * Sertifikat dapat dibuka pemilik atau pengguna yang berhak melihat prestasi dalam cakupannya.
     */
    public function certificate(Request $request, StudentAchievement $achievement): StreamedResponse
    {
        $user = $request->user();
        $isOwner = $user->student?->id === $achievement->student_id;

        abort_unless($isOwner || ($user->can('achievements.view') && StudentAchievement::query()->visibleTo($user)->whereKey($achievement->id)->exists()), 403);
        abort_unless($achievement->certificate_path && Storage::disk('local')->exists($achievement->certificate_path), 404);

        $extension = pathinfo($achievement->certificate_path, PATHINFO_EXTENSION);
        $name = $achievement->certificate_name ?: "sertifikat-{$achievement->id}.{$extension}";

        return Storage::disk('local')->response($achievement->certificate_path, $name, [], 'inline');
    }

    /**
     * Ekspor format tabel prestasi mahasiswa LKPS (akademik & nonakademik terpisah). Hanya prestasi terverifikasi,
     * kecuali pengguna memilih status lain lewat filter.
     */
    public function export(Request $request): StreamedResponse
    {
        AuditLogger::log('exported', 'achievements', null, 'Ekspor data prestasi mahasiswa');

        $items = $this->query($request, defaultStatus: AchievementStatus::Verified->value)->builder()->reorder()->orderBy('achieved_on')->orderBy('id')->get();
        $headers = ['No', 'Nama Kegiatan', 'Waktu Perolehan (Tahun)', 'Tingkat', 'Prestasi yang Dicapai', 'Nama Mahasiswa', 'NIM', 'Program Studi', 'Penyelenggara', 'Tanggal', 'Individu/Tim', 'Tingkat (rinci)', 'Status'];
        $widths = [6, 44, 14, 16, 24, 30, 16, 30, 32, 13, 14, 22, 20];

        $sheet = fn (AchievementCategory $category): array => [
            'title' => 'Prestasi '.$category->label(),
            'headers' => $headers,
            'widths' => $widths,
            'rows' => $items->filter(fn (StudentAchievement $item): bool => $item->category === $category)->values()
                ->map(fn (StudentAchievement $item, int $index): array => [
                    $index + 1,
                    $item->title,
                    (int) $item->achieved_on->format('Y'),
                    $item->level->lkpsLabel(),
                    $item->achievement,
                    $item->student?->name,
                    $item->student?->nim,
                    $item->student?->studyProgram?->full_name,
                    $item->organizer,
                    $item->achieved_on->format('d/m/Y'),
                    StudentAchievement::PARTICIPATIONS[$item->participation] ?? $item->participation,
                    $item->level->label(),
                    $item->status->label(),
                ])->all(),
        ];

        return Spreadsheet::downloadSheets('prestasi-mahasiswa-'.now()->format('Ymd').'.xlsx', [
            $sheet(AchievementCategory::Academic),
            $sheet(AchievementCategory::NonAcademic),
        ]);
    }

    /**
     * @return TableQuery<StudentAchievement>
     */
    private function query(Request $request, ?string $defaultStatus = null): TableQuery
    {
        $status = $request->input('status', $defaultStatus);

        $query = StudentAchievement::query()
            ->with(['student.studyProgram:id,name,degree', 'verifier:id,name'])
            ->visibleTo($request->user())
            ->when($this->filled($request, 'study_program_id'), fn (Builder $q) => $q->whereHas('student', fn (Builder $student) => $student->where('study_program_id', $request->integer('study_program_id'))))
            ->when($this->filled($request, 'year'), fn (Builder $q) => $q->whereYear('achieved_on', $request->integer('year')))
            ->when(! in_array($status, [null, '', 'all', 'any'], true), fn (Builder $q) => $q->where('status', $status));

        return TableQuery::for($query, $request)
            ->search(['title', 'achievement', 'organizer', 'student.name', 'student.nim'])
            ->filter(['category' => 'category', 'level' => 'level'])
            ->sort(['achieved_on', 'title', 'updated_at'], '-achieved_on');
    }

    private function filled(Request $request, string $key): bool
    {
        return $request->filled($key) && $request->input($key) !== 'all';
    }

    private function authorizeAchievement(Request $request, StudentAchievement $achievement): void
    {
        abort_unless(StudentAchievement::query()->visibleTo($request->user())->whereKey($achievement->id)->exists(), 403, 'Data ini berada di luar cakupan akses Anda.');
    }
}
