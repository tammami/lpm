<?php

use App\Enums\UserRole;
use App\Models\ImportJob;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\User;
use App\Services\AccountProvisioner;
use App\Services\Import\ImportManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * @param  list<list<string|null>>  $rows
 */
function studentWorkbook(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['NIM*', 'Nama*', 'Kode Prodi*', 'Angkatan*', 'Semester']));
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return new UploadedFile($path, 'mahasiswa.xlsx', null, null, true);
}

beforeEach(function () {
    Storage::fake('local');
    $this->program = StudyProgram::factory()->create(['code' => 'TMTK']);
    $this->admin = userWithRole(UserRole::AdminLpm);
});

it('validates rows and reports invalid, duplicate and existing records before importing', function () {
    Student::factory()->for($this->program)->create(['nim' => '2511003']);

    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'mahasiswa',
        'file' => studentWorkbook([
            ['2511001', 'Siti Aminah', 'TMTK', '2025', '3'],
            ['2511002', 'Ahmad Fauzi', 'XXXX', '2025', '3'],
            ['2511001', 'Siti Duplikat', 'TMTK', '2025', '3'],
            ['2511003', 'Sudah Ada', 'TMTK', '2025', '3'],
        ]),
    ])->assertRedirect();

    $job = ImportJob::query()->sole();

    expect($job->status)->toBe('validated')
        ->and($job->total_rows)->toBe(4)
        ->and($job->valid_rows)->toBe(2)
        ->and($job->invalid_rows)->toBe(2)
        ->and($job->duplicate_rows)->toBe(1)
        ->and($job->errors()->pluck('message')->all())->toContain('Kode prodi tidak valid', 'Duplikat dengan baris 2 pada file')
        ->and(Student::query()->count())->toBe(1);
});

it('imports valid rows with login accounts after confirmation', function () {
    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'mahasiswa',
        'file' => studentWorkbook([['2511001', 'Siti Aminah', 'TMTK', '2025', '3'], ['2511002', 'Nama', 'SALAH', '2025', '3']]),
    ]);
    $job = ImportJob::query()->sole();

    $this->actingAs($this->admin)->post(route('imports.confirm', $job), ['update_existing' => true])->assertRedirect(route('imports.show', $job));

    expect($job->fresh()->status)->toBe('completed')
        ->and($job->fresh()->imported_rows)->toBe(1)
        ->and(Student::query()->where('nim', '2511001')->value('name'))->toBe('Siti Aminah')
        ->and(User::query()->where('username', '2511001')->first()->hasRole('mahasiswa'))->toBeTrue();
});

it('creates imported accounts quickly and upgrades the initial password hash on first login', function () {
    config(['hashing.bcrypt.rounds' => 10]);
    Hash::forgetDrivers();

    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'mahasiswa',
        'file' => studentWorkbook([['2511001', 'Siti Aminah', 'TMTK', '2025', '3']]),
    ]);
    $this->actingAs($this->admin)->post(route('imports.confirm', ImportJob::query()->sole()));

    $user = User::query()->where('username', '2511001')->firstOrFail();
    expect(Hash::info($user->password)['options']['cost'])->toBe(4);

    auth()->logout();
    $this->post(route('login'), ['login' => '2511001', 'password' => '2511001']);

    $this->assertAuthenticatedAs($user);
    expect(Hash::info($user->fresh()->password)['options']['cost'])->toBe(10)
        ->and($user->fresh()->must_change_password)->toBeTrue();
});

it('rejects rows outside the admin prodi scope', function () {
    $other = StudyProgram::factory()->create(['code' => 'PAI']);
    $admin = userWithRole(UserRole::AdminProdi, ['study_program_id' => $this->program->id]);

    $this->actingAs($admin)->post(route('imports.store'), [
        'type' => 'mahasiswa',
        'file' => studentWorkbook([['2511009', 'Mahasiswa PAI', $other->code, '2025', '1']]),
    ]);

    expect(ImportJob::query()->sole()->errors()->value('message'))->toBe('Prodi di luar cakupan akses Anda');
});

