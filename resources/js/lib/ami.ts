import type { Tone } from '@/components/status-badge';

export const auditTone: Record<string, Tone> = {
    planned: 'info',
    desk_review: 'gold',
    field_audit: 'warning',
    reporting: 'primary',
    completed: 'success',
    cancelled: 'neutral',
};

export const findingTone: Record<string, Tone> = {
    open: 'neutral',
    action_required: 'danger',
    in_progress: 'warning',
    submitted: 'info',
    verified: 'primary',
    closed: 'success',
};

export const severityTone: Record<string, Tone> = { danger: 'danger', warning: 'warning', info: 'info', neutral: 'neutral', success: 'success' };

export const programTone: Record<string, Tone> = { draft: 'neutral', planned: 'info', ongoing: 'gold', completed: 'success', archived: 'neutral' };

export const auditStages = [
    { value: 'planned', label: 'Terjadwal' },
    { value: 'desk_review', label: 'Desk evaluation' },
    { value: 'field_audit', label: 'Audit lapangan' },
    { value: 'reporting', label: 'Pelaporan' },
    { value: 'completed', label: 'Selesai' },
];

export const findingSteps = [
    { value: 'open', label: 'Dicatat' },
    { value: 'action_required', label: 'Diterbitkan' },
    { value: 'in_progress', label: 'Perbaikan' },
    { value: 'submitted', label: 'Verifikasi' },
    { value: 'verified', label: 'Terverifikasi' },
    { value: 'closed', label: 'Ditutup' },
];
