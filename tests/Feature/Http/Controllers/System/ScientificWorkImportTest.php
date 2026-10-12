<?php

use App\Enums\ScientificWorkIndexation;
use App\Enums\ScientificWorkStatus;
use App\Enums\ScientificWorkType;
use App\Enums\UserRole;
use App\Models\ImportJob;
use App\Models\Lecturer;
use App\Models\ScientificWork;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Services\Research\WorkWorkbook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Workbook seperti template LPM: sheet "Rekap LPM" lebih dulu, lalu "Data Karya Dosen" dengan 500 baris bernomor.
 *
 * @param  array<int, array<string, mixed>>  $filled  nomor baris → kolom template → nilai
 */
function lpmTemplate(array $filled): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'lpm').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Rekap LPM');
    $writer->addRow(Row::fromValues(['REKAPITULASI KARYA DOSEN', 'Kolom 2']));
    $writer->addRow(Row::fromValues(['Total seluruh karya', 'COUNTA(...)']));

    $writer->addNewSheetAndMakeItCurrent()->setName('Data Karya Dosen');
    $headers = array_slice(WorkWorkbook::DATA_HEADERS, 0, 28);
    $headers[] = 'Mahasiswa Terlibat';
    $writer->addRow(Row::fromValues($headers));

    foreach (range(1, 500) as $number) {
        $values = array_fill_keys($headers, null);
        $values['No.'] = $number;
        $writer->addRow(Row::fromValues(array_values([...$values, ...($filled[$number] ?? [])])));
    }

    $writer->close();

    return new UploadedFile($path, 'Template_Data_Karya_Dosen.xlsx', null, null, true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->program = StudyProgram::factory()->create(['code' => 'TMTK']);
    $this->lecturer = Lecturer::factory()->for($this->program)->create(['nidn' => '0812345678', 'name' => 'Ahmad Fauzi']);
    $this->newcomer = Lecturer::factory()->nuptkOnly()->for($this->program)->create(['nuptk' => '7350765666130123']);
    $this->student = Student::factory()->for($this->program)->create(['name' => 'Siti Aminah', 'nim' => '2511001']);
    $this->admin = userWithRole(UserRole::AdminLpm);
});

function journalRow(array $overrides = []): array
{
    return [
        'Nama Dosen' => 'Ahmad Fauzi',
        'NIDN/NIDK' => '0812345678',
        'Jenis Karya' => 'Artikel & Publikasi Ilmiah',
        'Subjenis Karya' => 'Jurnal Nasional',
        'Judul Karya' => 'Pembelajaran Berbasis Masalah di MTs',
        'Tahun' => 2025,
        'Peran Dosen' => 'Penulis Pertama',
        'Jumlah Penulis' => 3,
        'Nama Jurnal/Penerbit/Media' => 'Jurnal Pendidikan Matematika',
        'Indeksasi/Akreditasi' => 'Sinta 4',
        'Peringkat/Kuartil' => 'S4',
        'Link Publikasi' => 'https://jurnal.contoh.ac.id/123',
        'Sumber Dana' => 'Mandiri',
        'Relevansi dengan Prodi' => 'Ya',
        'Status Verifikasi LPM' => 'Valid',
        'Tanggal Verifikasi' => '2025-11-02',
        'Verifikator' => 'Tim LPM',
        'Mahasiswa Terlibat' => 'Siti Aminah (2511001); Budi Alumni',
        ...$overrides,
    ];
}

