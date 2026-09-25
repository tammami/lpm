import { format, formatDistanceToNow, parseISO } from 'date-fns';
import { id } from 'date-fns/locale';

const toDate = (value: string | Date) => (typeof value === 'string' ? parseISO(value) : value);

export function formatDate(value?: string | Date | null, pattern = 'd MMM yyyy') {
    if (!value) return '—';
    return format(toDate(value), pattern, { locale: id });
}

export function formatDateTime(value?: string | Date | null) {
    return formatDate(value, 'd MMM yyyy, HH:mm');
}

export function fromNow(value?: string | Date | null) {
    if (!value) return '—';
    return formatDistanceToNow(toDate(value), { addSuffix: true, locale: id });
}

export function formatNumber(value?: number | null, digits = 0) {
    if (value === null || value === undefined || Number.isNaN(value)) return '—';
    return new Intl.NumberFormat('id-ID', { minimumFractionDigits: digits, maximumFractionDigits: digits }).format(value);
}

export function formatScore(value?: number | null) {
    return formatNumber(value, 2);
}

export function formatPercent(value?: number | null, digits = 1) {
    if (value === null || value === undefined) return '—';
    return `${formatNumber(value, digits)}%`;
}

export function formatBytes(bytes?: number | null) {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return `${formatNumber(bytes / 1024 ** index, index === 0 ? 0 : 1)} ${units[index]}`;
}
