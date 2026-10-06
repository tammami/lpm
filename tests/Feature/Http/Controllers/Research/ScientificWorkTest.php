<?php

use App\Enums\ScientificWorkStatus;
use App\Enums\UserRole;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\StudyProgram;
use App\Notifications\ScientificWorkRevisionNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function workPayload(array $overrides = []): array
{
    return [
        'title' => 'Analisis Kemampuan Pemecahan Masalah Matematis Siswa MTs',
        'type' => 'journal_accredited',
        'rank' => 'S3',
        'year' => now()->year,
        'venue' => 'Jurnal Pendidikan Matematika',
        'author_role' => 'first',
        'source' => 'research',
        'funding' => 'self',
        'url' => '10.1234/jpm.2026.01',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->programA = StudyProgram::factory()->create();
    $this->programB = StudyProgram::factory()->create();
    $this->dosenUser = userWithRole(UserRole::Dosen);
    $this->lecturer = Lecturer::factory()->for($this->programA)->create(['user_id' => $this->dosenUser->id]);
    $this->adminProdi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->programA->id]);
    $this->lpm = userWithRole(UserRole::AdminLpm);
});

it('lets a lecturer record their own work without uploading a file', function () {
    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload())->assertSessionHasNoErrors();

    $work = ScientificWork::query()->sole();

    expect($work->lecturer_id)->toBe($this->lecturer->id)
        ->and($work->status)->toBe(ScientificWorkStatus::Pending)
        ->and($work->rank)->toBe('S3')
        ->and($work->created_by)->toBe($this->dosenUser->id);

    $this->actingAs($this->dosenUser)->get(route('research.mine.index'))
        ->assertInertia(fn (Assert $page) => $page->component('research/mine')->has('works', 1)->where('works.0.rank_label', 'SINTA 3'));
});

it('requires a rank for accredited journals and drops it for other types', function () {
    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['rank' => '']))
        ->assertSessionHasErrors(['rank' => 'Pilih peringkat/indeksasi jurnal.']);

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['type' => 'book', 'rank' => 'S3', 'url' => 'not a link']))
        ->assertSessionHasErrors('url');

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['type' => 'book', 'rank' => 'S3']))->assertSessionHasNoErrors();

    expect(ScientificWork::query()->sole()->rank)->toBeNull();
});

it('keeps lecturers away from works of other lecturers', function () {
    $other = ScientificWork::factory()->for(Lecturer::factory()->for($this->programA))->create();

    $this->actingAs($this->dosenUser)->put(route('research.mine.update', $other), workPayload())->assertForbidden();
    $this->actingAs($this->dosenUser)->delete(route('research.mine.destroy', $other))->assertForbidden();
    $this->actingAs($this->dosenUser)->get(route('research.works.index'))->assertForbidden();
    $this->actingAs($this->dosenUser)->get(route('research.analysis'))->assertForbidden();
});

it('sends an edited verified work back to verification and blocks deleting it', function () {
    $work = ScientificWork::factory()->for($this->lecturer)->create(['status' => ScientificWorkStatus::Verified, 'verified_by' => $this->lpm->id, 'verified_at' => now()]);

    $this->actingAs($this->dosenUser)->delete(route('research.mine.destroy', $work))->assertRedirect();
    expect($work->fresh())->not->toBeNull();

    $this->actingAs($this->dosenUser)->put(route('research.mine.update', $work), workPayload(['title' => 'Judul diperbaiki']))->assertSessionHasNoErrors();

    expect($work->fresh()->status)->toBe(ScientificWorkStatus::Pending)
        ->and($work->fresh()->verified_by)->toBeNull()
        ->and($work->fresh()->title)->toBe('Judul diperbaiki');
});

it('scopes the admin prodi list and forbids recording works for another prodi', function () {
    ScientificWork::factory()->for($this->lecturer)->create(['title' => 'Karya Prodi A']);
    $lecturerB = Lecturer::factory()->for($this->programB)->create();
    ScientificWork::factory()->for($lecturerB)->create(['title' => 'Karya Prodi B']);

    $this->actingAs($this->adminProdi)->get(route('research.works.index'))
        ->assertInertia(fn (Assert $page) => $page->component('research/works')->has('works.data', 1)->where('works.data.0.title', 'Karya Prodi A'));

    $this->actingAs($this->adminProdi)->post(route('research.works.store'), workPayload(['lecturer_id' => $lecturerB->id]))->assertForbidden();

    $this->actingAs($this->adminProdi)->post(route('research.works.store'), workPayload(['lecturer_id' => $this->lecturer->id]))->assertSessionHasNoErrors();
    expect($this->lecturer->scientificWorks()->count())->toBe(2);
});

