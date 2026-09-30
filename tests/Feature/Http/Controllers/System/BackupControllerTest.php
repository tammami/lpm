<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Services\Backup\DatabaseBackup;
use App\Services\Settings;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Tiru mysqldump: tulis isi dump ke berkas --result-file.
 */
function fakeDump(string $contents = "-- MySQL dump\nCREATE TABLE `users` (`id` int);\n"): void
{
    test()->restoredSql = null;

    Process::fake(function (PendingProcess $process) use ($contents) {
        $target = collect($process->command)->first(fn (string $argument): bool => str_starts_with($argument, '--result-file='));

        if ($target === null) {
            test()->restoredSql = stream_get_contents($process->input);

            return Process::result();
        }

        file_put_contents(substr($target, strlen('--result-file=')), $contents);

        return Process::result();
    });
}

beforeEach(function () {
    Storage::fake('local');
    config(['database.backup.dump_binary' => 'mysqldump']);
    $this->admin = userWithRole(UserRole::AdminLpm);
});

it('creates a compressed backup named after the database and the current date', function () {
    fakeDump();
    $this->travelTo('2026-09-30 14:05:09');

    $this->actingAs($this->admin)->from(route('backups.index'))->post(route('backups.store'))->assertRedirect(route('backups.index'));

    $name = 'backup_'.config('database.connections.mysql.database').'_2026-09-30_14-05-09.sql.gz';
    $backups = app(DatabaseBackup::class);

    expect(array_column($backups->all(), 'name'))->toBe([$name])
        ->and(gzdecode(file_get_contents($backups->path($name))))->toContain('CREATE TABLE `users`')
        ->and(glob($backups->directory().'/*.tmp'))->toBeEmpty()
        ->and(AuditLog::query()->where('event', 'backed_up')->exists())->toBeTrue();

    Process::assertRan(fn (PendingProcess $process): bool => in_array('--single-transaction', $process->command, true)
        && ! str_contains(implode(' ', $process->command), 'password'));
});

