import type { Option } from '@/types';

export type WorkStatus = 'pending' | 'verified' | 'revision' | 'invalid' | 'duplicate';

export interface TaggedLecturer {
    id: number;
    name: string;
    identifier: string | null;
    study_program: string | null;
}

export interface TaggedStudent {
    student_id: number | null;
    name: string;
    nim: string | null;
    study_program?: string | null;
}

export interface ScientificWork {
    id: number;
    lecturer_id: number;
    title: string;
    type: string;
    type_label: string;
    category_label: string;
    year: number;
    venue: string | null;
    indexation: string | null;
    indexation_label: string | null;
    indexation_family: string | null;
    rank_label: string | null;
    author_role: string;
    author_role_label: string;
    author_count: number | null;
    co_authors: string | null;
    source: string;
    source_label: string;
    funding: string;
    funding_label: string;
    relevant_to_program: boolean | null;
    relevant_to_roadmap: boolean | null;
    identifier: string | null;
    doi: string | null;
    volume: string | null;
    url: string | null;
    evidence_url: string | null;
    ip_number: string | null;
    ip_status: string | null;
    ip_status_label: string | null;
    status: WorkStatus;
    status_label: string;
    verification_note: string | null;
    lecturer: string | null;
    study_program: string | null;
    lecturers: TaggedLecturer[];
    students: TaggedStudent[];
    possible_duplicates?: { id: number; title: string; lecturer: string | null; status_label: string }[];
    verifier: string | null;
    verified_at: string | null;
    updated_at: string | null;
}

export interface WorkType extends Option<string> {
    group: string;
    category: string;
    indexed: boolean;
    ip: boolean;
}

export interface WorkFormOptions {
    types: WorkType[];
    categories: Option<string>[];
    indexations: (Option<string> & { group: string })[];
    indexationFamilies: Option<string>[];
    authorRoles: Option<string>[];
    sources: Option<string>[];
    fundings: Option<string>[];
    ipStatuses: Option<string>[];
    statuses: Option<string>[];
}

export const workStatusTone = { pending: 'info', verified: 'success', revision: 'warning', invalid: 'danger', duplicate: 'neutral' } as const;

/** Label kolom "tempat terbit" & nomor identitas yang menyesuaikan subjenis karya. */
export function venueLabels(type: WorkType | undefined) {
    if (!type) return { venue: 'Nama jurnal / penerbit / media', identifier: 'ISSN / ISBN' };
    if (type.value.startsWith('journal')) return { venue: 'Nama jurnal', identifier: 'ISSN / e-ISSN' };
    if (type.value.startsWith('proceeding')) return { venue: 'Nama seminar / prosiding', identifier: 'ISSN / ISBN prosiding' };
    if (type.category === 'book') return { venue: 'Penerbit', identifier: 'ISBN' };
    if (type.ip) return { venue: 'Instansi pencatat (mis. DJKI)', identifier: 'Nomor permohonan (bila ada)' };
    if (type.value === 'popular') return { venue: 'Media / surat kabar', identifier: 'ISSN (bila ada)' };
    return { venue: 'Media / penyelenggara / mitra', identifier: 'Nomor identitas (bila ada)' };
}

/** DOI ditampilkan sebagai tautan doi.org agar bisa diklik verifikator. */
export function doiHref(doi: string | null) {
    return doi ? `https://doi.org/${doi}` : null;
}

/** "Ya" / "Tidak" / kosong untuk isian relevansi. */
export function yesNo(value: boolean | null) {
    return value === null ? null : value ? 'Ya' : 'Tidak';
}
