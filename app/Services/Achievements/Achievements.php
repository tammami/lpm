<?php

namespace App\Services\Achievements;

use App\Enums\AchievementCategory;
use App\Enums\AchievementLevel;
use App\Enums\AchievementStatus;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Support\UploadLimit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validasi, penyimpanan sertifikat, dan payload prestasi mahasiswa — dipakai halaman mahasiswa dan kemahasiswaan.
 */
class Achievements
{
    public const CERTIFICATE_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    /** Batas ukuran sertifikat (MB) sebelum dibatasi lagi oleh konfigurasi server. */
    public const CERTIFICATE_MAX_MB = 5;

    /**
     * @return array<string, mixed>
     */
    public function validate(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(AchievementCategory::class)],
            'level' => ['required', Rule::enum(AchievementLevel::class)],
            'achievement' => ['required', 'string', 'max:100'],
            'participation' => ['required', Rule::in(array_keys(StudentAchievement::PARTICIPATIONS))],
            'organizer' => ['required', 'string', 'max:255'],
            'achieved_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
            'certificate' => ['nullable', 'file', 'mimes:'.implode(',', self::CERTIFICATE_EXTENSIONS), 'max:'.UploadLimit::kilobytes(self::CERTIFICATE_MAX_MB)],
            'evidence_url' => ['nullable', 'url:http,https', 'max:1000'],
        ], [
            'title.required' => 'Isi nama kegiatan atau lomba.',
            'achievement.required' => 'Isi prestasi yang dicapai, mis. Juara 1.',
            'organizer.required' => 'Isi nama penyelenggara.',
            'achieved_on.required' => 'Isi tanggal prestasi diperoleh.',
            'achieved_on.before_or_equal' => 'Tanggal prestasi tidak boleh di masa depan.',
            'certificate.mimes' => 'Sertifikat harus berupa PDF, JPG, atau PNG.',
            'certificate.max' => 'Ukuran sertifikat melebihi batas unggah.',
            'evidence_url.url' => 'Tautan bukti harus berupa alamat lengkap, diawali https://',
        ]);
    }

    /**
     * Simpan data isian mahasiswa. Setiap perubahan mengembalikan status ke "Menunggu verifikasi".
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?StudentAchievement $achievement, Student $student, array $data, ?UploadedFile $certificate): StudentAchievement
    {
        $hasCertificate = $certificate !== null || ($achievement?->certificate_path !== null);

        if (! $hasCertificate && empty($data['evidence_url'])) {
            throw ValidationException::withMessages(['certificate' => 'Unggah sertifikat/piagam atau isi tautan bukti.']);
        }

        $oldPath = $achievement?->certificate_path;
        $attributes = collect($data)->except('certificate')->all();

        if ($certificate) {
            $extension = strtolower($certificate->getClientOriginalExtension() ?: $certificate->extension());
            $attributes['certificate_path'] = $certificate->storeAs("achievements/{$student->id}", Str::random(24).".{$extension}", 'local');
            $attributes['certificate_name'] = Str::limit($certificate->getClientOriginalName(), 250, '');
        }

        $saved = DB::transaction(function () use ($achievement, $student, $attributes): StudentAchievement {
            $attributes += ['status' => AchievementStatus::Pending, 'verified_by' => null, 'verified_at' => null];

            if ($achievement) {
                $achievement->update($attributes);

                return $achievement;
            }

            return StudentAchievement::query()->create([...$attributes, 'student_id' => $student->id]);
        });

        if ($certificate && $oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $saved;
    }

    public function delete(StudentAchievement $achievement): void
    {
        $path = $achievement->certificate_path;
        $achievement->delete();

        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(StudentAchievement $achievement): array
    {
        $student = $achievement->student;

        return [
            'id' => $achievement->id,
            'title' => $achievement->title,
            'category' => $achievement->category->value,
            'category_label' => $achievement->category->label(),
            'level' => $achievement->level->value,
            'level_label' => $achievement->level->label(),
            'achievement' => $achievement->achievement,
            'participation' => $achievement->participation,
            'participation_label' => StudentAchievement::PARTICIPATIONS[$achievement->participation] ?? $achievement->participation,
            'organizer' => $achievement->organizer,
            'achieved_on' => $achievement->achieved_on->toDateString(),
            'description' => $achievement->description,
            'certificate_name' => $achievement->certificate_name,
            'certificate_url' => $achievement->certificate_path ? route('achievements.certificate', $achievement) : null,
            'evidence_url' => $achievement->evidence_url,
            'status' => $achievement->status->value,
            'status_label' => $achievement->status->label(),
            'verification_note' => $achievement->verification_note,
            'verifier' => $achievement->relationLoaded('verifier') ? $achievement->verifier?->name : null,
            'verified_at' => $achievement->verified_at?->toIso8601String(),
            'updated_at' => $achievement->updated_at?->toIso8601String(),
            'student' => $student ? [
                'id' => $student->id,
                'name' => $student->name,
                'nim' => $student->nim,
                'study_program' => $student->studyProgram?->full_name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(): array
    {
        return [
            'categories' => AchievementCategory::options(),
            'levels' => AchievementLevel::options(),
            'statuses' => AchievementStatus::options(),
            'participations' => collect(StudentAchievement::PARTICIPATIONS)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
            'achievementSuggestions' => StudentAchievement::ACHIEVEMENT_SUGGESTIONS,
            'maxUploadMb' => UploadLimit::megabytes(self::CERTIFICATE_MAX_MB),
        ];
    }
}
