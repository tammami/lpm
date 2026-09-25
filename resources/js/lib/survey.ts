import type { Tone } from '@/components/status-badge';

export const surveyTone: Record<string, Tone> = {
    draft: 'neutral',
    active: 'success',
    closed: 'info',
    archived: 'neutral',
};

export function daysLeft(endsAt: string) {
    return Math.ceil((new Date(endsAt).getTime() - Date.now()) / 86_400_000);
}
