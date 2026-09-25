import type { LinkedEvidence } from '@/components/evidence/types';

export type IndicatorStatus = 'ready' | 'partial' | 'gap';

export interface ReadinessSummary {
    total: number;
    ready: number;
    partial: number;
    gap: number;
    readiness: number;
    assessed: number;
    coverage: number;
    estimated_score: number | null;
    estimated_grade: string | null;
    essential_unmet: number;
}

export interface CriterionStat {
    id: number;
    code: string;
    title: string;
    weight: number;
    total: number;
    ready: number;
    partial: number;
    gap: number;
    readiness: number | null;
    self_average: number | null;
    self_percent: number | null;
}

export interface PeriodRow {
    id: number;
    code: string;
    name: string;
    status: string;
    status_label: string;
    study_program: string | null;
    study_program_code: string | null;
    instrument: string;
    pic: string | null;
    submission_deadline: string | null;
    days_to_deadline: number | null;
}

export interface IndicatorState {
    id: number;
    code: string;
    statement: string;
    target: string | null;
    evidence_hint: string | null;
    weight: number;
    is_essential: boolean;
    status: IndicatorStatus;
    evidence_count: number;
    verified_count: number;
    self_score: number | null;
    essential_met: boolean;
    notes: string | null;
    evidence: LinkedEvidence[];
}

export interface GapItem {
    id: number;
    code: string;
    statement: string;
    evidence_hint: string | null;
    criterion: string | null;
    is_essential: boolean;
    status: IndicatorStatus;
    self_score: number | null;
    essential_met: boolean;
}
