import type { Classification } from '@/components/charts/score-badge';

export interface Scheme {
    id: number;
    name: string;
    scale_min: number;
    scale_max: number;
    classes: { label: string; min_score: number; max_score: number; color: string }[];
}

export interface AnalyticsSurvey {
    id: number;
    title: string;
    code: string;
    mode: string;
    status: string;
    status_label: string;
    period: string | null;
    instrument: string;
    instrument_version: string;
    scoring_method: string;
    formula: string;
    scale_max: number;
    scale_min: number;
}

export interface Summary {
    responses: number;
    score: number | null;
    classification: Classification | null;
    suppressed: boolean;
}

export interface QuestionStat {
    id: number;
    code: string;
    label: string;
    indicator: string | null;
    section: string | null;
    section_title: string | null;
    weight: number;
    score: number | null;
    classification: Classification | null;
    responses: number;
    distribution: { label: string; value: string; count: number; percent: number }[];
}

export interface SectionStat {
    id: number;
    code: string;
    title: string;
    score: number;
    responses: number;
}

export interface TrendPoint {
    survey_id: number;
    label: string;
    score: number | null;
    responses: number;
}
