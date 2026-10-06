<?php

namespace App\Enums;

/**
 * Jenis karya ilmiah dosen, mengikuti pengelompokan publikasi pada LKPS.
 */
enum ScientificWorkType: string
{
    use HasOptions;

    case JournalNational = 'journal_national';
    case JournalAccredited = 'journal_accredited';
    case JournalInternational = 'journal_international';
    case JournalReputable = 'journal_reputable';
    case ProceedingNational = 'proceeding_national';
    case ProceedingInternational = 'proceeding_international';
    case Book = 'book';
    case BookChapter = 'book_chapter';
    case IntellectualProperty = 'ip';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::JournalNational => 'Jurnal nasional (belum terakreditasi)',
            self::JournalAccredited => 'Jurnal nasional terakreditasi (SINTA)',
            self::JournalInternational => 'Jurnal internasional',
            self::JournalReputable => 'Jurnal internasional bereputasi (Scopus/WoS)',
            self::ProceedingNational => 'Prosiding seminar nasional',
            self::ProceedingInternational => 'Prosiding seminar internasional',
            self::Book => 'Buku (ajar/referensi/monograf)',
            self::BookChapter => 'Bab buku (book chapter)',
            self::IntellectualProperty => 'HKI (hak cipta/paten)',
            self::Other => 'Karya lain (media massa, policy brief, dll.)',
        };
    }

    /**
     * Label ringkas untuk grafik dan tabel sempit.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::JournalNational => 'Jurnal nasional',
            self::JournalAccredited => 'Jurnal SINTA',
            self::JournalInternational => 'Jurnal internasional',
            self::JournalReputable => 'Jurnal bereputasi',
            self::ProceedingNational => 'Prosiding nasional',
            self::ProceedingInternational => 'Prosiding internasional',
            self::Book => 'Buku',
            self::BookChapter => 'Bab buku',
            self::IntellectualProperty => 'HKI',
            self::Other => 'Karya lain',
        };
    }

    /**
     * Peringkat/indeksasi yang wajib dipilih untuk jenis ini (kosong = tidak berlaku).
     *
     * @return array<string, string>
     */
    public function ranks(): array
    {
        return match ($this) {
            self::JournalAccredited => ['S1' => 'SINTA 1', 'S2' => 'SINTA 2', 'S3' => 'SINTA 3', 'S4' => 'SINTA 4', 'S5' => 'SINTA 5', 'S6' => 'SINTA 6'],
            self::JournalReputable => ['Q1' => 'Q1', 'Q2' => 'Q2', 'Q3' => 'Q3', 'Q4' => 'Q4', 'indexed' => 'Terindeks, tanpa kuartil'],
            default => [],
        };
    }

    /**
     * Seluruh peringkat lintas jenis, untuk menampilkan label.
     *
     * @return array<string, string>
     */
    public static function allRanks(): array
    {
        return [...self::JournalAccredited->ranks(), ...self::JournalReputable->ranks()];
    }

    public function isJournal(): bool
    {
        return in_array($this, [self::JournalNational, self::JournalAccredited, self::JournalInternational, self::JournalReputable], true);
    }
}
