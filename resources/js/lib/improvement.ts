import type { Tone } from '@/components/status-badge';

export const recommendationTone: Record<string, Tone> = {
    open: 'danger',
    in_progress: 'info',
    completed: 'warning',
    verified: 'primary',
    closed: 'success',
    cancelled: 'neutral',
};

export const planTone: Record<string, Tone> = {
    planned: 'neutral',
    in_progress: 'info',
    completed: 'warning',
    verified: 'success',
    rejected: 'danger',
};

export const priorityTone: Record<string, Tone> = { high: 'danger', medium: 'gold', low: 'neutral' };

export const originTone: Record<string, Tone> = { monev: 'primary', ami: 'gold', accreditation: 'info', system: 'warning', manual: 'neutral' };
