import {
    BadgeCheck,
    BookOpenText,
    Building2,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    Compass,
    FileBarChart2,
    FileSpreadsheet,
    FolderArchive,
    GraduationCap,
    KeyRound,
    ListChecks,
    MessagesSquare,
    Settings2,
    ShieldCheck,
    Target,
    Trophy,
} from 'lucide-react';
import { accountArticles } from './content/account';
import { dataArticles } from './content/data';
import { monevArticles } from './content/monev';
import { qualityArticles } from './content/quality';
import { serviceArticles } from './content/services';
import { systemArticles } from './content/system';
import type { GuideArticle, GuideCategory } from './types';

export type { GuideArticle, GuideCategory } from './types';

export const guideCategories: GuideCategory[] = [
    { key: 'akun', title: 'Masuk & Akun', description: 'Login, lupa kata sandi, profil.', icon: KeyRound },
    { key: 'navigasi', title: 'Memulai & Navigasi', description: 'Tampilan, dashboard, notifikasi, tugas saya.', icon: Compass },
    { key: 'isi-monev', title: 'Mengisi Monev', description: 'Untuk mahasiswa & dosen sebagai responden.', icon: ClipboardCheck },
    { key: 'instrumen', title: 'Instrumen', description: 'Menyusun kuesioner dan menerbitkannya.', icon: ListChecks },
    { key: 'monev', title: 'Kegiatan Monev', description: 'Membuka, memantau, dan menutup Monev.', icon: ClipboardList },
    { key: 'analitik', title: 'Analitik & Laporan', description: 'Membaca hasil dan mengunduh laporan.', icon: FileBarChart2 },
    { key: 'ami', title: 'Audit Mutu Internal', description: 'Program, audit, temuan, tindak lanjut.', icon: ShieldCheck },
    { key: 'peningkatan', title: 'Peningkatan Mutu', description: 'Rekomendasi dan rencana aksi.', icon: Target },
    { key: 'dokumen', title: 'Dokumen Bukti', description: 'Unggah, verifikasi, dan pakai ulang bukti.', icon: FolderArchive },
    { key: 'akreditasi', title: 'Akreditasi', description: 'Instrumen LAM, periode, kesiapan.', icon: BadgeCheck },
    { key: 'karya', title: 'Karya Ilmiah', description: 'Publikasi dosen, verifikasi, analisis.', icon: BookOpenText },
    { key: 'program-kerja', title: 'Program Kerja LPM', description: 'Timeline kegiatan dan bukti pelaksanaan.', icon: CalendarRange },
    { key: 'saran', title: 'Kotak Saran', description: 'Mengirim dan menanggapi saran.', icon: MessagesSquare },
    { key: 'prestasi', title: 'Prestasi Mahasiswa', description: 'Mencatat dan memverifikasi prestasi.', icon: Trophy },
    { key: 'master', title: 'Master Akademik', description: 'Periode, dosen, mahasiswa, MK, kelas.', icon: GraduationCap },
    { key: 'organisasi', title: 'Organisasi', description: 'Institusi, fakultas, prodi, unit.', icon: Building2 },
    { key: 'impor', title: 'Impor Data', description: 'Memasukkan data massal dari Excel.', icon: FileSpreadsheet },
    { key: 'sistem', title: 'Pengguna & Sistem', description: 'Pengguna, hak akses, pengaturan, backup.', icon: Settings2 },
];

export const guideArticles: GuideArticle[] = [...accountArticles, ...monevArticles, ...qualityArticles, ...serviceArticles, ...dataArticles, ...systemArticles];

const bySlug = new Map(guideArticles.map((article) => [article.slug, article]));

export const findArticle = (slug: string | null | undefined) => (slug ? bySlug.get(slug) : undefined);

export const categoryOf = (article: GuideArticle) => guideCategories.find((category) => category.key === article.category);

/** Topik yang paling sering ditanyakan, ditampilkan saat kotak cari masih kosong. */
export const popularSlugs = [
    'cara-masuk',
    'lupa-kata-sandi',
    'isi-monev',
    'tambah-pengguna',
    'buka-kelas',
    'impor-data',
    'buat-kegiatan-monev',
    'tindak-lanjut-temuan',
    'unggah-dokumen',
    'isi-karya-saya',
    'hasil-evaluasi-saya',
    'reset-sandi-dosen-mahasiswa',
];

export interface GuideViewer {
    can: (...permissions: string[]) => boolean;
    isGuest: boolean;
    isLecturer: boolean;
    isStudent: boolean;
}

/**
 * Apakah panduan relevan untuk pembaca. Ini hanya penyaring relevansi,
 * bukan kontrol akses: hak akses tetap dijaga di server.
 */
export function isVisibleTo(article: GuideArticle, viewer: GuideViewer): boolean {
    const audience = article.audience;
    if (viewer.isGuest) return audience?.public === true;
    if (!audience || audience.public) return true;

    if (audience.profile === 'lecturer' && !viewer.isLecturer) return false;
    if (audience.profile === 'student' && !viewer.isStudent) return false;
    if (audience.profile === 'respondent' && !viewer.isLecturer && !viewer.isStudent) return false;

    return !audience.permissions || viewer.can(...audience.permissions);
}

/** Panduan yang relevan untuk halaman (nama rute) yang sedang dibuka. */
export function articlesForCurrentPage(articles: GuideArticle[]): GuideArticle[] {
    const matches = (pattern: string) => {
        try {
            return route().current(pattern) === true;
        } catch {
            return false;
        }
    };

    return articles.filter((article) => article.pages?.some(matches));
}

export { searchGuide } from './search';
