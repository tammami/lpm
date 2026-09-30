<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Konfigurasi sistem yang dapat diubah admin tanpa deploy (ambang minimum respons, dll.).
 */
class Settings
{
    private const CACHE_KEY = 'system_settings';

    /**
     * Nilai bawaan beserta metadata untuk halaman pengaturan.
     *
     * @return array<string, array{value: mixed, group: string, label: string, type: string, help?: string}>
     */
    public static function definitions(): array
    {
        return [
            'monev.min_responses' => [
                'value' => 5, 'group' => 'monev', 'type' => 'number',
                'label' => 'Minimum jumlah respons',
                'help' => 'Hasil per dosen/kelas di bawah ambang ini disembunyikan untuk menjaga anonimitas.',
            ],
            'monev.low_score_threshold' => [
                'value' => 3.0, 'group' => 'monev', 'type' => 'decimal',
                'label' => 'Ambang skor rendah',
                'help' => 'Skor di bawah nilai ini ditandai perlu perhatian dan memicu rekomendasi.',
            ],
            'monev.show_comments_to_lecturer' => [
                'value' => true, 'group' => 'monev', 'type' => 'boolean',
                'label' => 'Tampilkan komentar mahasiswa kepada dosen',
                'help' => 'Hanya komentar pada butir yang diizinkan (visible to evaluatee) yang ditampilkan.',
            ],
            'monev.reminder_days_before_close' => [
                'value' => 3, 'group' => 'monev', 'type' => 'number',
                'label' => 'Pengingat sebelum Monev ditutup (hari)',
            ],
            'improvement.default_due_days' => [
                'value' => 30, 'group' => 'improvement', 'type' => 'number',
                'label' => 'Tenggat default tindak lanjut (hari)',
            ],
            'accreditation.expiry_warning_days' => [
                'value' => 180, 'group' => 'accreditation', 'type' => 'number',
                'label' => 'Peringatan masa berlaku akreditasi (hari)',
            ],
            'report.signature_city' => [
                'value' => 'Lombok Timur', 'group' => 'report', 'type' => 'text',
                'label' => 'Kota pada area tanda tangan laporan',
            ],
            'evidence.max_upload_mb' => [
                'value' => 20, 'group' => 'evidence', 'type' => 'number',
                'label' => 'Ukuran maksimum unggah dokumen (MB)',
            ],
            'backup.keep_files' => [
                'value' => 30, 'group' => 'backup', 'type' => 'number',
                'label' => 'Jumlah file cadangan yang disimpan',
                'help' => 'Cadangan terlama dihapus otomatis setelah jumlah ini terlampaui.',
            ],
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = static::all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $defaults = array_map(fn (array $definition): mixed => $definition['value'], static::definitions());
            $stored = SystemSetting::query()->pluck('value', 'key')->all();

            return array_merge($defaults, $stored);
        });
    }

    public static function set(string $key, mixed $value): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => static::definitions()[$key]['group'] ?? 'general'],
        );

        Cache::forget(self::CACHE_KEY);
    }
}
