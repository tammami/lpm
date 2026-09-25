<?php

namespace App\Enums;

enum QuestionType: string
{
    use HasOptions;

    case Likert = 'likert';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case YesNo = 'yes_no';
    case Rating = 'rating';
    case Numeric = 'numeric';
    case Text = 'text';
    case LongText = 'long_text';
    case Date = 'date';
    case FileUpload = 'file_upload';

    public function label(): string
    {
        return match ($this) {
            self::Likert => 'Skala Likert',
            self::SingleChoice => 'Pilihan Tunggal',
            self::MultipleChoice => 'Pilihan Ganda',
            self::YesNo => 'Ya / Tidak',
            self::Rating => 'Rating Bintang',
            self::Numeric => 'Angka',
            self::Text => 'Teks Singkat',
            self::LongText => 'Teks Panjang',
            self::Date => 'Tanggal',
            self::FileUpload => 'Unggah Berkas',
        };
    }

    /**
     * Tipe yang jawabannya dipilih dari daftar opsi.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Likert, self::SingleChoice, self::MultipleChoice, self::YesNo], true);
    }

    /**
     * Tipe yang dapat menghasilkan skor numerik.
     */
    public function isScorable(): bool
    {
        return in_array($this, [self::Likert, self::SingleChoice, self::MultipleChoice, self::YesNo, self::Rating, self::Numeric], true);
    }
}
