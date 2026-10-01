<?php

namespace App\Services\Backup;

use App\Services\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Cadangan & pemulihan database MySQL (mysqldump → .sql.gz) di folder penyimpanan lokal.
 * Nama file memuat tanggal & jam pembuatan dan menjadi satu-satunya sumber informasi waktu cadangan.
 */
class DatabaseBackup
{
    public const DIRECTORY = 'backups';

    public const FILENAME_PATTERN = '/^backup_[A-Za-z0-9_]+_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.sql\.gz$/';

    /**
     * Lokasi umum mysqldump bila tidak ada di PATH proses web server.
     */
    private const BINARY_DIRECTORIES = [
        '/usr/bin', '/usr/local/bin', '/usr/local/mysql/bin', '/opt/homebrew/bin', '/opt/homebrew/opt/mysql-client/bin',
        '/Applications/XAMPP/xamppfiles/bin', '/Applications/MAMP/Library/bin', 'C:\\xampp\\mysql\\bin',
    ];

    /**
     * Pola folder berversi di Windows (Herd, Laragon, installer resmi MySQL/MariaDB); {home} = profil pengguna.
     */
    private const WINDOWS_BINARY_PATTERNS = [
        '{home}/.config/herd/bin', '{home}/.config/herd/bin/*/bin', '{home}/.config/herd/bin/*/*/bin',
        '{home}/.config/herd/bin/*/*/*/bin', '{home}/.config/herd/bin/*/*/*/*/bin',
        'C:/laragon/bin/mysql/*/bin', 'C:/Program Files/MySQL/*/bin', 'C:/Program Files/MariaDB*/bin',
    ];

    public function directory(): string
    {
        $path = Storage::disk('local')->path(self::DIRECTORY);
        File::ensureDirectoryExists($path);

        return realpath($path) ?: $path;
    }

    /**
     * Daftar cadangan, terbaru lebih dulu.
     *
     * @return list<array{name: string, size: int, created_at: Carbon}>
     */
    public function all(): array
    {
        return collect(File::files($this->directory()))
            ->map(fn (\SplFileInfo $file): ?array => ($createdAt = $this->createdAt($file->getFilename())) ? [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => $createdAt,
            ] : null)
            ->filter()
            ->sortByDesc('name')
            ->values()
            ->all();
    }

    /**
     * Buat cadangan baru dan kembalikan nama filenya.
     */
    public function create(bool $prune = true): string
    {
        $connection = $this->connection();

        $name = 'backup_'.preg_replace('/[^A-Za-z0-9_]/', '_', (string) $connection['database']).'_'.now()->format('Y-m-d_H-i-s').'.sql.gz';
        $target = $this->directory().DIRECTORY_SEPARATOR.$name;
        $dump = $target.'.tmp';

        try {
            $result = Process::timeout(900)
                ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
                ->run([
                    $this->binary('database.backup.dump_binary', ['mysqldump', 'mariadb-dump'], 'DB_DUMP_BINARY'),
                    '--host='.$connection['host'],
                    '--port='.$connection['port'],
                    '--user='.$connection['username'],
                    '--single-transaction',
                    '--quick',
                    '--routines',
                    '--triggers',
                    '--no-tablespaces',
                    '--default-character-set=utf8mb4',
                    '--result-file='.$dump,
                    $connection['database'],
                ]);

            if ($result->failed() || ! is_file($dump) || filesize($dump) === 0) {
                throw new RuntimeException('mysqldump gagal: '.(trim($result->errorOutput()) ?: 'tidak ada keluaran.'));
            }

            $this->compress($dump, $target);
        } finally {
            File::delete($dump);
        }

        if ($prune) {
            $this->prune();
        }

        return $name;
    }

