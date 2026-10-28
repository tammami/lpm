import type { LinkedEvidence } from '@/components/evidence/types';
import type { Tone } from '@/components/status-badge';
import { formatDate } from '@/lib/format';
import type { Option } from '@/types';

export type ActivityStatus = 'planned' | 'ongoing' | 'done' | 'postponed' | 'cancelled';

export interface LpmActivity {
    id: number;
    code: string;
    year: number;
    title: string;
    category: string;
    category_label: string;
    description: string | null;
    target_output: string | null;
    start_date: string;
    end_date: string;
    location: string | null;
    pic: string | null;
    participants: string | null;
    budget: number | null;
    status: ActivityStatus;
    status_label: string;
    is_overdue: boolean;
    realization_note: string | null;
    evidence_count: number;
    show_url: string;
    evidence?: LinkedEvidence[];
}

export interface ActivityFormOptions {
    categories: Option[];
    statuses: Option[];
}

export const activityStatusTone: Record<ActivityStatus, Tone> = {
    planned: 'info',
    ongoing: 'gold',
    done: 'success',
    postponed: 'warning',
    cancelled: 'neutral',
};

/** Rentang tanggal ringkas: "3 Mar", "3–5 Mar", atau "28 Mar – 2 Apr". */
export function dateRange(start: string, end: string) {
    if (start === end) return formatDate(start, 'd MMM yyyy');
    if (start.slice(0, 7) === end.slice(0, 7)) return `${formatDate(start, 'd')}–${formatDate(end, 'd MMM yyyy')}`;
    return `${formatDate(start, 'd MMM')} – ${formatDate(end, 'd MMM yyyy')}`;
}

export function formatRupiah(value: number | null) {
    return value === null ? null : `Rp${new Intl.NumberFormat('id-ID').format(value)}`;
}
