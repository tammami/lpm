<?php

use App\Enums\AccreditationPeriodStatus;
use App\Enums\UserRole;
use App\Models\AccreditationBody;
use App\Models\AccreditationInstrument;
use App\Models\AccreditationInstrumentVersion;
use App\Models\AccreditationPeriod;
use App\Models\Lecturer;
use App\Models\StudyProgram;
use App\Services\Evidence\EvidenceService;
use App\Services\Improvement\RecommendationEngine;
use Database\Seeders\InstitutionAccreditationSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->lpm = userWithRole(UserRole::AdminLpm);
    $this->body = AccreditationBody::factory()->create(['code' => 'LAMDIK']);
    $this->program = StudyProgram::factory()->create(['accreditation_body_id' => $this->body->id]);
});

function publishedVersion(): AccreditationInstrumentVersion
{
    $instrument = AccreditationInstrument::query()->create(['accreditation_body_id' => test()->body->id, 'code' => 'IAPS-T', 'name' => 'Instrumen uji']);
    $version = $instrument->versions()->create(['version' => '1.0', 'status' => 'published', 'scale_max' => 4]);
    $criterion = $version->criteria()->create(['code' => 'C1', 'title' => 'VMTS', 'weight' => 100, 'sort_order' => 1]);
    $criterion->indicators()->create(['instrument_version_id' => $version->id, 'code' => 'C1.1', 'statement' => 'Visi keilmuan', 'is_essential' => true]);

    return $version;
}

it('builds, publishes, locks and versions an accreditation instrument', function () {
    $this->actingAs($this->lpm)->post(route('accreditation.instruments.store'), [
        'accreditation_body_id' => $this->body->id, 'code' => 'IAPS-LAMDIK', 'name' => 'IAPS LAMDIK',
    ])->assertRedirect();

    $version = AccreditationInstrumentVersion::query()->sole();
    expect($version->status->value)->toBe('draft');

    $this->actingAs($this->lpm)->post(route('accreditation.versions.transition', $version), ['status' => 'published'])->assertSessionHasErrors('status');

    $this->actingAs($this->lpm)->post(route('accreditation.criteria.store', $version), ['code' => 'C1', 'title' => 'VMTS', 'weight' => 100])->assertRedirect();
    $criterion = $version->criteria()->sole();
    $this->actingAs($this->lpm)->post(route('accreditation.criteria.store', $version), ['code' => 'C1.1', 'title' => 'Sub', 'parent_id' => $criterion->id])->assertRedirect();
    $this->actingAs($this->lpm)->post(route('accreditation.indicators.store', $criterion), [
        'code' => 'C1.1.1', 'statement' => 'Visi keilmuan selaras', 'weight' => 2, 'is_essential' => true,
    ])->assertRedirect();

    $this->actingAs($this->lpm)->get(route('accreditation.versions.show', $version))->assertOk()->assertInertia(fn ($page) => $page->where('issues', []));
    $this->actingAs($this->lpm)->post(route('accreditation.versions.transition', $version), ['status' => 'published'])->assertSessionHasNoErrors();
    expect($version->fresh()->status->value)->toBe('published');

    // Terkunci: perubahan struktur ditolak.
    $this->actingAs($this->lpm)->post(route('accreditation.criteria.store', $version), ['code' => 'C2', 'title' => 'Tata pamong', 'weight' => 0]);
    expect($version->criteria()->count())->toBe(2);

    $this->actingAs($this->lpm)->post(route('accreditation.versions.duplicate', $version))->assertRedirect();
    $copy = AccreditationInstrumentVersion::query()->where('version', '1.1')->sole();
    expect($copy->status->value)->toBe('draft')
        ->and($copy->criteria()->count())->toBe(2)
        ->and($copy->criteria()->whereNotNull('parent_id')->sole()->parent->code)->toBe('C1')
        ->and($copy->indicators()->sole()->is_essential)->toBeTrue();
});

