export interface BuilderOption {
    id?: number;
    label: string;
    value: string;
    score: number | string | null;
}

export interface BuilderQuestion {
    id: number;
    instrument_section_id: number;
    code: string;
    label: string;
    description: string | null;
    type: string;
    type_label: string;
    category: string | null;
    indicator: string | null;
    is_required: boolean;
    is_scored: boolean;
    weight: number;
    min_score: number | null;
    max_score: number | null;
    requires_evidence: boolean;
    visible_to_evaluatee: boolean;
    is_active: boolean;
    settings: Record<string, unknown> | null;
    sort_order: number;
    options: BuilderOption[];
}

export interface BuilderSection {
    id: number;
    code: string;
    title: string;
    description: string | null;
    sort_order: number;
    questions: BuilderQuestion[];
}

export interface QuestionTypeOption {
    value: string;
    label: string;
    has_options: boolean;
    scorable: boolean;
}

export interface AnswerScale {
    id: number;
    name: string;
    question_type: string;
    options: { label: string; value: string; score: number | null }[];
}

export interface BuilderVersion {
    id: number;
    version: string;
    status: string;
    status_label: string;
    is_editable: boolean;
    scoring_method: string;
    scale_min: number;
    scale_max: number;
    classification_scheme_id: number | null;
    changelog: string | null;
    review_notes: string | null;
    parent_version: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    published_at: string | null;
    publisher: string | null;
    surveys_count: number;
    responses_count: number;
}
