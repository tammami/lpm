<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Support\Spreadsheet;
use App\Support\TableQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor master data ke Excel sesuai filter halaman dan cakupan pengguna.
 */
class MasterExportController extends Controller
{
    public function __invoke(Request $request, string $type): StreamedResponse
    {
        $user = $request->user();
        AuditLogger::log('exported', 'master', null, "Ekspor data {$type}");

        return match ($type) {
            'mahasiswa' => Spreadsheet::download(
                'mahasiswa-'.now()->format('Ymd').'.xlsx',
                ['NIM', 'Nama', 'Kode Prodi', 'Program Studi', 'Angkatan', 'Semester', 'Jenis Kelamin', 'Email', 'No HP', 'Status'],
                TableQuery::for(Student::query()->visibleTo($user)->with('studyProgram'), $request)
                    ->search(['name', 'nim', 'email'])
                    ->filter(['study_program_id' => 'study_program_id', 'entry_year' => 'entry_year', 'status' => 'status'])
                    ->sort(['name', 'nim'], 'nim')
                    ->builder()->lazy()
                    ->map(fn (Student $s): array => [$s->nim, $s->name, $s->studyProgram?->code, $s->studyProgram?->full_name, $s->entry_year, $s->semester, $s->gender, $s->email, $s->phone, $s->status->value]),
                [14, 30, 12, 32, 10, 10, 12, 30, 16, 12],
                'Mahasiswa',
            ),
            'dosen' => Spreadsheet::download(
                'dosen-'.now()->format('Ymd').'.xlsx',
                ['NIDN', 'Nama', 'Gelar Depan', 'Gelar Belakang', 'Kode Prodi', 'Homebase', 'Jabatan Fungsional', 'Status Kepegawaian', 'Email', 'No HP'],
                TableQuery::for(Lecturer::query()->visibleTo($user)->with('studyProgram'), $request)
                    ->search(['name', 'nidn', 'nip', 'email'])
                    ->filter(['study_program_id' => 'study_program_id', 'academic_rank' => 'academic_rank', 'employment_status' => 'employment_status'])
                    ->sort(['name'], 'name')
                    ->builder()->lazy()
                    ->map(fn (Lecturer $l): array => [$l->nidn, $l->name, $l->front_title, $l->back_title, $l->studyProgram?->code, $l->studyProgram?->full_name, $l->academic_rank, $l->employment_status, $l->email, $l->phone]),
                [14, 30, 10, 16, 12, 32, 18, 18, 30, 16],
                'Dosen',
            ),
            'mata-kuliah' => Spreadsheet::download(
                'mata-kuliah-'.now()->format('Ymd').'.xlsx',
                ['Kode MK', 'Nama', 'Kode Prodi', 'Program Studi', 'SKS', 'Semester', 'Sifat', 'Aktif'],
                TableQuery::for(Course::query()->visibleTo($user)->with('studyProgram'), $request)
                    ->search(['name', 'code'])
                    ->filter(['study_program_id' => 'study_program_id', 'semester' => 'semester', 'type' => 'type'])
                    ->sort(['code'], 'code')
                    ->builder()->lazy()
                    ->map(fn (Course $c): array => [$c->code, $c->name, $c->studyProgram?->code, $c->studyProgram?->full_name, $c->credits, $c->semester, $c->type, $c->is_active ? 'ya' : 'tidak']),
                [14, 36, 12, 32, 8, 10, 10, 8],
                'Mata Kuliah',
            ),
            default => abort(404),
        };
    }
}
