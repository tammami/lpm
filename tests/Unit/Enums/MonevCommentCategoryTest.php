<?php

use App\Enums\MonevCommentCategory;

it('groups monev suggestions by keyword', function (string $text, MonevCommentCategory $expected) {
    expect(MonevCommentCategory::classify($text))->toBe($expected);
})->with([
    'metode' => ['Penjelasannya terlalu cepat, mohon lebih banyak contoh.', MonevCommentCategory::Method],
    'materi' => ['Materi dan slide sebaiknya dibagikan sebelum kuliah.', MonevCommentCategory::Material],
    'disiplin' => ['Dosen sering terlambat masuk kelas.', MonevCommentCategory::Discipline],
    'sikap' => ['Bapak sangat ramah dan sabar.', MonevCommentCategory::Attitude],
    'penilaian' => ['Nilai tugas tolong diumumkan lebih cepat.', MonevCommentCategory::Assessment],
    'sarana' => ['AC di ruang kelas rusak, proyektor juga.', MonevCommentCategory::Facility],
    'apresiasi' => ['Terima kasih, pertahankan!', MonevCommentCategory::Appreciation],
    'kata pendek tidak salah cocok' => ['Acara seminar kemarin seru', MonevCommentCategory::Other],
    'kosong' => ['-', MonevCommentCategory::Empty],
    'tidak ada' => ['Tidak ada saran.', MonevCommentCategory::Empty],
]);
