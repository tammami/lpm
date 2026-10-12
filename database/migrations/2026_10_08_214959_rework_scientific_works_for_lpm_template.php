<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemetaan jenis lama → subjenis template beserta indeksasi bawaannya.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const TYPES = [
        'journal_national' => ['journal_national', 'not_accredited'],
        'journal_accredited' => ['journal_national', 'sinta'],
        'journal_international' => ['journal_international', 'other_international'],
        'journal_reputable' => ['journal_international', 'scopus'],
        'proceeding_national' => ['proceeding_national', 'not_indexed'],
        'proceeding_international' => ['proceeding_international', 'not_indexed'],
        'book' => ['book_reference', 'not_applicable'],
        'book_chapter' => ['book_chapter', 'not_applicable'],
        'ip' => ['ip_copyright', 'not_applicable'],
        'other' => ['other', 'not_applicable'],
    ];

    private const FUNDINGS = [
        'internal' => 'internal_grant',
        'government' => 'external_grant',
        'external' => 'partner',
        'foreign' => 'external_grant',
    ];

    /**
     * Menyelaraskan karya ilmiah dengan template Data Karya Dosen LPM: subjenis, indeksasi, tautan bukti, HKI, relevansi.
     */
    public function up(): void
    {
        Schema::table('scientific_works', function (Blueprint $table) {
            $table->string('indexation', 30)->nullable()->after('venue');
            $table->unsignedTinyInteger('author_count')->nullable()->after('author_role');
            $table->string('doi', 150)->nullable()->after('identifier');
            $table->string('evidence_url', 1000)->nullable()->after('url');
            $table->string('ip_number', 100)->nullable()->after('evidence_url');
            $table->string('ip_status', 20)->nullable()->after('ip_number');
            $table->boolean('relevant_to_program')->nullable()->after('funding');
            $table->boolean('relevant_to_roadmap')->nullable()->after('relevant_to_program');
        });

        DB::table('scientific_works')->orderBy('id')->each(function (object $work): void {
            [$type, $indexation] = self::TYPES[$work->type] ?? [$work->type, 'not_applicable'];

            $indexation = match ($indexation) {
                'sinta' => $work->rank ? 'sinta_'.substr($work->rank, -1) : 'sinta_indexed',
                'scopus' => $work->rank && str_starts_with($work->rank, 'Q') ? 'scopus_'.strtolower($work->rank) : 'scopus_no_quartile',
                default => $indexation,
            };

            DB::table('scientific_works')->where('id', $work->id)->update([
                'type' => $type,
                'indexation' => $indexation,
                'author_role' => $work->author_role === 'member' ? 'other' : $work->author_role,
                'funding' => self::FUNDINGS[$work->funding] ?? $work->funding,
                // DOI dulu diisi pada kolom tautan.
                'doi' => preg_match('/^10\.\d{4,9}\//', (string) $work->url) ? $work->url : null,
                'url' => preg_match('/^10\.\d{4,9}\//', (string) $work->url) ? null : $work->url,
            ]);
        });

        Schema::table('scientific_works', function (Blueprint $table) {
            $table->dropColumn('rank');
            $table->index('indexation');
        });
    }

    /**
     * Rollback hanya memulihkan struktur; rincian baru (HKI, relevansi, tautan bukti) tidak dapat dikembalikan.
     */
    public function down(): void
    {
        Schema::table('scientific_works', function (Blueprint $table) {
            $table->dropIndex(['indexation']);
            $table->string('rank', 20)->nullable()->after('venue');
        });

        Schema::table('scientific_works', function (Blueprint $table) {
            $table->dropColumn(['indexation', 'author_count', 'doi', 'evidence_url', 'ip_number', 'ip_status', 'relevant_to_program', 'relevant_to_roadmap']);
        });
    }
};
