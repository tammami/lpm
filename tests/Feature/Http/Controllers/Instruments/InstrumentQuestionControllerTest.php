<?php

use App\Enums\InstrumentVersionStatus;
use App\Enums\UserRole;
use App\Models\InstrumentVersion;

it('rejects editing a question of a published version', function () {
    $version = InstrumentVersion::factory()->published()->withLikertQuestions()->create();
    $question = $version->questions()->first();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->from(route('instrument-versions.show', $version))
        ->put(route('instrument-questions.update', $question), [
            'instrument_section_id' => $question->instrument_section_id,
            'label' => 'Diubah',
            'type' => 'likert',
            'weight' => 1,
        ])
        ->assertRedirect(route('instrument-versions.show', $version));

    expect($question->fresh()->label)->toBe('Pernyataan 1');
});

it('creates a likert question with default options when none are given', function () {
    $version = InstrumentVersion::factory()->withLikertQuestions([1])->create();
    $section = $version->sections()->first();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('instrument-questions.store', $section), [
            'instrument_section_id' => $section->id,
            'label' => 'Dosen memberikan contoh kontekstual.',
            'type' => 'likert',
            'weight' => 2,
            'is_scored' => true,
        ])
        ->assertRedirect();

    $question = $version->questions()->where('label', 'Dosen memberikan contoh kontekstual.')->firstOrFail();

    expect($question->code)->toBe('A3')
        ->and($question->options()->pluck('score')->all())->toBe([1.0, 2.0, 3.0, 4.0]);
});

it('forbids an admin prodi from approving an instrument under review', function () {
    $version = InstrumentVersion::factory()->withLikertQuestions()->create(['status' => InstrumentVersionStatus::Review]);

    $this->actingAs(userWithRole(UserRole::AdminProdi))
        ->post(route('instrument-versions.transition', $version), ['status' => 'approved'])
        ->assertForbidden();

    expect($version->fresh()->status)->toBe(InstrumentVersionStatus::Review);
});

it('lets an LPM admin approve and publish a reviewed version', function () {
    $version = InstrumentVersion::factory()->withLikertQuestions()->create(['status' => InstrumentVersionStatus::Review]);
    $admin = userWithRole(UserRole::AdminLpm);

    $this->actingAs($admin)->post(route('instrument-versions.transition', $version), ['status' => 'approved'])->assertRedirect();
    $this->actingAs($admin)->post(route('instrument-versions.transition', $version), ['status' => 'published'])->assertRedirect();

    expect($version->fresh())
        ->status->toBe(InstrumentVersionStatus::Published)
        ->published_by->toBe($admin->id);
});