it('fails with a clear message when required columns are missing', function () {
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['NIM', 'Nama']));
    $writer->addRow(Row::fromValues(['1', 'A']));
    $writer->close();

    $this->actingAs($this->admin)->post(route('imports.store'), ['type' => 'mahasiswa', 'file' => new UploadedFile($path, 'x.xlsx', null, null, true)]);

    expect(ImportJob::query()->sole())
        ->status->toBe('failed')
        ->failure_message->toContain('Kode Prodi');
});

it('serves an xlsx template without example data rows whose documented examples import cleanly', function (string $type) {
    $response = $this->actingAs($this->admin)->get(route('imports.template', $type))->assertDownload("template_{$type}.xlsx");
    $columns = app(ImportManager::class)->importer($type)->columns();
    $headers = array_map(fn (array $column): string => $column['label'].($column['required'] ? '*' : ''), $columns);

    // Hanya baris judul: tidak ada data contoh yang bisa ikut terimpor bila lupa dihapus.
    expect(downloadedRows($response))->toBe([$headers])
        ->and(collect(downloadedRows($response, 'Petunjuk'))->pluck(1)->implode(' '))->toContain('Contoh:');

    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues($headers));
    $writer->addRow(Row::fromValues(array_column($columns, 'example')));
    $writer->close();

    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => $type,
        'file' => new UploadedFile($path, "template_{$type}.xlsx", null, null, true),
    ])->assertSessionHasNoErrors();

    expect(ImportJob::query()->sole())
        ->status->toBe('validated')
        ->total_rows->toBe(1);
})->with(['mahasiswa', 'dosen', 'mata_kuliah', 'penugasan_mengajar', 'peserta_kelas']);

it('imports lecturers from a semicolon separated csv', function () {
    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'dosen',
        'file' => UploadedFile::fake()->createWithContent('dosen.csv', "NIDN*;Nama*;Kode Prodi*\n0812345678;Ahmad Fauzi;TMTK\n"),
    ])->assertSessionHasNoErrors();

    $job = ImportJob::query()->sole();
    $this->actingAs($this->admin)->post(route('imports.confirm', $job), ['update_existing' => true]);

    expect($job->fresh()->status)->toBe('completed')
        ->and(Lecturer::query()->where('nidn', '0812345678')->value('name'))->toBe('Ahmad Fauzi')
        ->and(User::query()->where('username', '0812345678')->first()->hasRole('dosen'))->toBeTrue();
});

it('accepts an xlsx file that the server detects as a plain zip archive', function () {
    $path = tempnam(sys_get_temp_dir(), 'zip').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('catatan.txt', 'bukan workbook');
    $zip->close();

    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'dosen',
        'file' => new UploadedFile($path, 'dosen.xlsx', null, null, true),
    ])->assertSessionHasNoErrors();

    expect(ImportJob::query()->sole()->status)->toBe('failed');
});

it('rejects legacy xls files with a clear message', function () {
    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'dosen',
        'file' => UploadedFile::fake()->create('dosen.xls', 20, 'application/vnd.ms-excel'),
    ])->assertSessionHasErrors(['file' => 'Gunakan file Excel (.xlsx) atau CSV. File Excel lama (.xls) perlu disimpan ulang sebagai .xlsx.']);

    expect(ImportJob::query()->count())->toBe(0);
});

it('downloads the validation errors of an import as xlsx', function () {
    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'mahasiswa',
        'file' => studentWorkbook([['2511002', 'Ahmad Fauzi', 'XXXX', '2025', '3']]),
    ]);

    $rows = downloadedRows($this->actingAs($this->admin)->get(route('imports.errors', ImportJob::query()->sole())));

    expect($rows[0])->toBe(['Baris', 'Kolom', 'Nilai', 'Keterangan'])
        ->and($rows[1])->toBe(['2', 'kode_prodi', 'XXXX', 'Kode prodi tidak valid']);
});

