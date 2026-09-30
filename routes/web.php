<?php

use App\Http\Controllers\Accreditation\InstrumentController as AccreditationInstrumentController;
use App\Http\Controllers\Accreditation\InstrumentVersionController as AccreditationVersionController;
use App\Http\Controllers\Accreditation\PeriodController;
use App\Http\Controllers\Accreditation\ReadinessController;
use App\Http\Controllers\Accreditation\StructureController;
use App\Http\Controllers\Ami\AuditController;
use App\Http\Controllers\Ami\AuditorController;
use App\Http\Controllers\Ami\AuditProgramController;
use App\Http\Controllers\Ami\FindingController;
use App\Http\Controllers\Ami\ReferenceController;
use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Analytics\MyEvaluationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Evidence\EvidenceCategoryController;
use App\Http\Controllers\Evidence\EvidenceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Improvement\ActionPlanController;
use App\Http\Controllers\Improvement\RecommendationController;
use App\Http\Controllers\Instruments\InstrumentController;
use App\Http\Controllers\Instruments\InstrumentQuestionController;
use App\Http\Controllers\Instruments\InstrumentSectionController;
use App\Http\Controllers\Instruments\InstrumentVersionController;
use App\Http\Controllers\MasterData\AcademicPeriodController;
use App\Http\Controllers\MasterData\AccreditationBodyController;
use App\Http\Controllers\MasterData\CourseClassController;
use App\Http\Controllers\MasterData\CourseController;
use App\Http\Controllers\MasterData\FacultyController;
use App\Http\Controllers\MasterData\InstitutionController;
use App\Http\Controllers\MasterData\LecturerController;
use App\Http\Controllers\MasterData\StudentController;
use App\Http\Controllers\MasterData\StudyProgramController;
use App\Http\Controllers\MasterData\UnitController;
use App\Http\Controllers\Monev\SurveyController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Reports\MasterExportController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\System\AuditLogController;
use App\Http\Controllers\System\BackupController;
use App\Http\Controllers\System\ImportController;
use App\Http\Controllers\System\NotificationController;
use App\Http\Controllers\System\RoleController;
use App\Http\Controllers\System\ScaleController;
use App\Http\Controllers\System\SettingController;
use App\Http\Controllers\System\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/branding/logo', BrandingController::class)->name('branding.logo');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/lupa-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', HomeController::class)->name('home');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profil/kata-sandi', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('/profil/kata-sandi', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Master Data
    |--------------------------------------------------------------------------
    */
    Route::prefix('master')->group(function (): void {
        Route::middleware('permission:organization.manage')->group(function (): void {
            Route::get('institusi', [InstitutionController::class, 'edit'])->name('institution.edit');
            Route::post('institusi', [InstitutionController::class, 'update'])->name('institution.update');
            Route::resource('lembaga-akreditasi', AccreditationBodyController::class)
                ->only(['index', 'store', 'update', 'destroy'])->names('accreditation-bodies')->parameters(['lembaga-akreditasi' => 'accreditationBody']);
            Route::resource('fakultas', FacultyController::class)
                ->only(['store', 'update', 'destroy'])->names('faculties')->parameters(['fakultas' => 'faculty']);
            Route::resource('prodi', StudyProgramController::class)
                ->only(['store', 'update', 'destroy'])->names('study-programs')->parameters(['prodi' => 'studyProgram']);
            Route::resource('unit', UnitController::class)
                ->only(['store', 'update', 'destroy'])->names('units')->parameters(['unit' => 'unit']);
            Route::resource('periode', AcademicPeriodController::class)
                ->only(['store', 'update', 'destroy'])->names('academic-periods')->parameters(['periode' => 'academicPeriod']);
            Route::post('periode/{academicPeriod}/aktifkan', [AcademicPeriodController::class, 'activate'])->name('academic-periods.activate');
        });

        Route::middleware('permission:master.view')->group(function (): void {
            Route::get('fakultas', [FacultyController::class, 'index'])->name('faculties.index');
            Route::get('prodi', [StudyProgramController::class, 'index'])->name('study-programs.index');
            Route::get('unit', [UnitController::class, 'index'])->name('units.index');
            Route::get('periode', [AcademicPeriodController::class, 'index'])->name('academic-periods.index');
            Route::get('dosen', [LecturerController::class, 'index'])->name('lecturers.index');
            Route::get('mahasiswa', [StudentController::class, 'index'])->name('students.index');
            Route::get('mata-kuliah', [CourseController::class, 'index'])->name('courses.index');
            Route::get('kelas', [CourseClassController::class, 'index'])->name('classes.index');
            Route::get('kelas/{class}', [CourseClassController::class, 'show'])->name('classes.show');
            Route::get('ekspor/{type}', MasterExportController::class)->whereIn('type', ['mahasiswa', 'dosen', 'mata-kuliah'])->name('master.export');
        });

        Route::middleware('permission:master.manage')->group(function (): void {
            Route::resource('dosen', LecturerController::class)
                ->only(['store', 'update', 'destroy'])->names('lecturers')->parameters(['dosen' => 'lecturer']);
            Route::post('dosen/{lecturer}/reset-password', [LecturerController::class, 'resetPassword'])->name('lecturers.reset-password');
            Route::resource('mahasiswa', StudentController::class)
                ->only(['store', 'update', 'destroy'])->names('students')->parameters(['mahasiswa' => 'student']);
            Route::post('mahasiswa/{student}/reset-password', [StudentController::class, 'resetPassword'])->name('students.reset-password');
            Route::resource('mata-kuliah', CourseController::class)
                ->only(['store', 'update', 'destroy'])->names('courses')->parameters(['mata-kuliah' => 'course']);
            Route::resource('kelas', CourseClassController::class)
                ->only(['store', 'update', 'destroy'])->names('classes')->parameters(['kelas' => 'class']);
            Route::post('kelas/{class}/dosen', [CourseClassController::class, 'assignLecturer'])->name('classes.lecturers.store');
            Route::delete('kelas/{class}/dosen/{assignment}', [CourseClassController::class, 'removeLecturer'])->name('classes.lecturers.destroy');
            Route::post('kelas/{class}/mahasiswa', [CourseClassController::class, 'enroll'])->name('classes.students.store');
            Route::delete('kelas/{class}/mahasiswa/{student}', [CourseClassController::class, 'unenroll'])->name('classes.students.destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Instrument Engine
    |--------------------------------------------------------------------------
    */
    Route::prefix('instrumen')->group(function (): void {
        Route::middleware('permission:instruments.view')->group(function (): void {
            Route::get('/', [InstrumentController::class, 'index'])->name('instruments.index');
            Route::get('{instrument}', [InstrumentController::class, 'show'])->name('instruments.show');
            Route::get('versi/{version}', [InstrumentVersionController::class, 'show'])->name('instrument-versions.show');
            Route::get('versi/{version}/pratinjau', [InstrumentVersionController::class, 'preview'])->name('instrument-versions.preview');
        });

        Route::post('versi/{version}/status', [InstrumentVersionController::class, 'transition'])->name('instrument-versions.transition');

        Route::middleware('permission:instruments.manage')->group(function (): void {
            Route::post('/', [InstrumentController::class, 'store'])->name('instruments.store');
            Route::put('{instrument}', [InstrumentController::class, 'update'])->name('instruments.update');
            Route::post('{instrument}/arsip', [InstrumentController::class, 'toggleArchive'])->name('instruments.archive');
            Route::delete('{instrument}', [InstrumentController::class, 'destroy'])->name('instruments.destroy');

            Route::put('versi/{version}', [InstrumentVersionController::class, 'update'])->name('instrument-versions.update');
            Route::post('versi/{version}/duplikat', [InstrumentVersionController::class, 'duplicate'])->name('instrument-versions.duplicate');
            Route::delete('versi/{version}', [InstrumentVersionController::class, 'destroy'])->name('instrument-versions.destroy');

            Route::post('versi/{version}/bagian', [InstrumentSectionController::class, 'store'])->name('instrument-sections.store');
            Route::put('bagian/{section}', [InstrumentSectionController::class, 'update'])->name('instrument-sections.update');
            Route::delete('bagian/{section}', [InstrumentSectionController::class, 'destroy'])->name('instrument-sections.destroy');
            Route::post('bagian/{section}/pindah', [InstrumentSectionController::class, 'move'])->name('instrument-sections.move');

            Route::post('bagian/{section}/pertanyaan', [InstrumentQuestionController::class, 'store'])->name('instrument-questions.store');
            Route::put('pertanyaan/{question}', [InstrumentQuestionController::class, 'update'])->name('instrument-questions.update');
            Route::delete('pertanyaan/{question}', [InstrumentQuestionController::class, 'destroy'])->name('instrument-questions.destroy');
            Route::post('pertanyaan/{question}/duplikat', [InstrumentQuestionController::class, 'duplicate'])->name('instrument-questions.duplicate');
            Route::post('pertanyaan/{question}/pindah', [InstrumentQuestionController::class, 'move'])->name('instrument-questions.move');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | e-Monev
    |--------------------------------------------------------------------------
    */
    Route::prefix('monev')->group(function (): void {
        Route::middleware('permission:surveys.manage')->group(function (): void {
            Route::get('kegiatan/baru', [SurveyController::class, 'create'])->name('surveys.create');
            Route::post('kegiatan', [SurveyController::class, 'store'])->name('surveys.store');
            Route::get('kegiatan/{survey}/ubah', [SurveyController::class, 'edit'])->name('surveys.edit');
            Route::put('kegiatan/{survey}', [SurveyController::class, 'update'])->name('surveys.update');
            Route::post('kegiatan/{survey}/status', [SurveyController::class, 'transition'])->name('surveys.transition');
            Route::delete('kegiatan/{survey}', [SurveyController::class, 'destroy'])->name('surveys.destroy');
        });

        Route::middleware('permission:surveys.view')->group(function (): void {
            Route::get('kegiatan', [SurveyController::class, 'index'])->name('surveys.index');
            Route::get('kegiatan/{survey}', [SurveyController::class, 'show'])->name('surveys.show');
            Route::get('kegiatan/{survey}/partisipasi', [SurveyController::class, 'participations'])->name('surveys.participations');
        });

        Route::post('kegiatan/{survey}/partisipasi/{participation}/buka-kembali', [SurveyController::class, 'reopen'])
            ->middleware('permission:surveys.reopen')->name('surveys.participations.reopen');
    });

    /*
    |--------------------------------------------------------------------------
    | Analitik
    |--------------------------------------------------------------------------
    */
    Route::prefix('analitik')->middleware('permission:analytics.view')->group(function (): void {
        Route::get('/', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('dosen/{lecturer}', [AnalyticsController::class, 'lecturer'])->name('analytics.lecturers.show');
    });
    Route::prefix('laporan')->group(function (): void {
        Route::middleware('permission:reports.export')->group(function (): void {
            Route::get('/', [ReportController::class, 'index'])->name('reports.index');
            Route::get('monev/pdf', [ReportController::class, 'monevPdf'])->name('reports.monev.pdf');
            Route::get('monev/excel', [ReportController::class, 'monevExcel'])->name('reports.monev.excel');
            Route::get('monev/respons', [ReportController::class, 'responsesExcel'])->name('reports.monev.responses');
        });
        Route::get('dosen/{lecturer}/pdf', [ReportController::class, 'lecturerPdf'])->name('reports.lecturer.pdf');
    });
    Route::get('hasil-evaluasi-saya', MyEvaluationController::class)->middleware('permission:monev.own_results')->name('my-evaluation.index');

    /*
    |--------------------------------------------------------------------------
    | Impor Data
    |--------------------------------------------------------------------------
    */
    Route::prefix('impor')->middleware('permission:import.manage')->group(function (): void {
        Route::get('/', [ImportController::class, 'index'])->name('imports.index');
        Route::get('template/{type}', [ImportController::class, 'template'])->name('imports.template');
        Route::post('/', [ImportController::class, 'store'])->middleware('throttle:20,1')->name('imports.store');
        Route::get('{import}', [ImportController::class, 'show'])->name('imports.show');
        Route::post('{import}/konfirmasi', [ImportController::class, 'confirm'])->name('imports.confirm');
        Route::get('{import}/error', [ImportController::class, 'errors'])->name('imports.errors');
        Route::delete('{import}', [ImportController::class, 'destroy'])->name('imports.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Sistem
    |--------------------------------------------------------------------------
    */
    Route::prefix('sistem')->group(function (): void {
        Route::middleware('permission:users.manage')->group(function (): void {
            Route::resource('pengguna', UserController::class)->only(['index', 'store', 'update', 'destroy'])
                ->names('users')->parameters(['pengguna' => 'user']);
        });

        Route::middleware('permission:roles.manage')->group(function (): void {
            Route::get('peran', [RoleController::class, 'index'])->name('roles.index');
            Route::put('peran/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::post('peran/{role}/reset', [RoleController::class, 'reset'])->name('roles.reset');
        });

        Route::middleware('permission:settings.manage')->group(function (): void {
            Route::get('pengaturan', [SettingController::class, 'index'])->name('settings.index');
            Route::put('pengaturan', [SettingController::class, 'update'])->name('settings.update');
            Route::get('skala', [ScaleController::class, 'index'])->name('settings.scales');
            Route::post('skala/klasifikasi', [ScaleController::class, 'storeScheme'])->name('settings.schemes.store');
            Route::put('skala/klasifikasi/{scheme}', [ScaleController::class, 'updateScheme'])->name('settings.schemes.update');
            Route::delete('skala/klasifikasi/{scheme}', [ScaleController::class, 'destroyScheme'])->name('settings.schemes.destroy');
            Route::post('skala/preset', [ScaleController::class, 'storeScale'])->name('settings.answer-scales.store');
            Route::put('skala/preset/{scale}', [ScaleController::class, 'updateScale'])->name('settings.answer-scales.update');
            Route::delete('skala/preset/{scale}', [ScaleController::class, 'destroyScale'])->name('settings.answer-scales.destroy');
        });

        Route::get('log-audit', [AuditLogController::class, 'index'])->middleware('permission:audit_logs.view')->name('audit-logs.index');

        Route::prefix('cadangan')->middleware('permission:backups.manage')->group(function (): void {
            Route::get('/', [BackupController::class, 'index'])->name('backups.index');
            Route::post('/', [BackupController::class, 'store'])->middleware('throttle:6,1')->name('backups.store');
            Route::get('{backup}/unduh', [BackupController::class, 'download'])->where('backup', '[A-Za-z0-9_.\\-]+')->name('backups.download');
            Route::post('{backup}/pulihkan', [BackupController::class, 'restore'])->where('backup', '[A-Za-z0-9_.\\-]+')
                ->middleware(['permission:backups.restore', 'throttle:3,1'])->name('backups.restore');
            Route::delete('{backup}', [BackupController::class, 'destroy'])->where('backup', '[A-Za-z0-9_.\\-]+')->name('backups.destroy');
        });
    });

    Route::get('notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifikasi/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    /*
    |--------------------------------------------------------------------------
    | Audit Mutu Internal
    |--------------------------------------------------------------------------
    */
    Route::prefix('ami')->name('ami.')->group(function (): void {
        Route::middleware('permission:ami.manage')->group(function (): void {
            Route::get('referensi', [ReferenceController::class, 'index'])->name('standards.index');
            Route::post('standar', [ReferenceController::class, 'storeStandard'])->name('standards.store');
            Route::put('standar/{standard}', [ReferenceController::class, 'updateStandard'])->name('standards.update');
            Route::delete('standar/{standard}', [ReferenceController::class, 'destroyStandard'])->name('standards.destroy');
            Route::post('kategori-temuan/{severity?}', [ReferenceController::class, 'saveSeverity'])->name('severities.save');
            Route::post('akar-masalah/{rootCause?}', [ReferenceController::class, 'saveRootCause'])->name('root-causes.save');

            Route::get('auditor', [AuditorController::class, 'index'])->name('auditors.index');
            Route::post('auditor', [AuditorController::class, 'store'])->name('auditors.store');
            Route::put('auditor/{auditor}', [AuditorController::class, 'update'])->name('auditors.update');
            Route::delete('auditor/{auditor}', [AuditorController::class, 'destroy'])->name('auditors.destroy');

            Route::post('program', [AuditProgramController::class, 'store'])->name('programs.store');
            Route::put('program/{program}', [AuditProgramController::class, 'update'])->name('programs.update');
            Route::post('program/{program}/status', [AuditProgramController::class, 'transition'])->name('programs.transition');
            Route::delete('program/{program}', [AuditProgramController::class, 'destroy'])->name('programs.destroy');
            Route::post('program/{program}/audit', [AuditController::class, 'store'])->name('audits.store');
            Route::put('audit/{audit}', [AuditController::class, 'update'])->name('audits.update');
            Route::post('audit/{audit}/batal', [AuditController::class, 'cancel'])->name('audits.cancel');
        });

        Route::middleware('permission:ami.view')->group(function (): void {
            Route::get('program', [AuditProgramController::class, 'index'])->name('programs.index');
            Route::get('program/{program}', [AuditProgramController::class, 'show'])->name('programs.show');
        });

        Route::middleware('permission:ami.view|ami.audit|findings.respond')->group(function (): void {
            Route::get('audit', [AuditController::class, 'index'])->name('audits.index');
            Route::get('audit/{audit}', [AuditController::class, 'show'])->name('audits.show');
            Route::get('audit/{audit}/laporan', [AuditController::class, 'report'])->name('audits.report');
            Route::post('audit/{audit}/tahap', [AuditController::class, 'advance'])->name('audits.advance');
            Route::post('audit/{audit}/jawaban', [AuditController::class, 'saveAnswer'])->name('audits.answers.save');
            Route::put('audit/{audit}/laporan', [AuditController::class, 'saveReport'])->name('audits.report.save');
            Route::post('audit/{audit}/temuan', [FindingController::class, 'store'])->name('findings.store');

            Route::get('temuan', [FindingController::class, 'index'])->name('findings.index');
            Route::get('temuan/{finding}', [FindingController::class, 'show'])->name('findings.show');
            Route::put('temuan/{finding}', [FindingController::class, 'update'])->name('findings.update');
            Route::delete('temuan/{finding}', [FindingController::class, 'destroy'])->name('findings.destroy');
            Route::post('temuan/{finding}/terbitkan', [FindingController::class, 'issue'])->name('findings.issue');
            Route::post('temuan/{finding}/tindakan', [FindingController::class, 'storeAction'])->name('findings.actions.store');
            Route::put('temuan/{finding}/tindakan/{action}', [FindingController::class, 'updateAction'])->name('findings.actions.update');
            Route::delete('temuan/{finding}/tindakan/{action}', [FindingController::class, 'destroyAction'])->name('findings.actions.destroy');
            Route::post('temuan/{finding}/ajukan', [FindingController::class, 'submit'])->name('findings.submit');
            Route::post('temuan/{finding}/verifikasi', [FindingController::class, 'verify'])->name('findings.verify');
            Route::post('temuan/{finding}/tutup', [FindingController::class, 'close'])->name('findings.close');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Peningkatan Mutu
    |--------------------------------------------------------------------------
    */
    Route::prefix('peningkatan')->name('improvement.')->middleware('permission:improvement.view|improvement.manage')->group(function (): void {
        Route::get('rekomendasi', [RecommendationController::class, 'index'])->name('recommendations.index');
        Route::get('rencana-aksi', [RecommendationController::class, 'monitoring'])->name('action-plans.index');

        Route::middleware('permission:improvement.manage')->group(function (): void {
            Route::get('rekomendasi/usulan', [RecommendationController::class, 'generate'])->name('recommendations.generate');
            Route::post('rekomendasi/usulan', [RecommendationController::class, 'accept'])->name('recommendations.accept');
            Route::post('rekomendasi', [RecommendationController::class, 'store'])->name('recommendations.store');
            Route::put('rekomendasi/{recommendation}', [RecommendationController::class, 'update'])->name('recommendations.update');
            Route::post('rekomendasi/{recommendation}/tutup', [RecommendationController::class, 'close'])->name('recommendations.close');
            Route::post('rekomendasi/{recommendation}/batal', [RecommendationController::class, 'cancel'])->name('recommendations.cancel');
            Route::delete('rencana-aksi/{plan}', [ActionPlanController::class, 'destroy'])->name('action-plans.destroy');
        });

        Route::get('rekomendasi/{recommendation}', [RecommendationController::class, 'show'])->name('recommendations.show');
        Route::post('rekomendasi/{recommendation}/rencana-aksi', [ActionPlanController::class, 'store'])->name('action-plans.store');
        Route::put('rencana-aksi/{plan}', [ActionPlanController::class, 'update'])->name('action-plans.update');
        Route::post('rencana-aksi/{plan}/tugas', [ActionPlanController::class, 'addTask'])->name('action-plans.tasks.store');
        Route::post('rencana-aksi/{plan}/tugas/{task}', [ActionPlanController::class, 'toggleTask'])->name('action-plans.tasks.toggle');
        Route::post('rencana-aksi/{plan}/verifikasi', [ActionPlanController::class, 'verify'])->middleware('permission:improvement.verify')->name('action-plans.verify');
    });

    /*
    |--------------------------------------------------------------------------
    | Akreditasi
    |--------------------------------------------------------------------------
    | Halaman periode diotorisasi di controller agar PIC di luar peran akreditasi tetap dapat berkontribusi.
    */
    Route::prefix('akreditasi')->name('accreditation.')->group(function (): void {
        Route::get('kesiapan', ReadinessController::class)->middleware('permission:accreditation.view')->name('readiness');
        Route::get('periode', [PeriodController::class, 'index'])->name('periods.index');
        Route::get('periode/{period}', [PeriodController::class, 'show'])->name('periods.show');
        Route::get('periode/{period}/laporan', [PeriodController::class, 'report'])->name('periods.report');
        Route::post('periode/{period}/penilaian', [PeriodController::class, 'assess'])->name('periods.assess');

        Route::middleware('permission:accreditation.manage')->group(function (): void {
            Route::post('periode', [PeriodController::class, 'store'])->name('periods.store');
            Route::put('periode/{period}', [PeriodController::class, 'update'])->name('periods.update');
            Route::post('periode/{period}/lanjut', [PeriodController::class, 'advance'])->name('periods.advance');
            Route::post('periode/{period}/keputusan', [PeriodController::class, 'decide'])->name('periods.decide');
            Route::post('periode/{period}/batal', [PeriodController::class, 'cancel'])->name('periods.cancel');

            Route::get('instrumen', [AccreditationInstrumentController::class, 'index'])->name('instruments.index');
            Route::post('instrumen', [AccreditationInstrumentController::class, 'store'])->name('instruments.store');
            Route::put('instrumen/{instrument}', [AccreditationInstrumentController::class, 'update'])->name('instruments.update');
            Route::delete('instrumen/{instrument}', [AccreditationInstrumentController::class, 'destroy'])->name('instruments.destroy');

            Route::get('versi/{version}', [AccreditationVersionController::class, 'show'])->name('versions.show');
            Route::put('versi/{version}', [AccreditationVersionController::class, 'update'])->name('versions.update');
            Route::post('versi/{version}/status', [AccreditationVersionController::class, 'transition'])->name('versions.transition');
            Route::post('versi/{version}/duplikat', [AccreditationVersionController::class, 'duplicate'])->name('versions.duplicate');
            Route::delete('versi/{version}', [AccreditationVersionController::class, 'destroy'])->name('versions.destroy');
            Route::post('versi/{version}/impor', [AccreditationVersionController::class, 'import'])->name('versions.import');
            Route::get('versi/{version}/ekspor', [AccreditationVersionController::class, 'export'])->name('versions.export');

            Route::post('versi/{version}/kriteria', [StructureController::class, 'storeCriterion'])->name('criteria.store');
            Route::put('kriteria/{criterion}', [StructureController::class, 'updateCriterion'])->name('criteria.update');
            Route::delete('kriteria/{criterion}', [StructureController::class, 'destroyCriterion'])->name('criteria.destroy');
            Route::post('kriteria/{criterion}/indikator', [StructureController::class, 'storeIndicator'])->name('indicators.store');
            Route::put('indikator/{indicator}', [StructureController::class, 'updateIndicator'])->name('indicators.update');
            Route::delete('indikator/{indicator}', [StructureController::class, 'destroyIndicator'])->name('indicators.destroy');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Dokumen Bukti
    |--------------------------------------------------------------------------
    */
    Route::prefix('dokumen')->group(function (): void {
        Route::get('/', [EvidenceController::class, 'index'])->middleware('permission:evidence.view')->name('evidence.index');
        Route::get('cari', [EvidenceController::class, 'search'])->name('evidence.search');
        Route::post('/', [EvidenceController::class, 'store'])->middleware('permission:evidence.manage|findings.respond|improvement.manage|accreditation.manage')->name('evidence.store');
        Route::post('pemetaan', [EvidenceController::class, 'map'])->name('evidence.map');
        Route::delete('pemetaan/{mapping}', [EvidenceController::class, 'unmap'])->name('evidence.unmap');

        Route::middleware('permission:evidence.manage')->group(function (): void {
            Route::get('kategori', [EvidenceCategoryController::class, 'index'])->name('evidence-categories.index');
            Route::post('kategori', [EvidenceCategoryController::class, 'store'])->name('evidence-categories.store');
            Route::put('kategori/{category}', [EvidenceCategoryController::class, 'update'])->name('evidence-categories.update');
            Route::delete('kategori/{category}', [EvidenceCategoryController::class, 'destroy'])->name('evidence-categories.destroy');
        });

        Route::get('{evidence}', [EvidenceController::class, 'show'])->name('evidence.show');
        Route::get('{evidence}/unduh/{version?}', [EvidenceController::class, 'download'])->name('evidence.download');
        Route::put('{evidence}', [EvidenceController::class, 'update'])->name('evidence.update');
        Route::post('{evidence}/versi', [EvidenceController::class, 'addVersion'])->name('evidence.versions.store');
        Route::post('{evidence}/verifikasi', [EvidenceController::class, 'verify'])->middleware('permission:evidence.verify')->name('evidence.verify');
        Route::delete('{evidence}', [EvidenceController::class, 'destroy'])->name('evidence.destroy');
    });

    Route::prefix('portal')->name('portal.')->middleware('permission:monev.fill')->group(function (): void {
        Route::get('/', [PortalController::class, 'home'])->name('home');
        Route::get('survei/{survey}', [PortalController::class, 'show'])->name('surveys.show');
        Route::post('survei/{survey}', [PortalController::class, 'submit'])->middleware('throttle:30,1')->name('surveys.submit');
    });
});
