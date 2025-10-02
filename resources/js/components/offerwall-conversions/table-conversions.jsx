import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { getCoreRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { columns } from './list-columns';
import { useOfferwallConversions } from '@/hooks/offerwall/use-conversions';
import { useEffect } from 'react';

export function TableConversions({ entries, integrations, companies }) {
  const {
    getConversions,
    columnFilters,
    setColumnFilters,
    sorting,
    setSorting,
    globalFilter,
    setGlobalFilter,
    isLoading,
  } = useOfferwallConversions();

  const table = useReactTable({
    data: entries.data,
    columns: columns,
    pageCount: entries.last_page,
    state: {
      sorting,
      columnFilters,
      globalFilter,
      pagination: { pageIndex: entries.current_page - 1, pageSize: entries.per_page },
    },
    onSortingChange: setSorting,
    onColumnFiltersChange: setColumnFilters,
    onGlobalFilterChange: setGlobalFilter,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true, // Added manual filtering
  });

  useEffect(() => {
    getConversions({ page: entries.current_page, per_page: entries.per_page });
  }, [sorting, columnFilters, globalFilter, entries.current_page, entries.per_page]); // Dependencies for fetching

  return (
    <>
      <div className="mb-4">
        <DataTableToolbar
          table={table}
          searchPlaceholder="Search conversions..."
          filters={[
            {
              columnId: 'integration_id',
              title: 'Integration',
              options: integrations,
            },
            {
              columnId: 'company_id',
              title: 'Company',
              options: companies,
            },
          ]}
        />
      </div>
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} sorting={sorting} setSorting={setSorting} />
          <TableBody>
            <DataTableContent table={table} data={entries.data} isLoading={isLoading} />
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} pagination={entries} />
    </>
  );
}
