import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Buang nilai filter kosong/"all" sebelum dijadikan query string. */
export function cleanQuery(filters: Record<string, unknown>) {
    return Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== 'all'));
}

/**
 * Kelompokkan opsi berdasarkan `group` dengan urutan kemunculan pertama. Opsi tanpa grup berada pada grup `null`.
 */
export function groupOptions<T extends { group?: string }>(options: T[]): { group: string | null; items: T[] }[] {
    const groups = new Map<string | null, T[]>();

    for (const option of options) {
        const key = option.group ?? null;
        groups.set(key, [...(groups.get(key) ?? []), option]);
    }

    return Array.from(groups, ([group, items]) => ({ group, items }));
}