    /**
     * Pulihkan database dari sebuah cadangan. Kondisi saat ini dicadangkan lebih dulu
     * agar pemulihan dapat dibatalkan; nama cadangan pengaman itu dikembalikan.
     */
    public function restore(string $name): string
    {
        $source = $this->path($name) ?? throw new RuntimeException('File cadangan tidak ditemukan.');
        $connection = $this->connection();
        $client = $this->binary('database.backup.client_binary', ['mysql', 'mariadb'], 'DB_CLIENT_BINARY');
        $safety = $this->create(prune: false);
        $sql = $source.'.restore.tmp';

        try {
            $this->decompress($source, $sql);
            $input = fopen($sql, 'rb');

            $result = Process::timeout(1800)
                ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
                ->input($input)
                ->run([
                    $client,
                    '--host='.$connection['host'],
                    '--port='.$connection['port'],
                    '--user='.$connection['username'],
                    '--default-character-set=utf8mb4',
                    $connection['database'],
                ]);

            if ($result->failed()) {
                throw new RuntimeException('Pemulihan gagal: '.(trim($result->errorOutput()) ?: 'tidak ada keluaran.')." Kondisi sebelum pemulihan tersimpan di {$safety}.");
            }
        } finally {
            if (isset($input) && is_resource($input)) {
                fclose($input);
            }

            File::delete($sql);
        }

        Cache::flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $safety;
    }

    /**
     * Path absolut sebuah cadangan; null bila nama tidak sah atau file tidak ada.
     */
    public function path(string $name): ?string
    {
        if (basename($name) !== $name || ! preg_match(self::FILENAME_PATTERN, $name)) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return is_file($path) ? $path : null;
    }

    public function delete(string $name): bool
    {
        $path = $this->path($name);

        return $path !== null && File::delete($path);
    }

    /**
     * Hapus cadangan terlama yang melebihi jumlah simpanan. Mengembalikan jumlah file yang dihapus.
     */
    public function prune(): int
    {
        $keep = max(1, (int) Settings::get('backup.keep_files', 30));
        $expired = array_slice($this->all(), $keep);

        foreach ($expired as $backup) {
            $this->delete($backup['name']);
        }

        return count($expired);
    }

    private function createdAt(string $name): ?Carbon
    {
        return preg_match(self::FILENAME_PATTERN, $name, $matches)
            ? Carbon::createFromFormat('Y-m-d_H-i-s', $matches[1])
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function connection(): array
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Cadangan hanya mendukung database MySQL/MariaDB.');
        }

        return $connection;
    }

    /**
     * @param  list<string>  $names
     */
    private function binary(string $configKey, array $names, string $envKey): string
    {
        if ($configured = config($configKey)) {
            return $configured;
        }

        $finder = new ExecutableFinder;
        $directories = [
            ...self::BINARY_DIRECTORIES,
            ...array_filter([getenv('HOME') ? getenv('HOME').'/Library/Application Support/Herd/bin' : null]),
            ...$this->windowsDirectories(),
        ];

        foreach ($names as $name) {
            if ($path = $finder->find($name, null, $directories)) {
                return $path;
            }
        }

        throw new RuntimeException("Program {$names[0]} tidak ditemukan di server. Isi {$envKey} pada file .env dengan lokasi lengkapnya.");
    }

    /**
     * Folder kandidat di Windows, versi terbaru lebih dulu.
     *
     * @return list<string>
     */
    private function windowsDirectories(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $home = str_replace('\\', '/', (string) (getenv('USERPROFILE') ?: getenv('HOME')));

        return collect(self::WINDOWS_BINARY_PATTERNS)
            ->reject(fn (string $pattern): bool => $home === '' && str_contains($pattern, '{home}'))
            ->flatMap(function (string $pattern) use ($home): array {
                $directories = glob(str_replace('{home}', $home, $pattern), GLOB_ONLYDIR) ?: [];
                usort($directories, fn (string $a, string $b): int => strnatcasecmp($b, $a));

                return $directories;
            })
            ->values()
            ->all();
    }

    private function decompress(string $source, string $target): void
    {
        $input = gzopen($source, 'rb');
        $output = fopen($target, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException('File cadangan tidak dapat dibaca. Periksa izin folder penyimpanan.');
        }

        while (! gzeof($input)) {
            fwrite($output, (string) gzread($input, 1024 * 512));
        }

        gzclose($input);
        fclose($output);
    }

    private function compress(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($target, 'wb6');

        if ($input === false || $output === false) {
            throw new RuntimeException('File cadangan tidak dapat ditulis. Periksa izin folder penyimpanan.');
        }

        while (! feof($input)) {
            gzwrite($output, (string) fread($input, 1024 * 512));
        }

        fclose($input);
        gzclose($output);
    }
}
