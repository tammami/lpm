import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PaginationBar } from '@/components/pagination-bar';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export interface Column<T> {
    key: string;
    header: ReactNode;
    cell: (row: T) => ReactNode;
    className?: string;
    headerClassName?: string;
    sortable?: boolean;
}

interface Props<T> {
    columns: Column<T>[];
    data: T[];
    pagination?: Omit<Paginated<T>, 'data'>;
    rowKey?: (row: T) => string | number;
    sort?: string;
    onSort?: (sort: string) => void;
    emptyTitle?: string;
    emptyDescription?: ReactNode;
    emptyAction?: ReactNode;
    onRowClick?: (row: T) => void;
    toolbar?: ReactNode;
    className?: string;
    /** Nama area tabel untuk pembaca layar. */
    label?: string;
}

/**
 * Tabel data standar dengan header sortir dan paginasi server-side.
 */
export function DataTable<T>({
    columns,
    data,
    pagination,
    rowKey,
    sort,
    onSort,
    emptyTitle = 'Belum ada data',
    emptyDescription,
    emptyAction,
    onRowClick,
    toolbar,
    className,
    label = 'Tabel data',
}: Props<T>) {
    const [sortKey, sortDir] = sort?.startsWith('-') ? [sort.slice(1), 'desc'] : [sort, 'asc'];

    const toggleSort = (key: string) => {
        if (!onSort) return;
        onSort(sortKey === key && sortDir === 'asc' ? `-${key}` : key);
    };

    return (
        <div className={cn('overflow-hidden rounded-2xl border border-white/75 surface-glass', className)}>
            {toolbar && <div className="border-b p-3 sm:p-4">{toolbar}</div>}
            {data.length === 0 ? (
                <EmptyState title={emptyTitle} description={emptyDescription} action={emptyAction} />
            ) : (
                <div className="relative overflow-x-auto">
                    <Table aria-label={label}>
                        <TableHeader>
                            <TableRow className="bg-white/40 hover:bg-white/40">
                                {columns.map((column) => (
                                    <TableHead
                                        key={column.key}
                                        aria-sort={column.sortable && onSort ? (sortKey === column.key ? (sortDir === 'asc' ? 'ascending' : 'descending') : 'none') : undefined}
                                        className={cn('h-11 px-4 text-[12px] font-bold text-muted-foreground', column.headerClassName)}
                                    >
                                        {column.sortable && onSort ? (
                                            <button
                                                type="button"
                                                onClick={() => toggleSort(column.key)}
                                                className="-ml-1 inline-flex min-h-8 items-center gap-1 rounded-md px-1 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                            >
                                                {column.header}
                                                {sortKey === column.key ? (
                                                    sortDir === 'asc' ? <ArrowUp className="size-3.5" /> : <ArrowDown className="size-3.5" />
                                                ) : (
                                                    <ArrowUpDown className="size-3.5 opacity-40" />
                                                )}
                                            </button>
                                        ) : column.header === '' ? (
                                            <span className="sr-only">Aksi</span>
                                        ) : (
                                            column.header
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.map((row, index) => (
                                <TableRow
                                    key={rowKey ? rowKey(row) : index}
                                    onClick={onRowClick ? () => onRowClick(row) : undefined}
                                    // Baris yang bisa diklik juga harus bisa dibuka lewat keyboard (Enter/Spasi).
                                    tabIndex={onRowClick ? 0 : undefined}
                                    onKeyDown={
                                        onRowClick
                                            ? (event) => {
                                                  if (event.target !== event.currentTarget) return;
                                                  if (event.key === 'Enter' || event.key === ' ') {
                                                      event.preventDefault();
                                                      onRowClick(row);
                                                  }
                                              }
                                            : undefined
                                    }
                                    className={cn(onRowClick && 'cursor-pointer focus-visible:bg-white/70 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring')}
                                >
                                    {columns.map((column) => (
                                        <TableCell key={column.key} className={cn('px-4 py-3 text-[14px]', column.className)}>
                                            {column.cell(row)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
            {pagination && <PaginationBar meta={pagination} />}
        </div>
    );
}
