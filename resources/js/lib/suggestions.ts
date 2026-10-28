import type { Tone } from '@/components/status-badge';

export type SuggestionStatus = 'new' | 'in_review' | 'followed_up' | 'closed';

export const suggestionStatusTone: Record<SuggestionStatus, Tone> = {
    new: 'gold',
    in_review: 'info',
    followed_up: 'success',
    closed: 'neutral',
};
