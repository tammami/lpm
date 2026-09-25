import type { Tone } from '@/components/status-badge';

export const periodTone: Record<string, Tone> = {
    preparing: 'info',
    submitted: 'gold',
    visitation: 'warning',
    decided: 'success',
    cancelled: 'neutral',
};

export const versionTone: Record<string, Tone> = { draft: 'warning', published: 'success', archived: 'neutral' };

export const indicatorStatus: Record<string, { label: string; tone: Tone }> = {
    ready: { label: 'Siap', tone: 'success' },
    partial: { label: 'Menunggu verifikasi', tone: 'warning' },
    gap: { label: 'Belum ada bukti', tone: 'danger' },
};

export const periodSteps = [
    { value: 'preparing', label: 'Penyusunan dokumen' },
    { value: 'submitted', label: 'Diajukan' },
    { value: 'visitation', label: 'Asesmen lapangan' },
    { value: 'decided', label: 'Keputusan' },
];

export function deadlineTone(days: number | null | undefined): Tone {
    if (days === null || days === undefined) return 'neutral';
    if (days < 0) return 'danger';
    if (days <= 30) return 'warning';
    return 'info';
}

export function deadlineLabel(days: number | null | undefined): string {
    if (days === null || days === undefined) return 'Tanpa tenggat';
    if (days < 0) return `Lewat ${Math.abs(days)} hari`;
    if (days === 0) return 'Hari ini';
    return `${days} hari lagi`;
}