it('imports criteria and indicators from a spreadsheet into a draft version', function () {
    $instrument = AccreditationInstrument::query()->create(['accreditation_body_id' => $this->body->id, 'code' => 'IAPS-I', 'name' => 'Impor']);
    $version = $instrument->versions()->create(['version' => '1.0', 'status' => 'draft', 'scale_max' => 4]);
    $csv = "kode_kriteria,kriteria,bobot_kriteria,induk,kode_indikator,indikator,target,bukti,bobot,esensial\n"
        ."C1,VMTS,40,,C1.1,Visi keilmuan,SK VMTS,SK penetapan,2,ya\n"
        ."C2,Pendidikan,60,,,,,,,\n"
        ."C2.1,Kurikulum,,C2,C2.1.1,Kurikulum OBE,,Dokumen kurikulum,1,\n";

    $this->actingAs($this->lpm)->post(route('accreditation.versions.import', $version), [
        'file' => UploadedFile::fake()->createWithContent('instrumen.csv', $csv),
    ])->assertSessionHasNoErrors();

    expect($version->criteria()->count())->toBe(3)
        ->and($version->criteria()->where('code', 'C2.1')->sole()->parent->code)->toBe('C2')
        ->and($version->indicators()->count())->toBe(2)
        ->and($version->indicators()->where('code', 'C1.1')->sole()->is_essential)->toBeTrue();

    $rows = downloadedRows($this->actingAs($this->lpm)->get(route('accreditation.versions.export', $version))->assertDownload('instrumen-iaps-i-v10.xlsx'));

    expect($rows)->toHaveCount(4)
        ->and($rows[1][0])->toBe('C1');
});

it('runs a period from preparation to decision and syncs the study program', function () {
    $version = publishedVersion();
    $indicator = $version->indicators()->sole();
    $pic = userWithRole(UserRole::Dosen);
    Lecturer::factory()->create(['user_id' => $pic->id, 'study_program_id' => $this->program->id]);
    $outsider = userWithRole(UserRole::Dosen);

    $store = fn () => $this->actingAs($this->lpm)->post(route('accreditation.periods.store'), [
        'study_program_id' => $this->program->id, 'instrument_version_id' => $version->id, 'name' => 'Reakreditasi 2027',
        'pic_user_id' => $pic->id, 'submission_deadline' => now()->addDays(60)->toDateString(),
    ]);
    $store()->assertRedirect();
    $store();
    $period = AccreditationPeriod::query()->sole();

    // PIC (dosen tanpa peran akreditasi) dapat menilai dan melampirkan bukti; dosen lain tidak.
    $this->actingAs($pic)->get(route('accreditation.periods.show', $period))->assertOk()->assertInertia(fn ($page) => $page->where('can.contribute', true));
    $this->actingAs($outsider)->get(route('accreditation.periods.show', $period))->assertForbidden();
    $this->actingAs($outsider)->post(route('accreditation.periods.assess', $period), ['indicator_id' => $indicator->id, 'self_score' => 3])->assertForbidden();
    $this->actingAs($pic)->post(route('accreditation.periods.assess', $period), ['indicator_id' => $indicator->id, 'self_score' => 3.5, 'notes' => 'Lengkap'])->assertRedirect();

    $service = app(EvidenceService::class);
    $evidence = $service->create(['title' => 'SK VMTS'], $pic, url: 'https://example.test/sk');
    $this->actingAs($pic)->post(route('evidence.map'), ['evidence_id' => $evidence->id, 'mappable_type' => 'accreditation_indicator', 'mappable_id' => $indicator->id])
        ->assertSessionHasErrors('context_id');
    $this->actingAs($outsider)->post(route('evidence.map'), ['evidence_id' => $evidence->id, 'mappable_type' => 'accreditation_indicator', 'mappable_id' => $indicator->id, 'context_id' => $period->id])
        ->assertForbidden();
    $this->actingAs($pic)->post(route('evidence.map'), ['evidence_id' => $evidence->id, 'mappable_type' => 'accreditation_indicator', 'mappable_id' => $indicator->id, 'context_id' => $period->id])
        ->assertSessionHasNoErrors();
    $service->verify($evidence, $this->lpm, true, null);

    $this->actingAs($this->lpm)->get(route('accreditation.periods.show', $period))->assertInertia(fn ($page) => $page
        ->where('summary.ready', 1)->where('summary.readiness', 100)->where('summary.essential_unmet', 0)->where('summary.estimated_score', 350));
    $this->actingAs($this->lpm)->get(route('accreditation.periods.report', $period))->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs($this->lpm)->post(route('accreditation.periods.advance', $period))->assertRedirect();
    $this->actingAs($this->lpm)->post(route('accreditation.periods.advance', $period))->assertRedirect();
    expect($period->fresh()->status)->toBe(AccreditationPeriodStatus::Visitation);
    $this->actingAs($this->lpm)->post(route('accreditation.periods.advance', $period))->assertSessionHasErrors('status');

    $this->actingAs($this->lpm)->post(route('accreditation.periods.decide', $period), [
        'result_grade' => 'Unggul', 'result_score' => 365, 'sk_number' => 'SK/123', 'decided_on' => now()->toDateString(), 'valid_until' => now()->addYears(5)->toDateString(),
    ])->assertRedirect();

    expect($period->fresh()->status)->toBe(AccreditationPeriodStatus::Decided)
        ->and($this->program->fresh()->accreditation_status)->toBe('Unggul')
        ->and($this->program->fresh()->accreditation_valid_until->toDateString())->toBe(now()->addYears(5)->toDateString());

    // Periode yang sudah diputuskan mengunci bukti dan penilaian.
    $mapping = $indicator->evidenceMappings()->sole();
    $this->actingAs($this->lpm)->delete(route('evidence.unmap', $mapping))->assertSessionHasErrors('mappable_id');
    $this->actingAs($pic)->post(route('accreditation.periods.assess', $period), ['indicator_id' => $indicator->id, 'self_score' => 1])->assertForbidden();
});

