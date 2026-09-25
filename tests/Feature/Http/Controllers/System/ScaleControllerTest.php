<?php

use App\Enums\UserRole;
use App\Models\ClassificationScheme;

it('rejects classification ranges with a gap', function () {
    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('settings.schemes.store'), [
            'name' => 'Bercelah',
            'scale_min' => 0,
            'scale_max' => 4,
            'classes' => [
                ['label' => 'Rendah', 'min_score' => 0, 'max_score' => 1.5, 'color' => 'danger'],
                ['label' => 'Tinggi', 'min_score' => 2, 'max_score' => 4, 'color' => 'success'],
            ],
        ])
        ->assertSessionHasErrors(['classes' => 'Rentang "Rendah" dan "Tinggi" harus bersambung tanpa celah/tumpang tindih.']);

    expect(ClassificationScheme::query()->count())->toBe(0);
});

it('classifies boundary scores into the lower class after the first', function () {
    $this->actingAs(userWithRole(UserRole::AdminLpm))->post(route('settings.schemes.store'), [
        'name' => 'Skala 4',
        'scale_min' => 0,
        'scale_max' => 4,
        'is_default' => true,
        'classes' => [
            ['label' => 'Rendah', 'min_score' => 0, 'max_score' => 2, 'color' => 'danger'],
            ['label' => 'Baik', 'min_score' => 2, 'max_score' => 3, 'color' => 'info'],
            ['label' => 'Sangat Baik', 'min_score' => 3, 'max_score' => 4, 'color' => 'success'],
        ],
    ]);

    $scheme = ClassificationScheme::query()->with('classifications')->sole();

    expect($scheme->classify(0.0)->label)->toBe('Rendah')
        ->and($scheme->classify(2.0)->label)->toBe('Rendah')
        ->and($scheme->classify(2.01)->label)->toBe('Baik')
        ->and($scheme->classify(3.0)->label)->toBe('Baik')
        ->and($scheme->classify(4.0)->label)->toBe('Sangat Baik');
});
