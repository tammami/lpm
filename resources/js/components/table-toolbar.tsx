import type { ReactNode } from 'react';
import { SearchInput } from '@/components/search-input';

export function TableToolbar({ search, onSearch, placeholder, children, end }: { search: string; onSearch: (value: string) => void; placeholder?: string; children?: ReactNode; end?: ReactNode }) {
    return (
        <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
            <SearchInput value={search} onChange={onSearch} placeholder={placeholder} />
            {children && <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center [&>*]:sm:w-48">{children}</div>}
            {end && <div className="flex items-center gap-2 lg:ml-auto">{end}</div>}
        </div>
    );
}