it('lets LPM verify or return a work with a note that reaches the lecturer', function () {
    Notification::fake();
    $work = ScientificWork::factory()->for($this->lecturer)->create();

    $this->actingAs($this->lpm)->post(route('research.works.verify', $work), ['decision' => 'revision'])
        ->assertSessionHasErrors(['note' => 'Tuliskan apa yang perlu diperbaiki dosen.']);

    $this->actingAs($this->lpm)->post(route('research.works.verify', $work), ['decision' => 'revision', 'note' => 'DOI tidak bisa dibuka'])->assertSessionHasNoErrors();

    expect($work->fresh()->status)->toBe(ScientificWorkStatus::Revision)
        ->and($work->fresh()->verification_note)->toBe('DOI tidak bisa dibuka');
    Notification::assertSentTo($this->dosenUser, ScientificWorkRevisionNotification::class);

    $this->actingAs($this->lpm)->post(route('research.works.verify', $work), ['decision' => 'verified'])->assertSessionHasNoErrors();

    expect($work->fresh()->status)->toBe(ScientificWorkStatus::Verified)
        ->and($work->fresh()->verified_by)->toBe($this->lpm->id)
        ->and($work->fresh()->verification_note)->toBeNull();

    $this->actingAs($this->adminProdi)->post(route('research.works.verify', $work), ['decision' => 'verified'])->assertForbidden();
});

it('lists lecturers without works and research statistics for the chosen years', function () {
    $year = now()->year;
    ScientificWork::factory()->for($this->lecturer)->accredited('S2')->create(['year' => $year, 'status' => ScientificWorkStatus::Verified]);
    ScientificWork::factory()->for($this->lecturer)->create(['year' => $year - 1, 'type' => 'book']);
    $idle = Lecturer::factory()->for($this->programA)->create(['name' => 'Dosen Belum Berkarya']);
    ScientificWork::factory()->for($idle)->create(['year' => $year - 6]);
    Lecturer::factory()->for($this->programA)->create(['is_active' => false]);

    $this->actingAs($this->adminProdi)->get(route('research.analysis'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('research/analysis')
            ->where('filters.from', $year - 2)
            ->where('summary.lecturers', 2)
            ->where('summary.productive', 1)
            ->where('summary.without', 1)
            ->where('summary.works', 2)
            ->where('summary.reputable', 1)
            ->where('lecturers.1.name', $idle->full_name)
            ->where('lecturers.1.last_year', $year - 6)
            ->where('byRank', fn ($ranks) => collect($ranks)->firstWhere('key', 'S2')['count'] === 1)
            ->where('byYear.2.journal', 1)
            ->where('byYear.1.book', 1));

    $this->actingAs($this->adminProdi)->get(route('research.analysis', ['verified_only' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('summary.works', 1));

    $this->actingAs($this->adminProdi)->get(route('research.analysis', ['study_program_id' => $this->programB->id]))->assertForbidden();
});

it('exports the analysis with the list of lecturers without works', function () {
    ScientificWork::factory()->for($this->lecturer)->create();
    Lecturer::factory()->for($this->programA)->create(['name' => 'Dosen Belum Berkarya', 'back_title' => null]);

    $rows = downloadedRows($this->actingAs($this->adminProdi)->get(route('research.analysis.export')));

    expect($rows[0][0])->toBe('NIDN')
        ->and(collect($rows)->pluck(1))->toContain('Dosen Belum Berkarya');
});

it('exports the filtered list of works', function () {
    ScientificWork::factory()->for($this->lecturer)->accredited()->create(['title' => 'Karya Ekspor']);

    $rows = downloadedRows($this->actingAs($this->lpm)->get(route('research.works.export')));

    expect($rows)->toHaveCount(2)
        ->and($rows[1][4])->toBe('Karya Ekspor')
        ->and($rows[1][6])->toBe('SINTA 3');
});
