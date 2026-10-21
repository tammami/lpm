<?php

use App\Enums\UserRole;
use App\Models\Instrument;
use App\Models\InstrumentVersion;
use App\Models\Survey;

it('deletes an unused published version that was a mistake', function () {
    $instrument = Instrument::factory()->create();
    $published = InstrumentVersion::factory()->published()->withLikertQuestions()->for($instrument)->create();
    $draft = InstrumentVersion::factory()->for($instrument)->create(['version' => '1.1', 'version_number' => 2, 'parent_version_id' => $published->id]);

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('instrument-versions.show', $published))
        ->assertInertia(fn ($page) => $page->where('version.deletable', true));

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->delete(route('instrument-versions.destroy', $published))
        ->assertRedirect(route('instruments.show', $instrument));

    expect($published->fresh())->toBeNull()
        ->and($draft->fresh()->parent_version_id)->toBeNull();
});

it('refuses to delete a version already used by a monev activity', function () {
    $instrument = Instrument::factory()->create();
    $used = InstrumentVersion::factory()->published()->for($instrument)->create();
    InstrumentVersion::factory()->for($instrument)->create(['version' => '1.1', 'version_number' => 2]);
    Survey::factory()->create(['instrument_version_id' => $used->id]);

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->get(route('instrument-versions.show', $used))
        ->assertInertia(fn ($page) => $page->where('version.deletable', false));

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->from(route('instrument-versions.show', $used))
        ->delete(route('instrument-versions.destroy', $used))
        ->assertRedirect(route('instrument-versions.show', $used));

    expect($used->fresh())->not->toBeNull();
});