it('scopes accreditation pages to the user and renders readiness', function () {
    $version = publishedVersion();
    $other = StudyProgram::factory()->create();
    $period = AccreditationPeriod::query()->create(['study_program_id' => $other->id, 'instrument_version_id' => $version->id, 'code' => 'AKR-X', 'name' => 'Lain']);
    $kaprodi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);

    $this->actingAs($kaprodi)->get(route('accreditation.readiness'))->assertOk()->assertInertia(fn ($page) => $page->has('programs', 1)->where('programs.0.id', $this->program->id));
    $this->actingAs($kaprodi)->get(route('accreditation.periods.index'))->assertOk()->assertInertia(fn ($page) => $page->has('periods', 0));
    $this->actingAs($kaprodi)->get(route('accreditation.periods.show', $period))->assertForbidden();
    $this->actingAs($kaprodi)->get(route('accreditation.instruments.index'))->assertForbidden();

    $this->actingAs($this->lpm)->get(route('accreditation.readiness'))->assertOk()->assertInertia(fn ($page) => $page->where('programs.0.is_institution', true)->where('programs.1.period.id', $period->id));
    $this->actingAs($this->lpm)->get(route('accreditation.instruments.index'))->assertOk();
});

it('seeds the institution (APT) instrument once as a draft with nine criteria', function () {
    $this->seed(InstitutionAccreditationSeeder::class);
    $this->seed(InstitutionAccreditationSeeder::class);

    $instrument = AccreditationInstrument::query()->where('code', InstitutionAccreditationSeeder::CODE)->sole();
    $version = $instrument->versions()->sole();

    expect($instrument->body->code)->toBe('BAN-PT')
        ->and($version->status->value)->toBe('draft')
        ->and($version->criteria()->count())->toBe(9)
        ->and((float) $version->criteria()->sum('weight'))->toBe(100.0)
        ->and($version->indicators()->count())->toBeGreaterThan(20);

    $this->actingAs($this->lpm)->get(route('accreditation.versions.show', $version))->assertInertia(fn ($page) => $page->where('issues', []));
    $this->actingAs($this->lpm)->post(route('accreditation.versions.transition', $version), ['status' => 'published'])->assertSessionHasNoErrors();
    expect($version->fresh()->status->value)->toBe('published');
});