it('shows the backup folder and the existing backups to the admin', function () {
    fakeDump();
    $name = app(DatabaseBackup::class)->create();

    $this->actingAs($this->admin)->get(route('backups.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('system/backups')
            ->where('directory', app(DatabaseBackup::class)->directory())
            ->where('backups.0.name', $name)
            ->has('backups', 1));
});

it('reports a failed dump and leaves no file behind', function () {
    Process::fake(fn () => Process::result(errorOutput: 'Access denied for user', exitCode: 2));

    $this->actingAs($this->admin)->from(route('backups.index'))->post(route('backups.store'))->assertRedirect(route('backups.index'));

    expect(app(DatabaseBackup::class)->all())->toBeEmpty()
        ->and(glob(app(DatabaseBackup::class)->directory().'/*'))->toBeEmpty();
});

it('keeps only the configured number of newest backups', function () {
    fakeDump();
    Settings::set('backup.keep_files', 2);
    $backups = app(DatabaseBackup::class);

    foreach (['2026-09-28 01:00:00', '2026-09-29 01:00:00', '2026-09-30 01:00:00'] as $moment) {
        $this->travelTo($moment);
        $backups->create();
    }

    expect(array_map(fn (array $backup): string => $backup['created_at']->toDateString(), $backups->all()))
        ->toBe(['2026-09-30', '2026-09-29']);
});

it('downloads and deletes a backup', function () {
    fakeDump();
    $name = app(DatabaseBackup::class)->create();

    $this->actingAs($this->admin)->get(route('backups.download', $name))->assertOk()->assertDownload($name);
    $this->actingAs($this->admin)->delete(route('backups.destroy', $name))->assertRedirect();

    expect(app(DatabaseBackup::class)->all())->toBeEmpty();
});

it('only serves files that follow the backup naming pattern', function () {
    Storage::disk('local')->put('backups/catatan.sql.gz', 'x');
    Storage::disk('local')->put('rahasia.txt', 'x');

    $this->actingAs($this->admin)->get(route('backups.download', 'catatan.sql.gz'))->assertNotFound();
    $this->actingAs($this->admin)->get('/sistem/cadangan/..%2Frahasia.txt/unduh')->assertNotFound();
    $this->actingAs($this->admin)->delete(route('backups.destroy', 'catatan.sql.gz'))->assertNotFound();

    expect(Storage::disk('local')->exists('backups/catatan.sql.gz'))->toBeTrue();
});

it('forbids backups for roles without the permission', function () {
    $prodi = userWithRole(UserRole::AdminProdi);

    $this->actingAs($prodi)->get(route('backups.index'))->assertForbidden();
    $this->actingAs($prodi)->post(route('backups.store'))->assertForbidden();
});

it('creates a backup from the terminal command', function () {
    fakeDump();

    $this->artisan('backup:database')->assertSuccessful();

    expect(app(DatabaseBackup::class)->all())->toHaveCount(1);
});

it('restores a backup for a superadmin after saving the current state first', function () {
    fakeDump("-- MySQL dump\nCREATE TABLE `pulih` (`id` int);\n");
    config(['database.backup.client_binary' => 'mysql']);
    $backups = app(DatabaseBackup::class);
    $this->travelTo('2026-09-29 01:00:00');
    $name = $backups->create();
    $this->travelTo('2026-09-30 09:00:00');

    $this->actingAs(userWithRole(UserRole::Superadmin))->from(route('backups.index'))
        ->post(route('backups.restore', $name), ['confirmation' => config('database.connections.mysql.database')])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('backups.index'));

    expect(array_map(fn (array $backup): string => $backup['created_at']->toDateTimeString(), $backups->all()))
        ->toBe(['2026-09-30 09:00:00', '2026-09-29 01:00:00'])
        ->and(glob($backups->directory().'/*.tmp'))->toBeEmpty()
        ->and(AuditLog::query()->where('event', 'restored')->exists())->toBeTrue()
        ->and($this->restoredSql)->toBe("-- MySQL dump\nCREATE TABLE `pulih` (`id` int);\n");

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'mysql'
        && end($process->command) === config('database.connections.mysql.database'));
});

it('refuses to restore without the exact database name', function () {
    fakeDump();
    $name = app(DatabaseBackup::class)->create();

    $this->actingAs(userWithRole(UserRole::Superadmin))->post(route('backups.restore', $name), ['confirmation' => 'salah'])
        ->assertSessionHasErrors(['confirmation' => 'Nama database tidak cocok.']);

    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
});

it('lets only superadmin restore', function () {
    fakeDump();
    $name = app(DatabaseBackup::class)->create();

    $this->actingAs($this->admin)->post(route('backups.restore', $name), ['confirmation' => config('database.connections.mysql.database')])
        ->assertForbidden();

    $this->actingAs($this->admin)->get(route('backups.index'))->assertInertia(fn ($page) => $page->where('canRestore', false));
});

it('reports a failed restore and points to the safety backup', function () {
    Process::fake(function (PendingProcess $process) {
        $target = collect($process->command)->first(fn (string $argument): bool => str_starts_with($argument, '--result-file='));

        if ($target === null) {
            return Process::result(errorOutput: 'ERROR 1064 (42000)', exitCode: 1);
        }

        file_put_contents(substr($target, strlen('--result-file=')), "-- MySQL dump\n");

        return Process::result();
    });
    config(['database.backup.client_binary' => 'mysql']);
    $name = app(DatabaseBackup::class)->create();
    $this->travel(1)->minutes();

    $this->artisan('backup:restore', ['file' => $name, '--force' => true])
        ->expectsOutputToContain('Pemulihan gagal: ERROR 1064')
        ->assertFailed();

    expect(app(DatabaseBackup::class)->all())->toHaveCount(2);
});
