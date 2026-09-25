import type { Tone } from '@/components/status-badge';

export const versionTone: Record<string, Tone> = {
    draft: 'neutral',
    review: 'info',
    approved: 'primary',
    published: 'success',
    archived: 'neutral',
};

export const instrumentTypeTone: Record<string, Tone> = {
    monev_pembelajaran: 'primary',
    survei: 'info',
    ami: 'gold',
    evaluasi: 'primary',
    asesmen: 'warning',
    kustom: 'neutral',
};
