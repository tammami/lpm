import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Buang nilai filter kosong/"all" sebelum dijadikan query string. */
export function cleanQuery(filters: Record<string, unknown>) {
    return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== 'all'));
}
