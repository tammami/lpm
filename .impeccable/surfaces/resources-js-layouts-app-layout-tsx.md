---
version: 1
slug: "resources-js-layouts-app-layout-tsx"
primary_target: "resources/js/layouts/app-layout.tsx"
related_targets: []
---

# Surface brief: SIMUTU app shell (semua layar aplikasi)

Scope: seluruh layar aplikasi (staf, portal mahasiswa, login, dialog, tabel, grafik). Laporan PDF tetap polos untuk cetak.
Mode: Operate. Tugas harian LPM/pimpinan/prodi/auditor/dosen; mahasiswa mengisi Monev lewat HP.
Constraint: tanpa mode gelap; kontras WCAG AA; struktur, konten, dan perilaku tetap — yang diganti hanya dunia warna & material.

## Direction contract

THESIS: Kaca beku di atas kabut teal–lavender: setiap permukaan (latar, sidebar, kartu, tombol, chip, bar) adalah gradien lembut yang tembus pandang, seperti referensi CoachPro yang dipinned pengguna. Menolak dashboard admin datar (latar abu, kartu putih rata, sidebar gelap).
OWN-WORLD: Latar kabut gradien mint-teal (kiri atas) → aqua pucat → lavender (kanan atas/bawah), tetap saat digulir. Kartu kaca: gradien putih 78%→46%, blur, tepi putih 1px, bayangan lembut ber-offset. Teal dalam (#0f7f86→#16a39a) untuk tombol utama, pill menu aktif, dan banner. Chip ikon bergradien: violet, pink, oranye, teal. Tinta teal gelap, bukan hitam. Status: teal (baik), oranye (peringatan), koral (kritis).
STORY: Pengguna tetap menemukan tugas dan angka secepat sebelumnya; kesan pertama berubah dari "sistem kampus kaku" menjadi "ruang kerja segar & tenang".
FIRST VIEWPORT: Dashboard — sidebar kaca terang di kiri dengan pill teal bergradien untuk menu aktif; header sapaan teal di atas judul; banner hero teal bergradien; baris kartu statistik kaca dengan chip ikon bergradien; grafik tren teal.
FORM: pinned by user (referensi CoachPro, "persis seperti contoh"); pinned direction beats the roll. Seed key: 9e3152d1.
FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