it('imports the Data Karya Dosen sheet of the LPM template, skipping numbered empty rows', function () {
    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'karya_ilmiah',
        'file' => lpmTemplate([
            1 => journalRow(),
            2 => ['NIDN/NIDK' => '7350765666130123', 'Subjenis Karya' => 'HKI - Hak Cipta', 'Judul Karya' => 'Aplikasi Kuis Matematika', 'Tahun' => 2026, 'Status HKI' => 'Bersertifikat', 'Nomor HKI' => 'EC00202612345', 'Link Bukti' => 'https://drive.google.com/x'],
            3 => journalRow(['Subjenis Karya' => 'Jurnal Abal-abal', 'Judul Karya' => 'Lain']),
            4 => journalRow(['NIDN/NIDK' => '0800000000', 'Judul Karya' => 'Dosen tidak ada']),
            5 => journalRow(['Judul Karya' => 'Tanpa Indeksasi', 'Indeksasi/Akreditasi' => null]),
        ]),
    ])->assertSessionHasNoErrors();

    $job = ImportJob::query()->sole();

    expect($job->status)->toBe('validated')
        ->and($job->total_rows)->toBe(5)
        ->and($job->valid_rows)->toBe(2)
        ->and($job->errors()->orderBy('row_number')->pluck('message')->all())->toBe([
            'Subjenis karya "Jurnal Abal-abal" tidak dikenal',
            'Dosen tidak ditemukan. Impor data dosen terlebih dahulu.',
            'Pilih indeksasi/akreditasi jurnal atau prosiding.',
        ]);

    $this->actingAs($this->admin)->post(route('imports.confirm', $job), ['update_existing' => true]);

    expect($job->fresh()->status)->toBe('completed');

    $journal = ScientificWork::query()->where('lecturer_id', $this->lecturer->id)->sole();
    expect($journal)
        ->type->toBe(ScientificWorkType::JournalNational)
        ->indexation->toBe(ScientificWorkIndexation::Sinta4)
        ->status->toBe(ScientificWorkStatus::Verified)
        ->relevant_to_program->toBeTrue()
        ->verification_note->toBe('(Verifikator: Tim LPM)')
        ->and($journal->verified_at->toDateString())->toBe('2025-11-02')
        ->and($journal->studentMembers()->pluck('student_id', 'name')->all())->toBe(['Siti Aminah' => $this->student->id, 'Budi Alumni' => null]);

    expect(ScientificWork::query()->where('lecturer_id', $this->newcomer->id)->sole())
        ->type->toBe(ScientificWorkType::Copyright)
        ->ip_status->toBe('certified')
        ->status->toBe(ScientificWorkStatus::Pending);
});

it('re-imports the same file as updates instead of duplicates', function () {
    foreach ([1, 2] as $attempt) {
        $this->actingAs($this->admin)->post(route('imports.store'), ['type' => 'karya_ilmiah', 'file' => lpmTemplate([1 => journalRow()])]);
        $this->actingAs($this->admin)->post(route('imports.confirm', ImportJob::query()->latest('id')->first()), ['update_existing' => true]);
    }

    expect(ImportJob::query()->latest('id')->first()->duplicate_rows)->toBe(1)
        ->and(ScientificWork::query()->count())->toBe(1);
});

it('keeps imported works of a kaprodi pending and rejects lecturers outside the prodi', function () {
    $other = Lecturer::factory()->for(StudyProgram::factory()->create())->create(['nidn' => '0899999999']);
    $kaprodi = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);

    $this->actingAs($kaprodi)->post(route('imports.store'), [
        'type' => 'karya_ilmiah',
        'file' => lpmTemplate([1 => journalRow(), 2 => journalRow(['NIDN/NIDK' => $other->nidn, 'Judul Karya' => 'Prodi lain'])]),
    ]);
    $job = ImportJob::query()->sole();

    expect($job->errors()->pluck('message')->all())->toBe(['Dosen di luar cakupan akses Anda']);

    $this->actingAs($kaprodi)->post(route('imports.confirm', $job), ['update_existing' => true]);

    expect(ScientificWork::query()->sole()->status)->toBe(ScientificWorkStatus::Pending);
});

it('reads back the workbook exported by the application', function () {
    $partner = Lecturer::factory()->for($this->program)->create(['nidn' => '0811111111']);
    $work = ScientificWork::factory()->for($this->lecturer)->create(['title' => 'Karya Bolak Balik', 'evidence_url' => 'https://drive.google.com/y']);
    $work->members()->createMany([
        ['kind' => 'lecturer', 'lecturer_id' => $partner->id, 'name' => $partner->full_name],
        ['kind' => 'student', 'student_id' => $this->student->id, 'name' => 'Siti Aminah', 'nim' => '2511001'],
    ]);

    $export = $this->actingAs($this->admin)->get(route('research.works.export'));
    $path = tempnam(sys_get_temp_dir(), 'exp').'.xlsx';
    file_put_contents($path, $export->streamedContent());
    $work->members()->delete();

    $this->actingAs($this->admin)->post(route('imports.store'), ['type' => 'karya_ilmiah', 'file' => new UploadedFile($path, 'karya.xlsx', null, null, true)]);
    $job = ImportJob::query()->sole();
    expect($job->valid_rows)->toBe(1)->and($job->duplicate_rows)->toBe(1);

    $this->actingAs($this->admin)->post(route('imports.confirm', $job), ['update_existing' => true]);

    expect(ScientificWork::query()->count())->toBe(1)
        ->and($work->lecturerMembers()->value('lecturer_id'))->toBe($partner->id)
        ->and($work->studentMembers()->value('student_id'))->toBe($this->student->id);
});
