<?php

namespace App\Enums;

/**
 * Indeksasi/Akreditasi publikasi, mengikuti daftar dropdown template Data Karya Dosen LPM.
 * Peringkat/Kuartil diturunkan dari sini sehingga tidak perlu diisi dua kali.
 */
enum ScientificWorkIndexation: string
{
    use HasOptions;

    case NotAccredited = 'not_accredited';
    case Sinta1 = 'sinta_1';
    case Sinta2 = 'sinta_2';
    case Sinta3 = 'sinta_3';
    case Sinta4 = 'sinta_4';
    case Sinta5 = 'sinta_5';
    case Sinta6 = 'sinta_6';
    case ScopusQ1 = 'scopus_q1';
    case ScopusQ2 = 'scopus_q2';
    case ScopusQ3 = 'scopus_q3';
    case ScopusQ4 = 'scopus_q4';
    case ScopusNoQuartile = 'scopus_no_quartile';
    case WosScie = 'wos_scie';
    case WosSsci = 'wos_ssci';
    case WosAhci = 'wos_ahci';
    case WosEsci = 'wos_esci';
    case Doaj = 'doaj';
    case Ebsco = 'ebsco';
    case ProQuest = 'proquest';
    case Dimensions = 'dimensions';
    case Aci = 'aci';
    case Garuda = 'garuda';
    case SintaIndexed = 'sinta_indexed';
    case Moraref = 'moraref';
    case GoogleScholar = 'google_scholar';
    case Crossref = 'crossref';
    case IndexCopernicus = 'copernicus';
    case OtherInternational = 'other_international';
    case OtherNational = 'other_national';
    case NotIndexed = 'not_indexed';
    case NotApplicable = 'not_applicable';

    /**
     * Kelompok indeksasi untuk filter dan rekap.
     *
     * @var array<string, string>
     */
    public const FAMILIES = [
        'sinta' => 'SINTA',
        'scopus' => 'Scopus',
        'wos' => 'Web of Science',
        'other' => 'Indeks lain',
        'none' => 'Belum terakreditasi / tidak terindeks',
    ];

    public function label(): string
    {
        return match ($this) {
            self::NotAccredited => 'Belum Terakreditasi',
            self::Sinta1 => 'Sinta 1',
            self::Sinta2 => 'Sinta 2',
            self::Sinta3 => 'Sinta 3',
            self::Sinta4 => 'Sinta 4',
            self::Sinta5 => 'Sinta 5',
            self::Sinta6 => 'Sinta 6',
            self::ScopusQ1 => 'Scopus Q1',
            self::ScopusQ2 => 'Scopus Q2',
            self::ScopusQ3 => 'Scopus Q3',
            self::ScopusQ4 => 'Scopus Q4',
            self::ScopusNoQuartile => 'Scopus - Belum/Tidak Ada Kuartil',
            self::WosScie => 'Web of Science - SCIE',
            self::WosSsci => 'Web of Science - SSCI',
            self::WosAhci => 'Web of Science - AHCI',
            self::WosEsci => 'Web of Science - ESCI',
            self::Doaj => 'DOAJ',
            self::Ebsco => 'EBSCO',
            self::ProQuest => 'ProQuest',
            self::Dimensions => 'Dimensions',
            self::Aci => 'ASEAN Citation Index (ACI)',
            self::Garuda => 'GARUDA',
            self::SintaIndexed => 'SINTA (Indeksasi)',
            self::Moraref => 'Moraref',
            self::GoogleScholar => 'Google Scholar',
            self::Crossref => 'Crossref',
            self::IndexCopernicus => 'Index Copernicus',
            self::OtherInternational => 'Indeks Internasional Lainnya',
            self::OtherNational => 'Indeks Nasional Lainnya',
            self::NotIndexed => 'Tidak Terindeks',
            self::NotApplicable => 'Tidak Berlaku',
        };
    }

    /**
     * Kelompok seperti sheet "Klasifikasi Otomatis": "SINTA (Indeksasi)" ikut terhitung Sinta
     * karena rumus template mencocokkan awalan "Sinta*" tanpa membedakan huruf besar/kecil.
     */
    public function family(): string
    {
        return match (true) {
            str_starts_with($this->value, 'sinta') => 'sinta',
            str_starts_with($this->value, 'scopus') => 'scopus',
            str_starts_with($this->value, 'wos') => 'wos',
            in_array($this, [self::NotAccredited, self::NotIndexed, self::NotApplicable], true) => 'none',
            default => 'other',
        };
    }

    /**
     * Peringkat/Kuartil sesuai dropdown template.
     */
    public function rank(): string
    {
        return match (true) {
            $this === self::SintaIndexed => 'Tidak Berlaku',
            str_starts_with($this->value, 'sinta_') => 'S'.substr($this->value, -1),
            $this === self::ScopusNoQuartile => 'Terindeks tanpa kuartil',
            str_starts_with($this->value, 'scopus_q') => strtoupper(substr($this->value, -2)),
            str_starts_with($this->value, 'wos_') => strtoupper(substr($this->value, 4)),
            default => 'Tidak Berlaku',
        };
    }

    /**
     * Rincian yang ditampilkan pada ringkasan akreditasi/indeksasi (Rekap LPM).
     *
     * @return list<self>
     */
    public static function summaryCases(): array
    {
        return [
            self::Sinta1, self::Sinta2, self::Sinta3, self::Sinta4, self::Sinta5, self::Sinta6,
            self::ScopusQ1, self::ScopusQ2, self::ScopusQ3, self::ScopusQ4,
            self::WosScie, self::WosSsci, self::WosAhci, self::WosEsci,
        ];
    }

    /**
     * Opsi dikelompokkan per keluarga indeksasi untuk dropdown.
     *
     * @return list<array{value: string, label: string, group: string}>
     */
    public static function groupedOptions(): array
    {
        return array_map(fn (self $case): array => [
            'value' => $case->value,
            'label' => $case->label(),
            'group' => self::FAMILIES[$case->family()],
        ], self::cases());
    }
}