/**
 * Instrumen APT hasil seeder, langsung ditetapkan berlaku.
 */
function publishedInstitutionVersion(): AccreditationInstrumentVersion
{
    test()->seed(InstitutionAccreditationSeeder::class);
    $version = AccreditationInstrument::query()->where('code', InstitutionAccreditationSeeder::CODE)->sole()->versions()->sole();
    $version->update(['status' => 'published']);

    return $version;
}

it('creates an institution accreditation period without a study program', function () {
    $apt = publishedInstitutionVersion();

    $this->actingAs($this->lpm)->get(route('accreditation.periods.index'))
        ->assertInertia(fn ($page) => $page->where('versions.0.institutional', true));

    $this->actingAs($this->lpm)->post(route('accreditation.periods.store'), [
        'study_program_id' => $this->program->id, 'instrument_version_id' => $apt->id, 'name' => 'Akreditasi Perguruan Tinggi 2027',
    ])->assertSessionHasNoErrors();

    $period = AccreditationPeriod::query()->sole();
    expect($period->study_program_id)->toBeNull()->and($period->isInstitutional())->toBeTrue();

    $this->actingAs($this->lpm)->get(route('accreditation.periods.show', $period))->assertOk()
        ->assertInertia(fn ($page) => $page->where('period.is_institutional', true)->where('period.study_program', 'Institusi (APT)'));
    $this->actingAs($this->lpm)->get(route('accreditation.periods.report', $period))->assertOk();
    $this->actingAs($this->lpm)->get(route('accreditation.periods.index', ['study_program_id' => 'institution']))
        ->assertInertia(fn ($page) => $page->has('periods', 1));

    // Satu periode institusi berjalan pada satu waktu.
    $this->actingAs($this->lpm)->post(route('accreditation.periods.store'), [
        'instrument_version_id' => $apt->id, 'name' => 'Duplikat',
    ]);
    expect(AccreditationPeriod::query()->count())->toBe(1);
});

it('records an institution decision without touching any study program', function () {
    $apt = publishedInstitutionVersion();
    $this->program->update(['accreditation_status' => 'Baik']);
    $this->actingAs($this->lpm)->post(route('accreditation.periods.store'), ['instrument_version_id' => $apt->id, 'name' => 'APT 2027']);
    $period = AccreditationPeriod::query()->sole();

    $this->actingAs($this->lpm)->post(route('accreditation.periods.decide', $period), [
        'result_grade' => 'Unggul', 'decided_on' => '2027-08-01', 'valid_until' => '2032-08-01',
    ])->assertSessionHasNoErrors();

    expect($period->fresh()->currentAccreditation())->toBe(['grade' => 'Unggul', 'valid_until' => '2032-08-01'])
        ->and($this->program->fresh()->accreditation_status)->toBe('Baik');
});

it('keeps institution periods away from study program admins', function () {
    $apt = publishedInstitutionVersion();
    $this->actingAs($this->lpm)->post(route('accreditation.periods.store'), ['instrument_version_id' => $apt->id, 'name' => 'APT 2027']);
    $period = AccreditationPeriod::query()->sole();
    $kaprodi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);
    $kaprodi->givePermissionTo('accreditation.view');

    $this->actingAs($kaprodi)->get(route('accreditation.periods.index'))->assertInertia(fn ($page) => $page->has('periods', 0));
    $this->actingAs($kaprodi)->get(route('accreditation.periods.show', $period))->assertForbidden();
});

it('still requires a study program for a study program instrument', function () {
    $this->actingAs($this->lpm)->post(route('accreditation.periods.store'), [
        'instrument_version_id' => publishedVersion()->id, 'name' => 'Tanpa prodi',
    ])->assertSessionHasErrors('study_program_id');
});

