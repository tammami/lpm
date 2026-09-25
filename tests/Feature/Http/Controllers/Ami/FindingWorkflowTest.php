<?php

use App\Enums\AuditeeType;
use App\Enums\AuditStatus;
use App\Enums\FindingStatus;
use App\Enums\UserRole;
use App\Models\Audit;
use App\Models\Auditor;
use App\Models\AuditProgram;
use App\Models\Finding;
use App\Models\FindingSeverity;
use App\Models\InstrumentVersion;
use App\Models\StudyProgram;

beforeEach(function () {
    $this->program = StudyProgram::factory()->create();
    $this->auditorUser = userWithRole(UserRole::Auditor);
    $this->pic = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);
    $auditor = Auditor::query()->create(['user_id' => $this->auditorUser->id]);

    $this->audit = Audit::query()->create([
        'audit_program_id' => AuditProgram::query()->create(['code' => 'AMI-T-01', 'name' => 'AMI Uji', 'year' => 2026])->id,
        'code' => 'AUD-T-001',
        'auditee_type' => AuditeeType::StudyProgram,
        'auditee_id' => $this->program->id,
        'auditee_name' => $this->program->full_name,
        'study_program_id' => $this->program->id,
        'faculty_id' => $this->program->faculty_id,
        'instrument_version_id' => InstrumentVersion::factory()->published()->withLikertQuestions([1])->create()->id,
        'auditee_pic_user_id' => $this->pic->id,
        'scheduled_on' => now()->toDateString(),
        'status' => AuditStatus::FieldAudit,
    ]);
    $this->audit->auditors()->attach($auditor->id, ['role' => 'lead']);

    $this->major = FindingSeverity::query()->create(['code' => 'MAYOR', 'name' => 'KTS Mayor', 'requires_corrective_action' => true, 'default_due_days' => 30]);
});

function recordFinding(): Finding
{
    test()->actingAs(test()->auditorUser)->post(route('ami.findings.store', test()->audit), [
        'title' => 'RPS belum lengkap',
        'description' => '3 dari 11 mata kuliah belum memiliki RPS.',
        'finding_severity_id' => test()->major->id,
    ])->assertRedirect();

    return Finding::query()->sole();
}

it('runs a finding through issue, corrective action, rejection, re-submission, verification and closure', function () {
    $finding = recordFinding();
    expect($finding->status)->toBe(FindingStatus::Open)
        ->and($finding->pic_user_id)->toBe($this->pic->id)
        ->and($finding->due_date->toDateString())->toBe(now()->addDays(30)->toDateString());

    $this->actingAs($this->auditorUser)->post(route('ami.findings.issue', $finding))->assertRedirect();
    expect($finding->fresh()->status)->toBe(FindingStatus::ActionRequired)
        ->and($this->pic->notifications()->count())->toBe(1);

    $this->actingAs($this->pic)->post(route('ami.findings.actions.store', $finding), [
        'root_cause' => 'Belum ada tenggat internal penyusunan RPS.',
        'action_plan' => 'Menyusun dan mengesahkan RPS.',
        'pic_user_id' => $this->pic->id,
        'due_date' => now()->addDays(20)->toDateString(),
    ])->assertRedirect();
    $action = $finding->correctiveActions()->sole();
    expect($finding->fresh()->status)->toBe(FindingStatus::InProgress);

    $complete = fn () => $this->actingAs($this->pic)->put(route('ami.findings.actions.update', [$finding, $action]), [
        'root_cause' => $action->root_cause, 'action_plan' => $action->action_plan, 'pic_user_id' => $this->pic->id,
        'due_date' => $action->due_date->toDateString(), 'progress' => 100,
    ]);

    $complete();
    $this->actingAs($this->pic)->post(route('ami.findings.submit', $finding))->assertRedirect();
    expect($finding->fresh()->status)->toBe(FindingStatus::Submitted);

    $this->actingAs($this->auditorUser)->post(route('ami.findings.verify', $finding), ['decision' => 'rejected', 'notes' => 'Bukti pengesahan RPS belum dilampirkan.']);
    expect($finding->fresh()->status)->toBe(FindingStatus::InProgress);

    $complete();
    $this->actingAs($this->pic)->post(route('ami.findings.submit', $finding));
    $this->actingAs($this->auditorUser)->post(route('ami.findings.verify', $finding), ['decision' => 'accepted']);
    expect($finding->fresh()->status)->toBe(FindingStatus::Verified);

    $this->actingAs($this->auditorUser)->post(route('ami.findings.close', $finding))->assertRedirect();
    expect($finding->fresh()->status)->toBe(FindingStatus::Closed)
        ->and($finding->verifications()->count())->toBe(2);
});

it('requires a PIC on every corrective action', function () {
    $finding = recordFinding();
    $this->actingAs($this->auditorUser)->post(route('ami.findings.issue', $finding));

    $this->actingAs($this->pic)->post(route('ami.findings.actions.store', $finding), [
        'root_cause' => 'x', 'action_plan' => 'y', 'due_date' => now()->addWeek()->toDateString(),
    ])->assertSessionHasErrors(['pic_user_id' => 'Setiap tindakan koreksi wajib memiliki PIC.']);
});

it('refuses to close a major finding before its corrective action is verified', function () {
    $finding = recordFinding();
    $this->actingAs($this->auditorUser)->post(route('ami.findings.issue', $finding));

    $this->actingAs(userWithRole(UserRole::AdminLpm))->post(route('ami.findings.close', $finding))
        ->assertSessionHasErrors('finding');

    expect($finding->fresh()->status)->toBe(FindingStatus::ActionRequired);
});

it('hides a finding from an admin of another prodi', function () {
    $finding = recordFinding();
    $outsider = userWithRole(UserRole::AdminProdi, ['study_program_id' => StudyProgram::factory()->create()->id]);

    $this->actingAs($outsider)->get(route('ami.findings.show', $finding))->assertForbidden();
});

it('forbids an unassigned auditor from filling the checklist', function () {
    $stranger = userWithRole(UserRole::Auditor);
    Auditor::query()->create(['user_id' => $stranger->id]);
    $question = $this->audit->instrumentVersion->questions()->first();

    $this->actingAs($stranger)->post(route('ami.audits.answers.save', $this->audit), ['instrument_question_id' => $question->id])->assertForbidden();
});

it('lets the lead auditor move the audit forward only after a conclusion is written', function () {
    $this->audit->update(['status' => AuditStatus::Reporting]);

    $this->actingAs($this->auditorUser)->post(route('ami.audits.advance', $this->audit));
    expect($this->audit->fresh()->status)->toBe(AuditStatus::Reporting);

    $this->audit->update(['conclusion' => 'Memenuhi standar dengan catatan.']);
    $this->actingAs($this->auditorUser)->post(route('ami.audits.advance', $this->audit));
    expect($this->audit->fresh()->status)->toBe(AuditStatus::Completed);
});
