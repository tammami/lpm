<?php

use App\Enums\AchievementStatus;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentAchievement;
use App\Models\StudyProgram;
use App\Notifications\AchievementReviewedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function achievementPayload(array $overrides = []): array
{
    return [
        'title' => 'Lomba Karya Tulis Ilmiah Nasional',
        'category' => 'academic',
        'level' => 'national',
        'achievement' => 'Juara 2',
        'participation' => 'team',
        'organizer' => 'Kementerian Agama RI',
        'achieved_on' => now()->subMonth()->toDateString(),
        ...$overrides,
    ];
}

beforeEach(function () {
    Storage::fake('local');
    $this->programA = StudyProgram::factory()->create();
    $this->programB = StudyProgram::factory()->create();
    $this->studentUser = userWithRole(UserRole::Mahasiswa);
    $this->student = Student::factory()->for($this->programA)->create(['user_id' => $this->studentUser->id]);
    $this->kemahasiswaan = userWithRole(UserRole::Kemahasiswaan);
});

it('lets a student record an achievement with a certificate', function () {
    $this->actingAs($this->studentUser)->post(route('achievements.mine.store'), achievementPayload([
        'certificate' => UploadedFile::fake()->create('sertifikat.pdf', 200, 'application/pdf'),
    ]))->assertSessionHasNoErrors();

    $achievement = StudentAchievement::query()->sole();

    expect($achievement->student_id)->toBe($this->student->id)
        ->and($achievement->status)->toBe(AchievementStatus::Pending)
        ->and($achievement->certificate_name)->toBe('sertifikat.pdf');
    Storage::disk('local')->assertExists($achievement->certificate_path);

    $this->actingAs($this->studentUser)->get(route('achievements.certificate', $achievement))->assertOk();
});

it('requires a certificate or an evidence link', function () {
    $this->actingAs($this->studentUser)->post(route('achievements.mine.store'), achievementPayload())
        ->assertSessionHasErrors('certificate');

    $this->actingAs($this->studentUser)->post(route('achievements.mine.store'), achievementPayload(['evidence_url' => 'https://contoh.ac.id/pengumuman']))
        ->assertSessionHasNoErrors();
});

it('rejects evidence links that are not http or https', function () {
    $this->actingAs($this->studentUser)->post(route('achievements.mine.store'), achievementPayload(['evidence_url' => 'javascript:alert(1)']))
        ->assertSessionHasErrors('evidence_url');

    expect(StudentAchievement::query()->count())->toBe(0);
});