it('suggests closing document gaps for an institution period nearing its deadline', function () {
    $apt = publishedInstitutionVersion();
    AccreditationPeriod::query()->create([
        'instrument_version_id' => $apt->id, 'code' => 'AKR-APT', 'name' => 'APT 2027', 'status' => AccreditationPeriodStatus::Preparing,
        'submission_deadline' => now()->addDays(20)->toDateString(),
    ]);

    $titles = app(RecommendationEngine::class)->candidates($this->lpm)->pluck('title');

    expect($titles->contains(fn (string $title): bool => str_starts_with($title, 'Percepat pemenuhan dokumen akreditasi')))->toBeTrue();
});

it('shows the institution first on the readiness page and dashboard for institution-wide users', function () {
    $apt = publishedInstitutionVersion();
    $this->actingAs($this->lpm)->post(route('accreditation.periods.store'), ['instrument_version_id' => $apt->id, 'name' => 'APT 2027']);
    $period = AccreditationPeriod::query()->sole();

    $this->actingAs($this->lpm)->get(route('accreditation.readiness'))
        ->assertInertia(fn ($page) => $page
            ->where('programs.0.is_institution', true)
            ->where('programs.0.body', 'BAN-PT')
            ->where('programs.0.period.id', $period->id)
            ->has('programs.0.criteria', 9)
            ->where('programs.1.is_institution', false));

    $this->actingAs($this->lpm)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('accreditation.0.is_institution', true)
            ->where('accreditation.0.period_id', $period->id));
});

it('shows the latest institution decision even without a running period', function () {
    $apt = publishedInstitutionVersion();
    AccreditationPeriod::query()->create([
        'instrument_version_id' => $apt->id, 'code' => 'AKR-APT-2022', 'name' => 'APT 2022', 'status' => AccreditationPeriodStatus::Decided,
        'result_grade' => 'Baik Sekali', 'decided_on' => '2022-05-01', 'valid_until' => now()->addDays(100)->toDateString(),
    ]);

    $this->actingAs($this->lpm)->get(route('accreditation.readiness'))
        ->assertInertia(fn ($page) => $page
            ->where('programs.0.status', 'Baik Sekali')
            ->where('programs.0.days_left', 100)
            ->where('programs.0.period', null));
});

it('hides the institution row from study program admins', function () {
    $kaprodi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);
    $kaprodi->givePermissionTo('accreditation.view');

    $this->actingAs($kaprodi)->get(route('accreditation.readiness'))
        ->assertInertia(fn ($page) => $page->where('programs', fn ($programs) => collect($programs)->every(fn ($row) => $row['is_institution'] === false)));
});

it('keeps a prodi admin granted accreditation.manage away from periods outside their scope', function () {
    $apt = publishedInstitutionVersion();
    $this->actingAs($this->lpm)->post(route('accreditation.periods.store'), ['instrument_version_id' => $apt->id, 'name' => 'APT 2027']);
    $institution = AccreditationPeriod::query()->sole();
    $otherProgram = AccreditationPeriod::query()->create([
        'study_program_id' => StudyProgram::factory()->create()->id, 'instrument_version_id' => publishedVersion()->id, 'code' => 'AKR-LAIN', 'name' => 'Prodi lain',
    ]);

    $kaprodi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);
    $kaprodi->givePermissionTo('accreditation.manage');

    foreach ([$institution, $otherProgram] as $period) {
        $this->actingAs($kaprodi)->post(route('accreditation.periods.cancel', $period), ['reason' => 'uji'])->assertForbidden();
        $this->actingAs($kaprodi)->post(route('accreditation.periods.advance', $period))->assertForbidden();
        $this->actingAs($kaprodi)->post(route('accreditation.periods.decide', $period), [
            'result_grade' => 'Unggul', 'decided_on' => '2027-08-01', 'valid_until' => '2032-08-01',
        ])->assertForbidden();
        $this->actingAs($kaprodi)->put(route('accreditation.periods.update', $period), [
            'study_program_id' => $this->program->id, 'instrument_version_id' => $period->instrument_version_id, 'name' => 'Diambil alih',
        ])->assertForbidden();

        expect($period->fresh()->canContribute($kaprodi))->toBeFalse()
            ->and($period->fresh()->status)->toBe(AccreditationPeriodStatus::Preparing);
    }
});
