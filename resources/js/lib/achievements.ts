import type { Tone } from '@/components/status-badge';
import type { Option } from '@/types';

export type AchievementStatus = 'pending' | 'verified' | 'revision' | 'rejected';

export interface Achievement {
    id: number;
    title: string;
    category: string;
    category_label: string;
    level: string;
    level_label: string;
    achievement: string;
    participation: string;
    participation_label: string;
    organizer: string | null;
    achieved_on: string;
    description: string | null;
    certificate_name: string | null;
    certificate_url: string | null;
    evidence_url: string | null;
    status: AchievementStatus;
    status_label: string;
    verification_note: string | null;
    verifier: string | null;
    verified_at: string | null;
    updated_at: string | null;
    student: { id: number; name: string; nim: string; study_program: string | null } | null;
}

export interface AchievementFormOptions {
    categories: Option[];
    levels: Option[];
    statuses: Option[];
    participations: Option[];
    achievementSuggestions: string[];
    maxUploadMb: number;
}

export const achievementStatusTone: Record<AchievementStatus, Tone> = {
    pending: 'warning',
    verified: 'success',
    revision: 'gold',
    rejected: 'danger',
};
