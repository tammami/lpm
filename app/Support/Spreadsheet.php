<?php

namespace App\Support;

use Generator;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Properties;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pembaca & penulis spreadsheet (XLSX/CSV) berbasis OpenSpout — hemat memori untuk file besar.
 */
final class Spreadsheet
{
    public const IMPORT_MAX_MB = 10;

    /**
     * Aturan validasi file impor. Ekstensi menjadi acuan karena XLSX dari sebagian aplikasi
     * (Google Sheets, LibreOffice, WPS) terdeteksi sebagai ZIP/biner oleh pemeriksa MIME.
     *
     * @return list<string>
     */
    public static function uploadRules(int $maxMb = self::IMPORT_MAX_MB): array
    {
        return [
            'required',
            'file',
            'extensions:xlsx,csv',
            'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/x-zip-compressed,application/octet-stream,text/plain,text/csv,application/csv',
            'max:'.UploadLimit::kilobytes($maxMb),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function uploadMessages(int $maxMb = self::IMPORT_MAX_MB): array
    {
        $format = 'Gunakan file Excel (.xlsx) atau CSV. File Excel lama (.xls) perlu disimpan ulang sebagai .xlsx.';

        return [
            'file.required' => 'Pilih file yang akan diimpor.',
            'file.extensions' => $format,
            'file.mimetypes' => $format,
            'file.max' => 'Ukuran file melebihi batas '.UploadLimit::megabytes($maxMb).' MB.',
            'file.uploaded' => 'File gagal diunggah. Ukuran file mungkin melebihi batas '.UploadLimit::megabytes($maxMb).' MB.',
        ];
    }

    /**
     * Baca sheet pertama. Menghasilkan [nomor baris => nilai-nilai sel].
     *
     * @return Generator<int, list<mixed>>
     */
    public static function read(string $path): Generator
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        $reader = $extension === 'csv'
            ? new CsvReader(new CsvOptions(FIELD_DELIMITER: self::detectDelimiter($path)))
            : new XlsxReader;

        $reader->open($path);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $number = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $number++;
                    $values = $row->toArray();
                    $dense = [];

                    for ($index = 0, $last = $values === [] ? -1 : max(array_keys($values)); $index <= $last; $index++) {
                        $dense[] = self::normalizeCell($values[$index] ?? null);
                    }

                    yield $number => $dense;
                }

                break;
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * Unduh XLSX dengan baris judul bergaya.
     *
     * @param  list<string>  $headers
     * @param  iterable<list<mixed>>  $rows
     * @param  list<float>  $widths
     */
    public static function download(string $filename, array $headers, iterable $rows, array $widths = [], ?string $title = null, array $notes = []): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $widths, $title, $notes): void {
            $writer = self::writer();
            $writer->openToFile('php://output');

            $sheet = $writer->getCurrentSheet();
            $sheet->setName(Str::limit($title ?? 'Data', 28, ''));

            foreach ($widths as $index => $width) {
                $sheet->setColumnWidth($width, $index + 1);
            }

            $writer->addRow(Row::fromValuesWithStyle($headers, self::headerStyle(), 22));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues(array_map(fn ($value) => $value instanceof \BackedEnum ? $value->value : $value, $row)));
            }

            if ($notes !== []) {
                $notesSheet = $writer->addNewSheetAndMakeItCurrent();
                $notesSheet->setName('Petunjuk');
                $notesSheet->setColumnWidth(28, 1);
                $notesSheet->setColumnWidth(90, 2);
                $writer->addRow(Row::fromValuesWithStyle(['Kolom', 'Keterangan'], self::headerStyle(), 22));

                foreach ($notes as $column => $note) {
                    $writer->addRow(Row::fromValues([$column, $note]));
                }
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Unduh XLSX berisi beberapa sheet.
     *
     * @param  list<array{title: string, headers: list<string>, rows: iterable<list<mixed>>, widths?: list<float>}>  $sheets
     */
    public static function downloadSheets(string $filename, array $sheets): StreamedResponse
    {
        return response()->streamDownload(function () use ($sheets): void {
            $writer = self::writer();
            $writer->openToFile('php://output');

            foreach ($sheets as $index => $definition) {
                $sheet = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName(Str::limit(str_replace(['/', '\\', '?', '*', '[', ']', ':'], '-', $definition['title']), 28, ''));

                foreach ($definition['widths'] ?? [] as $column => $width) {
                    $sheet->setColumnWidth($width, $column + 1);
                }

                $writer->addRow(Row::fromValuesWithStyle($definition['headers'], self::headerStyle(), 22));

                foreach ($definition['rows'] as $row) {
                    $writer->addRow(Row::fromValues($row));
                }
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * OpenSpout 5 menetapkan properti dokumen lewat Options, bukan setter pada writer.
     */
    private static function writer(): XlsxWriter
    {
        return new XlsxWriter(new XlsxOptions(properties: new Properties(
            title: null,
            application: config('app.name'),
            creator: config('app.name'),
            lastModifiedBy: config('app.name'),
        )));
    }

    public static function headerStyle(): Style
    {
        return (new Style)
            ->withFontBold(true)
            ->withFontColor('FFFFFF')
            ->withBackgroundColor('0F3D2C');
    }

    /**
     * Kunci header yang dinormalisasi (mis. "Kode Prodi*" → "kode_prodi").
     */
    public static function headerKey(mixed $header): string
    {
        return (string) Str::of((string) $header)->replace('*', '')->trim()->lower()->slug('_');
    }

    private static function normalizeCell(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = implode('', array_map(fn ($run): string => is_object($run) && property_exists($run, 'text') ? (string) $run->text : (string) $run, $value));
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return is_int($value) ? (string) $value : $value;
    }

    private static function detectDelimiter(string $path): string
    {
        $line = (string) fgets(fopen($path, 'r'));

        return substr_count($line, ';') > substr_count($line, ',') ? ';' : ',';
    }
}
