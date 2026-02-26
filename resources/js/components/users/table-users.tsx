import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { useUsers } from '@/hooks/use-users';
import { getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { usersColumns } from './list-columns';

export function TableUsers({ entries }: { entries: any[] }) {
  const { sorting, setSorting, globalFilter, setGlobalFilter, pagination, setPagination } = useUsers();

  const table = useReactTable({
    data: entries,
    columns: usersColumns,
    state: {
      sorting,
      globalFilter,
      pagination,
    },
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    getPaginationRowModel: getPaginationRowModel(),
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    rowCount: entries.length,
    globalFilterFn: 'includesString',
    filterFns: {
      booleanFilter: (row: any, columnId: string, filterValue: any[]) => {
        const cellValue = row.getValue(columnId);
        return filterValue.includes(cellValue);
      },
    },
  });

  return (
    <>
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search users..."
            config={{
              filters: [
                {
                  columnId: 'role',
                  title: 'Role',
                  options: [
                    { label: 'Admin', value: 'admin' },
                    { label: 'Manager', value: 'manager' },
                    { label: 'User', value: 'user' },
                  ],
                },
                {
                  columnId: 'is_active',
                  title: 'Status',
                  options: [
                    { label: 'Active', value: true },
                    { label: 'Inactive', value: false },
                  ],
                },
              ],
              dateRange: { column: 'created_at', label: 'Created At' },
            }}
          />
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
