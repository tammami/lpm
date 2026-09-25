import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type FilterValue = string | number | boolean | null | undefined;

/**
 * Sinkronkan filter tabel dengan query string (search, filter, sort) secara debounce.
 */
export function useQueryFilters<T extends Record<string, FilterValue>>(initial: T, options: { only?: string[]; delay?: number } = {}) {
    const [filters, setFilters] = useState<T>(initial);
    const first = useRef(true);
    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            const query = Object.fromEntries(
                Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined && value !== 'all'),
            );
            router.get(window.location.pathname, query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: options.only,
            });
        }, options.delay ?? 300);
        return () => clearTimeout(timer.current);
    }, [filters]);

    const setFilter = useCallback(<K extends keyof T>(key: K, value: T[K]) => {
        setFilters((current) => ({ ...current, [key]: value, ...(key !== 'page' && 'page' in current ? { page: undefined } : {}) }));
    }, []);

    return { filters, setFilter, setFilters };
}
