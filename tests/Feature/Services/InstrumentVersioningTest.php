<?php

use App\Enums\InstrumentVersionStatus;
use App\Enums\UserRole;
use App\Models\InstrumentVersion;
use App\Services\InstrumentVersioning;
use Illuminate\Validation\ValidationException;

it('copies every section, question and option into a new draft without touching the source', function () {
    $source = InstrumentVersion::factory()->published()->withLikertQuestions([1, 2])->create();
    $originalLabels = $source->questions()->pluck('label')->all();

    $copy = app(InstrumentVersioning::class)->cloneVersion($source, userWithRole(UserRole::AdminLpm));
    $copy->questions()->first()->update(['label' => 'Diubah di versi baru']);

    expect($copy->version)->toBe('1.1')
        ->and($copy->status)->toBe(InstrumentVersionStatus::Draft)
        ->and($copy->questions()->count())->toBe(3)
        ->and($copy->questions()->first()->options()->count())->toBe(4)
        ->and($source->questions()->pluck('label')->all())->toBe($originalLabels);
});

it('bumps the major version number when requested', function () {
    $source = InstrumentVersion::factory()->published()->withLikertQuestions()->create(['version' => '1.3', 'version_number' => 4]);

    $copy = app(InstrumentVersioning::class)->cloneVersion($source, userWithRole(UserRole::AdminLpm), major: true);

    expect($copy->version)->toBe('2.0')->and($copy->version_number)->toBe(5);
});

it('refuses to submit a version whose option question has no options', function () {
    $version = InstrumentVersion::factory()->withLikertQuestions()->create();
    $version->questions()->first()->options()->delete();

    app(InstrumentVersioning::class)->transition($version, InstrumentVersionStatus::Review, userWithRole(UserRole::AdminLpm));
})->throws(ValidationException::class, 'Butir A1 membutuhkan minimal 2 opsi jawaban.');

it('refuses to skip workflow steps', function () {
    $version = InstrumentVersion::factory()->withLikertQuestions()->create();

    app(InstrumentVersioning::class)->transition($version, InstrumentVersionStatus::Published, userWithRole(UserRole::AdminLpm));
})->throws(ValidationException::class, 'Status Draf tidak dapat diubah menjadi Terbit.');
