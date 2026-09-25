<?php

use App\Enums\ActionPlanStatus;
use App\Enums\UserRole;
use App\Models\AccreditationBody;
use App\Models\AccreditationInstrument;
use App\Models\AccreditationPeriod;
use App\Models\Recommendation;
use App\Models\StudyProgram;
use App\Notifications\AccreditationDeadlineNotification;
use App\Notifications\EvidenceExpiringNotification;
use App\Notifications\ImprovementNotification;
use App\Services\Evidence\EvidenceService;
use Illuminate\Support\Facades\Notification;

it('reminds PICs of overdue plans on the reminder cadence and owners of expiring evidence', function () {
    Notification::fake();
    $pic = userWithRole(UserRole::AdminProdi);
    $recommendation = Recommendation::query()->create([
        'code' => 'REK-T-1', 'title' => 'Uji', 'description' => 'Uji', 'origin' => 'manual', 'priority' => 'high', 'status' => 'in_progress',
    ]);
    $late = fn (int $days) => $recommendation->actionPlans()->create([
        'title' => "Terlambat {$days} hari", 'pic_user_id' => $pic->id, 'due_date' => now()->subDays($days)->toDateString(), 'status' => ActionPlanStatus::InProgress,
    ]);
    $late(1);
    $late(8);
    $late(3);

    $owner = userWithRole(UserRole::AdminLpm);
    app(EvidenceService::class)->create(['title' => 'SK Tim', 'valid_until' => now()->addDays(7)->toDateString()], $owner, url: 'https://example.test/sk');
    app(EvidenceService::class)->create(['title' => 'SK Lain', 'valid_until' => now()->addDays(8)->toDateString()], $owner, url: 'https://example.test/sk2');

    $this->artisan('quality:remind')->assertSuccessful();

    Notification::assertSentToTimes($pic, ImprovementNotification::class, 2);
    Notification::assertSentTo($owner, EvidenceExpiringNotification::class, fn ($n) => $n->daysLeft === 7);
    Notification::assertSentToTimes($owner, EvidenceExpiringNotification::class, 1);
});

it('reminds the period PIC ahead of the accreditation submission deadline', function () {
    Notification::fake();
    $pic = userWithRole(UserRole::AdminProdi);
    $instrument = AccreditationInstrument::query()->create(['accreditation_body_id' => AccreditationBody::factory()->create()->id, 'code' => 'LAM-R', 'name' => 'Uji']);
    $version = $instrument->versions()->create(['version' => '1.0', 'status' => 'published']);
    $make = fn (int $days, string $code) => AccreditationPeriod::query()->create([
        'study_program_id' => StudyProgram::factory()->create()->id, 'instrument_version_id' => $version->id,
        'code' => $code, 'name' => $code, 'pic_user_id' => $pic->id, 'submission_deadline' => now()->addDays($days)->toDateString(),
    ]);
    $make(30, 'AKR-A');
    $make(29, 'AKR-B');

    $this->artisan('quality:remind')->assertSuccessful();

    Notification::assertSentToTimes($pic, AccreditationDeadlineNotification::class, 1);
});
