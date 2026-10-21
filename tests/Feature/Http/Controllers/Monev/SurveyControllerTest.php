<?php

use App\Enums\MonevStage;
use App\Enums\SurveyStatus;
use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\Survey;

/**
 * @return array<string, mixed>
 */
function surveyPayload(array $overrides = []): array
{
    return [
        'title' => 'Monev Pembelajaran Awal Semester',
        'instrument_version_id' => InstrumentVersion::factory()->published()->create()->id,
        'mode' => 'teaching_evaluation',
        'stage' => 'awal',
        'respondent_type' => 'mahasiswa',
        'academic_period_id' => AcademicPeriod::factory()->create()->id,
        'is_anonymous' => true,
        'starts_at' => now()->toDateTimeString(),
        'ends_at' => now()->addWeek()->toDateTimeString(),
        ...$overrides,
    ];
}

it('stores the monev stage of a teaching evaluation', function () {
    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('surveys.store'), surveyPayload())
        ->assertSessionHasNoErrors();

    expect(Survey::query()->sole()->stage)->toBe(MonevStage::Awal);
});

it('requires a stage for teaching evaluations', function () {
    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('surveys.store'), surveyPayload(['stage' => null]))
        ->assertSessionHasErrors('stage');
});

it('drops the stage for general surveys', function () {
    $version = InstrumentVersion::factory()->published()->for(Instrument::factory()->state(['type' => 'survei']))->create();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('surveys.store'), surveyPayload(['mode' => 'general', 'instrument_version_id' => $version->id, 'academic_period_id' => null]))
        ->assertSessionHasNoErrors();

    expect(Survey::query()->sole()->stage)->toBeNull();
});

it('filters the activity list by stage', function () {
    Survey::factory()->create(['stage' => MonevStage::Awal, 'title' => 'Tahap awal']);
    Survey::factory()->create(['stage' => MonevStage::Akhir, 'title' => 'Tahap akhir']);

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('surveys.index', ['stage' => 'awal']))
        ->assertInertia(fn ($page) => $page->has('surveys.data', 1)->where('surveys.data.0.title', 'Tahap awal'));
});

it('closes and reopens an activity with the quick toggle', function () {
    $survey = Survey::factory()->create();
    $admin = userWithRole(UserRole::AdminLpm);

    $this->actingAs($admin)->post(route('surveys.transition', $survey), ['status' => 'closed']);
    expect($survey->fresh()->status)->toBe(SurveyStatus::Closed);

    $this->actingAs($admin)->post(route('surveys.transition', $survey), ['status' => 'active']);
    expect($survey->fresh()->status)->toBe(SurveyStatus::Active);
});

it('lets the stage be corrected after the activity is opened', function () {
    $survey = Survey::factory()->create(['stage' => MonevStage::Akhir]);

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->put(route('surveys.update', $survey), [
            'title' => $survey->title,
            'ends_at' => $survey->ends_at->toDateTimeString(),
            'stage' => 'awal',
        ])
        ->assertSessionHasNoErrors();

    expect($survey->fresh()->stage)->toBe(MonevStage::Awal);
});

it('keeps general surveys without a stage when edited after opening', function () {
    $survey = Survey::factory()->create(['mode' => 'general', 'stage' => null]);

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->put(route('surveys.update', $survey), ['title' => 'Survei layanan', 'ends_at' => $survey->ends_at->toDateTimeString(), 'stage' => null])
        ->assertSessionHasNoErrors();

    expect($survey->fresh()->stage)->toBeNull();
});
