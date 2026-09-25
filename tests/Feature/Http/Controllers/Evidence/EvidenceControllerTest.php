<?php

use App\Enums\EvidenceStatus;
use App\Enums\UserRole;
use App\Models\Evidence;
use App\Models\Institution;
use App\Models\StudyProgram;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Institution::factory()->create();
    $this->program = StudyProgram::factory()->create();
    $this->owner = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);
});

function uploadEvidence(): Evidence
{
    test()->actingAs(test()->owner)->post(route('evidence.store'), [
        'title' => 'RPS Matematika Diskrit',
        'unit' => 'study_program:'.test()->program->id,
        'file' => UploadedFile::fake()->create('rps.pdf', 120, 'application/pdf'),
    ])->assertRedirect();

    return Evidence::query()->sole();
}

it('stores the uploaded file privately with an auto-generated code', function () {
    $evidence = uploadEvidence();
    $version = $evidence->currentVersion;

    expect($evidence->code)->toStartWith('DOK-')
        ->and($evidence->status)->toBe(EvidenceStatus::Pending)
        ->and($evidence->study_program_id)->toBe($this->program->id)
        ->and($version->file_path)->toStartWith("evidence/{$evidence->id}/")
        ->and($version->file_path)->not->toContain('public');

    Storage::disk('local')->assertExists($version->file_path);
});

it('keeps previous versions when a new version is uploaded', function () {
    $evidence = uploadEvidence();
    $first = $evidence->currentVersion;

    $this->actingAs($this->owner)->post(route('evidence.versions.store', $evidence), [
        'file' => UploadedFile::fake()->create('rps-revisi.pdf', 150, 'application/pdf'),
        'notes' => 'Revisi CPMK',
    ])->assertRedirect();

    $evidence->refresh();

    expect($evidence->versions()->count())->toBe(2)
        ->and($evidence->currentVersion->version)->toBe(2);
    Storage::disk('local')->assertExists($first->file_path);
});

it('rejects executable uploads', function () {
    $this->actingAs($this->owner)->post(route('evidence.store'), [
        'title' => 'Berbahaya',
        'file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
    ])->assertSessionHasErrors(['file' => 'Format berkas tidak diizinkan.']);
});

it('refuses downloads from users outside the document scope', function () {
    $evidence = uploadEvidence();
    $outsider = userWithRole(UserRole::AdminProdi, ['study_program_id' => StudyProgram::factory()->create()->id]);

    $this->actingAs($outsider)->get(route('evidence.download', $evidence))->assertForbidden();
    $this->actingAs($this->owner)->get(route('evidence.download', $evidence))->assertOk();
});

it('lets a verifier reject a document and notifies the owner', function () {
    $evidence = uploadEvidence();

    $this->actingAs(userWithRole(UserRole::AdminLpm))
        ->post(route('evidence.verify', $evidence), ['decision' => 'rejected', 'notes' => 'Belum ditandatangani kaprodi.'])
        ->assertRedirect();

    expect($evidence->fresh()->status)->toBe(EvidenceStatus::Rejected)
        ->and($this->owner->notifications()->count())->toBe(1);
});
