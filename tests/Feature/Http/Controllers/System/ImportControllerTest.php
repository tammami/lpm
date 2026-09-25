<?php

use App\Enums\UserRole;
use App\Models\ImportJob;
use App\Models\Student;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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

it('serves an xlsx template for each import type', function () {
    $this->actingAs($this->admin)
        ->get(route('imports.template', 'dosen'))
        ->assertOk()
        ->assertDownload('template_dosen.xlsx');
});
