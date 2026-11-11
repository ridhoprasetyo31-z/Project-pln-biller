import { router } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable, type ColumnDef } from '@tanstack/react-table';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';

import { Button } from '@/Components/ui/button';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export type { ColumnDef };

declare module '@tanstack/react-table' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData, TValue> {
        /** Kelas untuk <th> dan <td>, misal lebar atau rata kanan. */
        className?: string;
    }
}

interface DataTableProps<T> {
    columns: ColumnDef<T, any>[];
    /** Data paginasi server (paginate()) atau array biasa. */
    data: Paginated<T> | T[];
    getRowId?: (row: T) => string;
    rowClassName?: (row: T) => string | undefined;
    empty?: ReactNode;
    className?: string;
}

/**
 * Tabel data: border rounded-14, header #F3F6F9, baris ≥ 58 px,
 * bisa digulir mendatar di HP, paginasi "Menampilkan x sampai y dari z".
 */
export default function DataTable<T>({ columns, data, getRowId, rowClassName, empty, className }: DataTableProps<T>) {
    const rows = Array.isArray(data) ? data : data.data;
    const paginated = Array.isArray(data) ? null : data;

    const table = useReactTable({
        data: rows,
        columns,
        getRowId,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
    });

    return (
        <div className={cn('overflow-hidden rounded-14 border border-border bg-white', className)}>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[720px] border-collapse text-sm">
                    <thead className="bg-table-head">
                        {table.getHeaderGroups().map((hg) => (
                            <tr key={hg.id}>
                                {hg.headers.map((header) => (
                                    <th
                                        key={header.id}
                                        scope="col"
                                        className={cn(
                                            'h-11 px-4 text-left align-middle text-xs font-medium whitespace-nowrap text-muted-ink',
                                            header.column.columnDef.meta?.className,
                                        )}
                                    >
                                        {header.isPlaceholder
                                            ? null
                                            : flexRender(header.column.columnDef.header, header.getContext())}
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows.length === 0 ? (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-14 text-center text-sm text-muted-ink">
                                    {empty ?? 'Belum ada data.'}
                                </td>
                            </tr>
                        ) : (
                            table.getRowModel().rows.map((row) => (
                                <tr
                                    key={row.id}
                                    className={cn(
                                        'border-t border-line-row transition-colors hover:bg-table-head/50',
                                        rowClassName?.(row.original),
                                    )}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <td
                                            key={cell.id}
                                            className={cn(
                                                'h-[58px] px-4 py-2.5 align-middle',
                                                cell.column.columnDef.meta?.className,
                                            )}
                                        >
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
            {paginated && paginated.total > 0 && <Pagination page={paginated} />}
        </div>
    );
}

function Pagination<T>({ page }: { page: Paginated<T> }) {
    const go = (url: string | null) =>
        url && router.get(url, {}, { preserveState: true, preserveScroll: false });

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line-row px-4 py-3">
            <p className="text-[13px] text-muted-ink">
                Menampilkan {formatNumber(page.from ?? 0)} sampai {formatNumber(page.to ?? 0)} dari{' '}
                {formatNumber(page.total)}
            </p>
            {page.last_page > 1 && (
                <div className="flex gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={!page.prev_page_url}
                        onClick={() => go(page.prev_page_url)}
                    >
                        <ChevronLeft />
                        Sebelumnya
                    </Button>
                    <Button
                        variant="secondary"
                        size="sm"
                        disabled={!page.next_page_url}
                        onClick={() => go(page.next_page_url)}
                    >
                        Berikutnya
                        <ChevronRight />
                    </Button>
                </div>
            )}
        </div>
    );
}
