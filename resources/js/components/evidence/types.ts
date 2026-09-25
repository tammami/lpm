export interface EvidenceRow {
    id: number;
    code: string;
    title: string;
    category: string | null;
    evidence_category_id: number | null;
    document_type: 'file' | 'link';
    unit_name: string | null;
    year: number | null;
    status: string;
    status_label: string;
    owner: string | null;
    mappings_count: number | null;
    version: number | null;
    file_name: string | null;
    mime_type: string | null;
    size: number | null;
    url: string | null;
    download_url: string;
    show_url: string;
    updated_at: string;
}

export interface LinkedEvidence extends EvidenceRow {
    mapping_id: number;
    can_unlink: boolean;
}

export const evidenceTone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    verified: 'success',
    pending: 'warning',
    rejected: 'danger',
    expired: 'danger',
};
