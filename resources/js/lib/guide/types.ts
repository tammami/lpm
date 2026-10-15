import type { LucideIcon } from 'lucide-react';

export interface GuideCategory {
    key: string;
    title: string;
    description: string;
    icon: LucideIcon;
}

export interface GuideAudience {
    /** Tampil juga untuk tamu (belum login), mis. panduan masuk & lupa kata sandi. */
    public?: boolean;
    /** Cukup salah satu permission. */
    permissions?: string[];
    /** Hanya untuk pengguna yang terhubung ke data dosen/mahasiswa. */
    profile?: 'lecturer' | 'student' | 'respondent';
}

export interface GuideArticle {
    slug: string;
    category: string;
    /** Ditulis sebagai pertanyaan/tugas pengguna, mis. "Menambah pengguna baru". */
    title: string;
    summary: string;
    /** Kata yang biasa diketik pengguna, termasuk istilah awam & singkatan. */
    keywords?: string[];
    /** Kosong = semua pengguna yang sudah masuk. */
    audience?: GuideAudience;
    /** Peran yang biasanya mengerjakan, untuk ditampilkan ke pembaca. */
    who?: string;
    /** Letak menu, mis. ['Sistem', 'Pengaturan', 'Pengguna'], beserta rute tujuan. */
    where?: { path: string[]; route?: string };
    /** Langkah bernomor. Mendukung **tebal**. */
    steps?: string[];
    /** Catatan/tips setelah langkah. Mendukung **tebal**. */
    notes?: string[];
    /** Pola nama rute tempat panduan ini relevan (bantuan kontekstual). */
    pages?: string[];
    related?: string[];
}
