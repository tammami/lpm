<?php

use App\Enums\UserRole;
use App\Models\AccreditationPeriod;
use App\Models\Audit;
use App\Models\CourseClass;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\Instrument;
use App\Models\Lecturer;
use App\Models\Recommendation;
use App\Models\StudyProgram;
use App\Models\Survey;
use App\Models\User;
use Database\Seeders\AcademicDemoSeeder;
use Database\Seeders\AccreditationSeeder;
use Database\Seeders\AmiDemoSeeder;
use Database\Seeders\AmiReferenceSeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\EvidenceDemoSeeder;
use Database\Seeders\ImprovementDemoSeeder;
use Database\Seeders\InstrumentSeeder;
use Database\Seeders\MonevDemoSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\ReferenceDataSeeder;

beforeEach(function () {
    $this->seed([OrganizationSeeder::class, ReferenceDataSeeder::class, DemoUserSeeder::class, AcademicDemoSeeder::class, InstrumentSeeder::class, MonevDemoSeeder::class]);
});

it('renders every staff page for an LPM admin without errors', function () {
    $admin = User::query()->where('username', 'lpm')->firstOrFail();
    $class = CourseClass::query()->firstOrFail();
    $survey = Survey::query()->where('status', 'closed')->firstOrFail();
    $instrument = Instrument::query()->firstOrFail();
    $lecturer = Lecturer::query()->firstOrFail();

    $pages = [
        route('dashboard'),
        route('institution.edit'),
        route('faculties.index'),
        route('study-programs.index'),
        route('units.index'),
        route('accreditation-bodies.index'),
        route('academic-periods.index'),
        route('lecturers.index'),
        route('students.index', ['search' => 'a', 'sort' => '-entry_year']),
        route('courses.index'),
        route('classes.index'),
        route('classes.show', $class),
        route('instruments.index'),
        route('instrument-versions.show', $instrument->latestVersion()->first()),
        route('instrument-versions.preview', $instrument->latestVersion()->first()),
        route('surveys.index'),
        route('surveys.create'),
        route('surveys.show', $survey),
        route('surveys.edit', $survey),
        route('surveys.participations', $survey),
        route('analytics.index'),
        route('analytics.index', ['survey' => $survey->id, 'study_program_id' => StudyProgram::query()->value('id')]),
        route('analytics.lecturers.show', ['lecturer' => $lecturer->id, 'survey' => $survey->id]),
        route('reports.index'),
        route('imports.index'),
        route('users.index'),
        route('settings.index'),
        route('settings.scales'),
        route('audit-logs.index'),
        route('notifications.index'),
        route('profile.edit'),
    ];

    foreach ($pages as $page) {
        $this->actingAs($admin)->get($page)->assertOk();
    }
});

it('renders scoped pages for an admin prodi', function () {
    $admin = User::query()->where('username', 'prodi.tmtk')->firstOrFail();

    foreach (['dashboard', 'lecturers.index', 'students.index', 'courses.index', 'classes.index', 'study-programs.index', 'analytics.index', 'surveys.index', 'reports.index'] as $name) {
        $this->actingAs($admin)->get(route($name))->assertOk();
    }
});

it('renders the lecturer and student experiences', function () {
    $lecturer = User::query()->where('email', 'dosen@simutu.test')->firstOrFail();
    $student = User::query()->where('email', 'mahasiswa@simutu.test')->firstOrFail();

    $this->actingAs($lecturer)->get(route('dashboard'))->assertOk();
    $this->actingAs($lecturer)->get(route('my-evaluation.index'))->assertOk();
    $this->actingAs($student)->get(route('portal.home'))->assertOk();
});

it('downloads the monev PDF and Excel reports', function () {
    $admin = User::query()->where('username', 'lpm')->firstOrFail();
    $survey = Survey::query()->where('status', 'closed')->firstOrFail();

    $this->actingAs($admin)->get(route('reports.monev.pdf', ['survey' => $survey->id]))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('reports.monev.excel', ['survey' => $survey->id]))->assertOk();
    $this->actingAs($admin)->get(route('reports.monev.responses', ['survey' => $survey->id]))->assertOk();
});

it('renders the dashboard for pimpinan and auditor accounts', function () {
    foreach (['rektor', 'auditor', 'ftk'] as $username) {
        $this->actingAs(User::query()->where('username', $username)->firstOrFail())->get(route('dashboard'))->assertOk();
    }
});

it('shows students their notifications inside the portal', function () {
    $student = User::query()->where('email', 'mahasiswa@simutu.test')->firstOrFail();

    $this->actingAs($student)->get(route('notifications.index'))->assertOk();
});

it('forbids pimpinan from managing users', function () {
    $this->actingAs(User::query()->where('username', 'rektor')->firstOrFail())->get(route('users.index'))->assertForbidden();
    expect(User::query()->where('username', 'rektor')->firstOrFail()->hasRole(UserRole::Pimpinan->value))->toBeTrue();
});

it('renders AMI, evidence and improvement pages with the full demo cycle', function () {
    $this->seed([AmiReferenceSeeder::class, AmiDemoSeeder::class, EvidenceDemoSeeder::class, AccreditationSeeder::class, ImprovementDemoSeeder::class]);

    $audit = Audit::query()->where('status', 'completed')->firstOrFail();
    $finding = Finding::query()->firstOrFail();
    $evidence = Evidence::query()->firstOrFail();
    $recommendation = Recommendation::query()->has('actionPlans')->firstOrFail();
    $period = AccreditationPeriod::query()->where('status', 'preparing')->firstOrFail();

    $pages = [
        route('ami.programs.index'),
        route('ami.programs.show', $audit->audit_program_id),
        route('ami.audits.index'),
        route('ami.audits.show', $audit),
        route('ami.auditors.index'),
        route('ami.standards.index'),
        route('ami.findings.index'),
        route('ami.findings.show', $finding),
        route('evidence.index'),
        route('evidence.show', $evidence),
        route('evidence-categories.index'),
        route('evidence.search', ['q' => 'kurikulum']),
        route('improvement.recommendations.index'),
        route('improvement.recommendations.generate'),
        route('improvement.recommendations.show', $recommendation),
        route('improvement.action-plans.index', ['overdue' => 1]),
        route('accreditation.readiness'),
        route('accreditation.periods.index', ['create' => 1]),
        route('accreditation.periods.show', $period),
        route('accreditation.instruments.index'),
        route('accreditation.versions.show', $period->instrument_version_id),
    ];

    foreach (['lpm', 'prodi.tmtk', 'rektor', 'auditor'] as $username) {
        $user = User::query()->where('username', $username)->firstOrFail();

        foreach ($pages as $url) {
            $status = $this->actingAs($user)->get($url)->status();
            expect($status)->toBeIn([200, 403], "{$username} {$url} → {$status}");
        }
    }

    $admin = User::query()->where('username', 'lpm')->firstOrFail();
    foreach ($pages as $url) {
        $this->actingAs($admin)->get($url)->assertOk();
    }

    $this->actingAs($admin)->get(route('ami.audits.report', $audit))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('accreditation.periods.report', $period))->assertOk()->assertHeader('content-type', 'application/pdf');

    foreach (['lpm', 'prodi.tmtk', 'rektor', 'ftk', 'auditor'] as $username) {
        $this->actingAs(User::query()->where('username', $username)->firstOrFail())->get(route('dashboard'))->assertOk();
    }
});
