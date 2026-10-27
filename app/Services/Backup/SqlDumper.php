<?php

namespace App\Services\Backup;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Cadangan & pemulihan MySQL lewat koneksi PHP aplikasi, dipakai bila program mysqldump/mysql
 * tidak tersedia bagi proses web server (mis. layanan yang berjalan dengan akun atau PATH lain).
 * Keluaran setara mysqldump: satu pernyataan INSERT per baris sehingga dapat dipulihkan oleh keduanya.
 */
class SqlDumper
{
    private const ROWS_PER_INSERT = 200;

    public function dump(string $target): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $output = fopen($target, 'wb');

        if ($output === false) {
            throw new RuntimeException('File cadangan tidak dapat ditulis. Periksa izin folder penyimpanan.');
        }

        // Snapshot konsisten hanya bila tidak sedang di dalam transaksi (START TRANSACTION akan meng-commit transaksi berjalan).
        $snapshot = $connection->transactionLevel() === 0;

        try {
            if ($snapshot) {
                $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            }

            fwrite($output, "-- Cadangan SIMUTU (PHP) database `{$connection->getDatabaseName()}` ".now()->toDateTimeString()."\n\n"
                ."/*!40101 SET NAMES utf8mb4 */;\n"
                ."/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n"
                ."/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n"
                ."/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n\n");

            $tables = $connection->select('SHOW FULL TABLES');
            $views = [];

            foreach ($tables as $row) {
                [$name, $type] = array_values((array) $row);

                if ($type === 'VIEW') {
                    $views[] = $name;

                    continue;
                }

                $this->dumpTable($connection, $pdo, $output, $name);
            }

            foreach ($views as $name) {
                $create = array_values((array) $connection->selectOne('SHOW CREATE VIEW '.$this->identifier($name)))[1];
                fwrite($output, 'DROP VIEW IF EXISTS '.$this->identifier($name).";\n{$create};\n\n");
            }

            fwrite($output, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n"
                ."/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n"
                ."/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
        } finally {
            if ($snapshot) {
                $pdo->exec('COMMIT');
            }

            fclose($output);
        }
    }

    /**
     * Jalankan file SQL (hasil mysqldump atau dump()) pernyataan demi pernyataan.
     */
    public function import(string $source): void
    {
        $input = fopen($source, 'rb');

        if ($input === false) {
            throw new RuntimeException('File cadangan tidak dapat dibaca. Periksa izin folder penyimpanan.');
        }

        $pdo = DB::connection()->getPdo();
        $delimiter = ';';
        $statement = '';

        try {
            while (($line = fgets($input)) !== false) {
                $trimmed = rtrim($line, "\r\n");

                if ($statement === '') {
                    if (trim($trimmed) === '' || str_starts_with($trimmed, '--')) {
                        continue;
                    }

                    if (preg_match('/^DELIMITER\s+(\S+)/i', $trimmed, $matches)) {
                        $delimiter = $matches[1];

                        continue;
                    }
                }

                $statement .= $line;

                if (str_ends_with(rtrim($trimmed), $delimiter)) {
                    $sql = substr(rtrim($statement), 0, -strlen($delimiter));
                    $statement = '';

                    if (trim($sql) !== '') {
                        $pdo->exec($sql);
                    }
                }
            }

            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        } finally {
            fclose($input);
        }
    }

    /**
     * @param  resource  $output
     */
    private function dumpTable(Connection $connection, PDO $pdo, $output, string $table): void
    {
        $quoted = $this->identifier($table);
        $create = array_values((array) $connection->selectOne("SHOW CREATE TABLE {$quoted}"))[1];

        fwrite($output, "DROP TABLE IF EXISTS {$quoted};\n{$create};\n\n");

        $primary = collect($connection->select("SHOW KEYS FROM {$quoted} WHERE Key_name = 'PRIMARY'"))
            ->sortBy('Seq_in_index')
            ->map(fn (object $key): string => $this->identifier($key->Column_name))
            ->implode(', ');
        $order = $primary !== '' ? " ORDER BY {$primary}" : '';

        for ($offset = 0; ; $offset += 1000) {
            $rows = $pdo->query("SELECT * FROM {$quoted}{$order} LIMIT 1000 OFFSET {$offset}")->fetchAll(PDO::FETCH_NUM);

            foreach (array_chunk($rows, self::ROWS_PER_INSERT) as $chunk) {
                $values = array_map(
                    fn (array $row): string => '('.implode(',', array_map(fn (mixed $value): string => match (true) {
                        $value === null => 'NULL',
                        is_int($value), is_float($value) => (string) $value,
                        default => $pdo->quote((string) $value),
                    }, $row)).')',
                    $chunk,
                );

                fwrite($output, "INSERT INTO {$quoted} VALUES ".implode(',', $values).";\n");
            }

            if (count($rows) < 1000) {
                break;
            }
        }

        fwrite($output, "\n");
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }
}
