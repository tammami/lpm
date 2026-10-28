<?php

use App\Enums\LpmActivityStatus;
use App\Enums\UserRole;
use App\Models\Evidence;
use App\Models\EvidenceMapping;
use App\Models\LpmActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function activityPayload(array $overrides = []): array
{
    return [
        'title' => 'Workshop penyusunan dokumen SPMI',
        'category' => 'training',
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'pic' => 'Sekretaris LPM',
        'location' => 'Aula kampus',
        'budget' => 2500000,
        'status' => 'planned',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->lpm = userWithRole(UserRole::AdminLpm);
});

it('lets LPM add an activity to the yearly timeline with a generated code', function () {
    $this->actingAs($this->lpm)->post(route('activities.store'), activityPayload())->assertSessionHasNoErrors();

    $activity = LpmActivity::query()->sole();

    expect($activity->code)->toBe('PK-'.now()->year.'-0001')
        ->and($activity->year)->toBe(now()->addDay()->year)
        ->and($activity->status)->toBe(LpmActivityStatus::Planned)
        ->and($activity->created_by)->toBe($this->lpm->id);

    $this->actingAs($this->lpm)->get(route('activities.index', ['year' => $activity->year]))
        ->assertInertia(fn (Assert $page) => $page->component('activities/index')
            ->has('activities', 1)
            ->where('stats.total', 1)
            ->where('activities.0.is_overdue', false));
});

it('rejects an end date before the start date', function () {
    $this->actingAs($this->lpm)
        ->post(route('activities.store'), activityPayload(['end_date' => now()->subDay()->toDateString()]))
        ->assertSessionHasErrors('end_date');

    expect(LpmActivity::query()->count())->toBe(0);
});

it('flags a past activity that is still planned as overdue and filters by it', function () {
    LpmActivity::factory()->create(['start_date' => now()->subDays(10), 'end_date' => now()->subDays(5), 'year' => now()->subDays(10)->year]);
    LpmActivity::factory()->create(['start_date' => now()->addDays(5), 'end_date' => now()->addDays(6), 'year' => now()->addDays(5)->year]);

    $this->actingAs($this->lpm)->get(route('activities.index', ['year' => now()->subDays(10)->year, 'status' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page->has('activities', 1)->where('activities.0.is_overdue', true)->where('stats.overdue', 1));
});

it('marks an activity done with a realization note', function () {
    $activity = LpmActivity::factory()->create();

    $this->actingAs($this->lpm)->post(route('activities.status', $activity), ['status' => 'done', 'realization_note' => 'Diikuti 35 peserta.'])
        ->assertSessionHasNoErrors();

    expect($activity->refresh()->status)->toBe(LpmActivityStatus::Done)
        ->and($activity->realization_note)->toBe('Diikuti 35 peserta.');
});

it('stores program evidence in the evidence repository and keeps it when the activity is deleted', function () {
    Storage::fake('local');
    $activity = LpmActivity::factory()->create();

    $this->actingAs($this->lpm)->post(route('evidence.store'), [
        'title' => 'Daftar hadir workshop',
        'file' => UploadedFile::fake()->create('daftar-hadir.pdf', 80, 'application/pdf'),
        'map_type' => 'lpm_activity',
        'map_id' => $activity->id,
    ])->assertSessionHasNoErrors();

    expect($activity->evidenceMappings()->count())->toBe(1);

    $this->actingAs($this->lpm)->get(route('activity-evidence.index'))
        ->assertInertia(fn (Assert $page) => $page->component('activities/evidence')
            ->where('stats.documents', 1)
            ->where('activities.0.evidence.0.title', 'Daftar hadir workshop'));

    $this->actingAs($this->lpm)->get(route('activities.show', $activity))
        ->assertInertia(fn (Assert $page) => $page->component('activities/show')->has('activity.evidence', 1));

    $this->actingAs($this->lpm)->delete(route('activities.destroy', $activity))->assertRedirect();

    expect(LpmActivity::query()->count())->toBe(0)
        ->and(Evidence::query()->count())->toBe(1)
        ->and(EvidenceMapping::query()->count())->toBe(0);
});

it('only lets program managers attach evidence to an activity', function () {
    $activity = LpmActivity::factory()->create();
    $prodi = userWithRole(UserRole::AdminProdi);
    $evidence = Evidence::query()->create(['code' => 'DOK-1', 'title' => 'Dokumen', 'owner_user_id' => $prodi->id, 'confidentiality' => 'public', 'status' => 'pending']);

    $this->actingAs($prodi)->post(route('evidence.map'), ['evidence_id' => $evidence->id, 'mappable_type' => 'lpm_activity', 'mappable_id' => $activity->id])
        ->assertForbidden();
});

it('lets leadership view the timeline but not change it, and hides it from lecturers', function () {
    $activity = LpmActivity::factory()->create();
    $pimpinan = userWithRole(UserRole::Pimpinan);

    $this->actingAs($pimpinan)->get(route('activities.index'))->assertOk();
    $this->actingAs($pimpinan)->get(route('activities.show', $activity))->assertInertia(fn (Assert $page) => $page->where('canManage', false));
    $this->actingAs($pimpinan)->post(route('activities.store'), activityPayload())->assertForbidden();
    $this->actingAs(userWithRole(UserRole::Dosen))->get(route('activities.index'))->assertForbidden();
});

it('exports the yearly program to Excel', function () {
    LpmActivity::factory()->create(['title' => 'Rapat kerja tahunan', 'start_date' => now()->year.'-01-15', 'end_date' => now()->year.'-01-15', 'year' => now()->year]);

    $rows = downloadedRows($this->actingAs($this->lpm)->get(route('activities.export')));

    expect(collect($rows)->flatten()->contains('Rapat kerja tahunan'))->toBeTrue();
});