it('sends an edited achievement back to the verification queue and replaces the old certificate', function () {
    $old = UploadedFile::fake()->create('lama.pdf', 50, 'application/pdf')->storeAs("achievements/{$this->student->id}", 'lama.pdf', 'local');
    $achievement = StudentAchievement::factory()->for($this->student)->create([
        'status' => AchievementStatus::Revision, 'verification_note' => 'Sertifikat buram.', 'certificate_path' => $old, 'certificate_name' => 'lama.pdf',
    ]);

    $this->actingAs($this->studentUser)->post(route('achievements.mine.update', $achievement), [
        ...achievementPayload(),
        '_method' => 'put',
        'certificate' => UploadedFile::fake()->create('baru.pdf', 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    expect($achievement->refresh()->status)->toBe(AchievementStatus::Pending)
        ->and($achievement->certificate_name)->toBe('baru.pdf');
    Storage::disk('local')->assertMissing($old);
});

it('stops students from changing or deleting a verified achievement, or touching someone else\'s', function () {
    $verified = StudentAchievement::factory()->for($this->student)->create(['status' => AchievementStatus::Verified]);
    $others = StudentAchievement::factory()->create();

    $this->actingAs($this->studentUser)->put(route('achievements.mine.update', $verified), achievementPayload(['evidence_url' => 'https://contoh.ac.id']))->assertRedirect();
    $this->actingAs($this->studentUser)->delete(route('achievements.mine.destroy', $verified))->assertRedirect();
    expect($verified->refresh()->title)->not->toBe('Lomba Karya Tulis Ilmiah Nasional')
        ->and(StudentAchievement::query()->whereKey($verified->id)->exists())->toBeTrue();

    $this->actingAs($this->studentUser)->delete(route('achievements.mine.destroy', $others))->assertForbidden();
    $this->actingAs($this->studentUser)->get(route('achievements.certificate', $others))->assertForbidden();
});

it('lets student affairs verify or return an achievement and notifies the student', function () {
    Notification::fake();
    $achievement = StudentAchievement::factory()->for($this->student)->create();

    $this->actingAs($this->kemahasiswaan)->post(route('achievements.verify', $achievement), ['decision' => 'revision'])
        ->assertSessionHasErrors('note');

    $this->actingAs($this->kemahasiswaan)->post(route('achievements.verify', $achievement), ['decision' => 'revision', 'note' => 'Lampirkan sertifikat bernama Anda.'])
        ->assertSessionHasNoErrors();
    expect($achievement->refresh()->status)->toBe(AchievementStatus::Revision);

    $this->actingAs($this->kemahasiswaan)->post(route('achievements.verify', $achievement), ['decision' => 'verified'])->assertSessionHasNoErrors();
    expect($achievement->refresh()->status)->toBe(AchievementStatus::Verified)
        ->and($achievement->verified_by)->toBe($this->kemahasiswaan->id);

    Notification::assertSentToTimes($this->studentUser, AchievementReviewedNotification::class, 2);
});

it('sends student affairs straight to the pending queue', function () {
    StudentAchievement::factory()->for($this->student)->create();
    StudentAchievement::factory()->for($this->student)->create(['status' => AchievementStatus::Verified]);

    $this->actingAs($this->kemahasiswaan)->get(route('home'))->assertRedirect(route('achievements.index'));

    $this->actingAs($this->kemahasiswaan)->get(route('achievements.index'))
        ->assertInertia(fn (Assert $page) => $page->component('achievements/index')
            ->where('filters.status', 'pending')
            ->has('achievements.data', 1)
            ->where('navCounts.pendingAchievements', 1));

    $this->actingAs($this->kemahasiswaan)->get(route('achievements.index', ['status' => 'any']))
        ->assertInertia(fn (Assert $page) => $page->has('achievements.data', 2));
});

it('lets LPM and study programs see the recap within their scope but not verify', function () {
    $mine = StudentAchievement::factory()->for($this->student)->create();
    StudentAchievement::factory()->for(Student::factory()->for($this->programB))->create();
    $prodi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->programA->id]);
    $lpm = userWithRole(UserRole::AdminLpm);

    $this->actingAs($prodi)->get(route('achievements.index'))->assertInertia(fn (Assert $page) => $page->has('achievements.data', 1)->where('canVerify', false));
    $this->actingAs($lpm)->get(route('achievements.index'))->assertInertia(fn (Assert $page) => $page->has('achievements.data', 2));
    $this->actingAs($lpm)->post(route('achievements.verify', $mine), ['decision' => 'verified'])->assertForbidden();
    $this->actingAs($this->studentUser)->get(route('achievements.index'))->assertForbidden();
});

it('exports only verified achievements in the LKPS layout split by category', function () {
    StudentAchievement::factory()->for($this->student)->create(['title' => 'Olimpiade Sains', 'status' => AchievementStatus::Verified, 'level' => 'regency']);
    StudentAchievement::factory()->for($this->student)->create(['title' => 'Belum diperiksa', 'status' => AchievementStatus::Pending]);
    StudentAchievement::factory()->for($this->student)->create(['title' => 'Kejuaraan Pencak Silat', 'category' => 'non_academic', 'status' => AchievementStatus::Verified]);

    $response = $this->actingAs($this->kemahasiswaan)->get(route('achievements.export'));
    $academic = downloadedRows($response, 'Prestasi Akademik');

    expect($academic)->toHaveCount(2)
        ->and($academic[1][1])->toBe('Olimpiade Sains')
        ->and($academic[1][3])->toBe('Lokal/Wilayah')
        ->and(downloadedRows($this->actingAs($this->kemahasiswaan)->get(route('achievements.export')), 'Prestasi Nonakademik')[1][1])->toBe('Kejuaraan Pencak Silat');
});
