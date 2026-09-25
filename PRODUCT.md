# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **LPM (admin & staf penjaminan mutu)**: menyusun instrumen, membuka Monev, mengelola AMI, rekomendasi, dokumen bukti, dan kesiapan akreditasi. Pengguna harian terberat, di laptop kantor.
- **Pimpinan (rektor/wakil), fakultas, prodi**: memantau skor mutu, response rate, temuan, dan kesiapan akreditasi dalam cakupan masing-masing; menindaklanjuti rekomendasi sebagai PIC.
- **Auditor internal**: menjalankan audit, mengisi daftar tilik, menerbitkan dan memverifikasi temuan.
- **Dosen**: melihat hasil evaluasi dirinya, menangani tugas mutu (temuan, rencana aksi, periode akreditasi) sebagai PIC.
- **Mahasiswa**: mengisi e-Monev pembelajaran dan survei layanan, sering lewat HP.

## Product Purpose

Sistem Informasi Penjaminan Mutu (SIMUTU) LPM IAIA NU Lombok Timur menjalankan siklus SPMI (PPEPP) dalam satu tempat: e-Monev, Audit Mutu Internal, peningkatan mutu berbasis rekomendasi, repositori dokumen bukti, dan kesiapan akreditasi LAMDIK/LAMGAMA. Sukses berarti data evaluasi menjadi tindakan yang terlacak sampai terverifikasi, dan prodi siap akreditasi dengan bukti yang lengkap.

## Positioning

Satu rantai bukti dari data ke tindakan: skor Monev dan temuan AMI memunculkan rekomendasi, rekomendasi menjadi rencana aksi berbukti, dan bukti yang sama dipetakan ke indikator akreditasi per periode.

## Operating Context

- Siklus per semester (Monev) dan per tahun (AMI); periode akreditasi per prodi dengan tenggat LAM.
- Dipakai di laptop kampus maupun HP; mahasiswa dominan HP.
- Laporan PDF resmi (kop institusi) dan ekspor Excel untuk rapat dan arsip.

## Capabilities and Constraints

- Stack: Laravel + Inertia + React + shadcn/ui + Tailwind CSS v4; database MySQL.
- Akses hierarkis (institusi → fakultas → prodi) dan berbasis peran.
- Anonimitas evaluasi mahasiswa wajib; hasil per dosen disembunyikan di bawah ambang minimum respons.
- Bahasa antarmuka: Indonesia.
- Tidak memakai mode gelap/terang (keputusan pengguna).

## Brand Commitments

- Lambang resmi IAIA NU Lombok Timur (segel hijau-kuning) dipakai di aplikasi dan kop laporan; versi ringkas untuk ukuran kecil. Aset: `public/images/logo-iaia.*`, `public/images/logo-mark.*`.
- Nama produk: SIMUTU.

## Evidence on Hand

- Data demo (seeder) bersifat sintetis: nama dosen/mahasiswa, skor, temuan, dokumen.
- Instrumen akreditasi bawaan bersifat ilustratif, bukan teks resmi LAM.
- Tidak ada testimoni, statistik resmi, atau klaim akreditasi yang boleh dikarang.

## Product Principles

1. Data harus berujung tindakan yang punya PIC, tenggat, bukti, dan verifikasi.
2. Kerahasiaan responden di atas kelengkapan analisis.
3. Setiap peran hanya melihat cakupannya, tetapi tugasnya selalu mudah ditemukan.
4. Bukti dipakai ulang, bukan diunggah berulang.

## Accessibility & Inclusion

Target WCAG 2.2 AA: kontras teks ≥ 4,5:1, fokus keyboard terlihat, target sentuh memadai di HP, dukungan kurangi gerakan.
