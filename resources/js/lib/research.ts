import type { Option } from '@/types';

export interface ScientificWork {
    id: number;
    lecturer_id: number;
    title: string;
    type: string;
    type_label: string;
    year: number;
    venue: string | null;
    rank: string | null;
    rank_label: string | null;
    author_role: string;
    author_role_label: string;
    co_authors: string | null;
    source: string;
    source_label: string;
    funding: string;
    funding_label: string;
    identifier: string | null;
    volume: string | null;
    url: string | null;
    status: 'pending' | 'verified' | 'revision';
    status_label: string;
    verification_note: string | null;
    lecturer: string | null;
    study_program: string | null;
    verifier: string | null;
    verified_at: string | null;
    updated_at: string | null;
}

export interface WorkType extends Option<string> {
    journal: boolean;
    ranks: Option<string>[];
}

export interface WorkFormOptions {
    types: WorkType[];
    authorRoles: Option<string>[];
    sources: Option<string>[];
    fundings: Option<string>[];
    statuses: Option<string>[];
}

/** Label kolom "tempat terbit" & nomor identitas yang menyesuaikan jenis karya. */
export function venueLabels(type: string) {
    if (type.startsWith('journal')) return { venue: 'Nama jurnal', identifier: 'ISSN / e-ISSN' };
    if (type.startsWith('proceeding')) return { venue: 'Nama seminar / prosiding', identifier: 'ISSN / ISBN prosiding' };
    if (type === 'book' || type === 'book_chapter') return { venue: 'Penerbit', identifier: 'ISBN' };
    if (type === 'ip') return { venue: 'Instansi pencatat (mis. DJKI)', identifier: 'Nomor pencatatan / paten' };
    return { venue: 'Media / penerbit', identifier: 'Nomor identitas (bila ada)' };
}

/** DOI ditampilkan sebagai tautan doi.org agar bisa diklik verifikator. */
export function workHref(url: string | null) {
    if (!url) return null;
    return /^10\.\d{4,9}\//.test(url) ? `https://doi.org/${url}` : url;
}
