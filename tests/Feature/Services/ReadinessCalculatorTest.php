<?php

use App\Enums\UserRole;
use App\Models\AccreditationBody;
use App\Models\AccreditationInstrument;
use App\Models\AccreditationPeriod;
use App\Models\StudyProgram;
use App\Services\Accreditation\ReadinessCalculator;
use App\Services\Evidence\EvidenceService;

it('computes readiness per period from evidence status, context and self assessment', function () {
    $user = userWithRole(UserRole::AdminLpm);
    $service = app(EvidenceService::class);
    $program = StudyProgram::factory()->create();

    $instrument = AccreditationInstrument::query()->create(['accreditation_body_id' => AccreditationBody::factory()->create()->id, 'code' => 'LAM-T', 'name' => 'Uji']);
    $version = $instrument->versions()->create(['version' => '1.0', 'status' => 'published', 'scale_max' => 4]);
    $c1 = $version->criteria()->create(['code' => 'C1', 'title' => 'Tata kelola', 'weight' => 60, 'sort_order' => 1]);
    $c11 = $version->criteria()->create(['code' => 'C1.1', 'title' => 'Sub', 'parent_id' => $c1->id, 'sort_order' => 2]);
    $c2 = $version->criteria()->create(['code' => 'C2', 'title' => 'Pendidikan', 'weight' => 40, 'sort_order' => 3]);
    $indicator = fn ($criterion, string $code, bool $essential = false) => $criterion->indicators()->create([
        'instrument_version_id' => $version->id, 'code' => $code, 'statement' => "Indikator {$code}", 'is_essential' => $essential,
    ]);
    $i1 = $indicator($c1, 'I1', true);
    $i2 = $indicator($c11, 'I2');
    $i3 = $indicator($c2, 'I3');

    $period = AccreditationPeriod::query()->create(['study_program_id' => $program->id, 'instrument_version_id' => $version->id, 'code' => 'AKR-1', 'name' => 'Reakreditasi 2027']);
    $other = AccreditationPeriod::query()->create(['study_program_id' => $program->id, 'instrument_version_id' => $version->id, 'code' => 'AKR-0', 'name' => 'Siklus lama']);

    $evidence = function (string $title, bool $verified, ?string $validUntil = null) use ($service, $user) {
        $item = $service->create(['title' => $title, 'valid_until' => $validUntil], $user, url: 'https://example.test/'.str($title)->slug());
        if ($verified) {
            $service->verify($item, $user, true, null);
        }

        return $item;
    };

    $service->map($evidence('SK Renstra', true), 'accreditation_indicator', $i1->id, $user, $period->id);
    $service->map($evidence('Draft RPS', false), 'accreditation_indicator', $i2->id, $user, $period->id);
    $service->map($evidence('Laporan lama', true), 'accreditation_indicator', $i3->id, $user, $other->id);
    $service->map($evidence('Sertifikat kedaluwarsa', true, now()->subDay()->toDateString()), 'accreditation_indicator', $i3->id, $user, $period->id);

    $other->update(['status' => 'decided']);

    foreach ([[$i1, 4], [$i2, 2], [$i3, 2]] as [$item, $score]) {
        $period->assessments()->create(['indicator_id' => $item->id, 'self_score' => $score]);
    }

    $calculator = ReadinessCalculator::for($period);
    $summary = $calculator->summary();

    expect($calculator->states()[$i1->id]['status'])->toBe('ready')
        ->and($calculator->states()[$i2->id]['status'])->toBe('partial')
        ->and($calculator->states()[$i3->id]['status'])->toBe('gap')
        ->and($summary)->toMatchArray(['total' => 3, 'ready' => 1, 'partial' => 1, 'gap' => 1, 'readiness' => 50.0, 'assessed' => 3, 'coverage' => 100.0, 'essential_unmet' => 0])
        ->and($summary['estimated_score'])->toBe(260.0)
        ->and($summary['estimated_grade'])->toBe('Baik');

    $criteria = collect($calculator->byCriterion())->keyBy('code');
    expect($criteria->keys()->all())->toBe(['C1', 'C2'])
        ->and($criteria['C1']['total'])->toBe(2)
        ->and($criteria['C1']['self_average'])->toBe(3.0)
        ->and($criteria['C2']['readiness'])->toBe(0.0);

    expect(collect($calculator->gaps())->pluck('code')->all())->toBe(['I2', 'I3']);
});