/**
 * @param  list<list<mixed>>  $rows
 */
function lecturerWorkbook(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['NIDN/NIDK', 'NUPTK', 'Nama*', 'Kode Prodi*']));
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return new UploadedFile($path, 'dosen.xlsx', null, null, true);
}

it('imports a new lecturer who only has a NUPTK and lets them log in with it', function () {
    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'dosen',
        'file' => lecturerWorkbook([[null, '7350765666130123', 'Dosen Baru', 'TMTK']]),
    ])->assertSessionHasNoErrors();

    $job = ImportJob::query()->sole();
    expect($job->valid_rows)->toBe(1);

    $this->actingAs($this->admin)->post(route('imports.confirm', $job), ['update_existing' => true]);

    $lecturer = Lecturer::query()->where('nuptk', '7350765666130123')->firstOrFail();
    expect($lecturer->nidn)->toBeNull()
        ->and($lecturer->user->username)->toBe('7350765666130123');

    auth()->logout();
    $this->post(route('login'), ['login' => '7350765666130123', 'password' => '7350765666130123']);
    $this->assertAuthenticatedAs($lecturer->user);
});

it('adds the NUPTK to an existing lecturer matched by NIDN and logs in with either number', function () {
    $lecturer = Lecturer::factory()->for($this->program)->create(['nidn' => '0812345678']);
    app(AccountProvisioner::class)->forLecturer($lecturer);

    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'dosen',
        'file' => lecturerWorkbook([['0812345678', '7350765666130123', 'Nama Baru', 'TMTK']]),
    ]);
    $job = ImportJob::query()->sole();
    expect($job->duplicate_rows)->toBe(1);

    $this->actingAs($this->admin)->post(route('imports.confirm', $job), ['update_existing' => true]);

    expect(Lecturer::query()->count())->toBe(1)
        ->and($lecturer->fresh())->nuptk->toBe('7350765666130123')->name->toBe('Nama Baru');

    auth()->logout();
    $this->post(route('login'), ['login' => '7350765666130123', 'password' => '0812345678']);
    $this->assertAuthenticatedAs($lecturer->fresh()->user);
});

it('rejects lecturer rows without any identity number, with a NUPTK stored as a number, or with numbers of two lecturers', function () {
    Lecturer::factory()->for($this->program)->create(['nidn' => '0811111111']);
    Lecturer::factory()->for($this->program)->create(['nidn' => null, 'nuptk' => '1111222233334444']);

    $this->actingAs($this->admin)->post(route('imports.store'), [
        'type' => 'dosen',
        'file' => lecturerWorkbook([
            [null, null, 'Tanpa Nomor', 'TMTK'],
            [null, 7350765666130123, 'Diketik Angka', 'TMTK'],
            [null, '73507656661301', 'Kurang Digit', 'TMTK'],
            ['0811111111', '1111222233334444', 'Dua Dosen', 'TMTK'],
        ]),
    ]);

    $messages = ImportJob::query()->sole()->errors()->orderBy('row_number')->pluck('message')->all();

    expect($messages)->toHaveCount(4)
        ->and($messages[0])->toBe('Isi NIDN/NIDK atau NUPTK (minimal salah satu)')
        ->and($messages[1])->toContain('terbaca sebagai angka')
        ->and($messages[2])->toBe('NUPTK harus 16 digit angka')
        ->and($messages[3])->toBe('NIDN/NIDK dan NUPTK ini tercatat pada dua dosen berbeda');
});

it('formats identity columns of the lecturer template as text so Excel keeps all 16 digits', function () {
    $response = $this->actingAs($this->admin)->get(route('imports.template', 'dosen'));
    $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
    file_put_contents($path, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($path);
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $styles = $zip->getFromName('xl/styles.xml');
    $zip->close();

    expect($styles)->toContain('numFmtId="49"')
        ->and(substr_count($sheet, '<c r="B300"'))->toBe(1)
        ->and(downloadedRows($response))->toHaveCount(1);
});
