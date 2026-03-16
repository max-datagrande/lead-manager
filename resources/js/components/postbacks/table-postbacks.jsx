import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { usePostbacks } from '@/hooks/use-postbacks';
import { getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { DataTablePagination } from '../data-table/table-pagination';
import { columns } from './list-columns';

export function TablePostbacks({ entries }) {
  const { sorting, setSorting, globalFilter, setGlobalFilter, pagination, setPagination, resetTrigger, setResetTrigger } = usePostbacks();

  const table = useReactTable({
    data: entries,
    columns,
    state: { sorting, globalFilter, pagination },
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    onPaginationChange: setPagination,
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    rowCount: entries.length,
    globalFilterFn: 'includesString',
  });

  return (
    <>
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar table={table} searchPlaceholder="Search postbacks..." resetTrigger={resetTrigger} setResetTrigger={setResetTrigger} />
        </div>
      </div>
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} />
          <TableBody>
            <DataTableContent table={table} data={entries} />
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} />
    </>
  );
}
