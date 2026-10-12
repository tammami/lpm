<?php

namespace App\Enums;

/**
 * Subjenis karya dosen, mengikuti daftar "Subjenis Karya" pada template Data Karya Dosen LPM.
 */
enum ScientificWorkType: string
{
    use HasOptions;

    case JournalNational = 'journal_national';
    case JournalInternational = 'journal_international';
    case ProceedingNational = 'proceeding_national';
    case ProceedingInternational = 'proceeding_international';
    case TextBook = 'book_text';
    case ReferenceBook = 'book_reference';
    case Monograph = 'book_monograph';
    case BookChapter = 'book_chapter';
    case PopularArticle = 'popular';
    case Copyright = 'ip_copyright';
    case Patent = 'ip_patent';
    case SimplePatent = 'ip_simple_patent';
    case IndustrialDesign = 'ip_industrial_design';
    case AppropriateTechnology = 'appropriate_technology';
    case Art = 'art';
    case Innovation = 'innovation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::JournalNational => 'Jurnal Nasional',
            self::JournalInternational => 'Jurnal Internasional',
            self::ProceedingNational => 'Prosiding Seminar Nasional',
            self::ProceedingInternational => 'Prosiding Seminar Internasional',
            self::TextBook => 'Buku Ajar',
            self::ReferenceBook => 'Buku Referensi',
            self::Monograph => 'Buku Monograf',
            self::BookChapter => 'Book Chapter/Bunga Rampai',
            self::PopularArticle => 'Karya Ilmiah Populer',
            self::Copyright => 'HKI - Hak Cipta',
            self::Patent => 'HKI - Paten',
            self::SimplePatent => 'HKI - Paten Sederhana',
            self::IndustrialDesign => 'HKI - Desain Industri',
            self::AppropriateTechnology => 'Teknologi Tepat Guna',
            self::Art => 'Karya Seni',
            self::Innovation => 'Karya/Produk Inovasi',
            self::Other => 'Karya Lainnya',
        };
    }

    /**
     * Label ringkas untuk grafik dan tabel sempit.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::JournalNational => 'Jurnal nasional',
            self::JournalInternational => 'Jurnal internasional',
            self::ProceedingNational => 'Prosiding nasional',
            self::ProceedingInternational => 'Prosiding internasional',
            self::TextBook => 'Buku ajar',
            self::ReferenceBook => 'Buku referensi',
            self::Monograph => 'Buku monograf',
            self::BookChapter => 'Book chapter',
            self::PopularArticle => 'Ilmiah populer',
            self::Copyright => 'Hak cipta',
            self::Patent => 'Paten',
            self::SimplePatent => 'Paten sederhana',
            self::IndustrialDesign => 'Desain industri',
            self::AppropriateTechnology => 'Teknologi tepat guna',
            self::Art => 'Karya seni',
            self::Innovation => 'Produk inovasi',
            self::Other => 'Karya lainnya',
        };
    }

    /**
     * Jenis Karya. Template tidak memetakan subjenis ke jenis; "Karya Ilmiah Populer" dimasukkan ke artikel.
     */
    public function category(): ScientificWorkCategory
    {
        return match ($this) {
            self::JournalNational, self::JournalInternational, self::ProceedingNational,
            self::ProceedingInternational, self::PopularArticle => ScientificWorkCategory::Article,
            self::TextBook, self::ReferenceBook, self::Monograph, self::BookChapter => ScientificWorkCategory::Book,
            default => ScientificWorkCategory::Other,
        };
    }

    public function isJournal(): bool
    {
        return in_array($this, [self::JournalNational, self::JournalInternational], true);
    }

    public function isProceeding(): bool
    {
        return in_array($this, [self::ProceedingNational, self::ProceedingInternational], true);
    }

    /**
     * Indeksasi/akreditasi wajib dipilih untuk jurnal dan prosiding; selain itu "Tidak Berlaku".
     */
    public function usesIndexation(): bool
    {
        return $this->isJournal() || $this->isProceeding();
    }

    public function isIntellectualProperty(): bool
    {
        return in_array($this, [self::Copyright, self::Patent, self::SimplePatent, self::IndustrialDesign], true);
    }

    /**
     * Kelompok rekap yang saling lepas, untuk grafik bertumpuk (lihat ResearchAnalytics::GROUPS).
     */
    public function recapGroup(): string
    {
        return match (true) {
            $this->isJournal() => 'journal',
            $this->isProceeding() => 'proceeding',
            $this->category() === ScientificWorkCategory::Book => 'book',
            $this->isIntellectualProperty() => 'ip',
            default => 'other',
        };
    }
}
