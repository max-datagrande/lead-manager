import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { useAlertChannels } from '@/hooks/use-alert-channels';
import { type AlertChannel } from '@/pages/alert-channels/index';
import {
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useReactTable,
} from '@tanstack/react-table';
import { useMemo } from 'react';
import { columns } from './list-columns';

const toolbarConfig = {
  dateRange: { column: 'created_at', label: 'Created At' },
  filters: [
    {
      columnId: 'type',
      title: 'Type',
      options: [] as { label: string; value: string }[],
    },
    {
      columnId: 'active',
      title: 'Status',
      options: [
        { label: 'Active', value: 'true' },
        { label: 'Inactive', value: 'false' },
      ],
    },
  ],
};

export function TableAlertChannels({ entries }: { entries: AlertChannel[] }) {
  const {
    channelTypes,
    resetTrigger,
    setResetTrigger,
    sorting,
    setSorting,
    globalFilter,
    setGlobalFilter,
    pagination,
    setPagination,
    columnFilters,
    setColumnFilters,
  } = useAlertChannels();

  const config = useMemo(() => {
    return {
      ...toolbarConfig,
      filters: toolbarConfig.filters.map((f) => {
        if (f.columnId === 'type') {
          return { ...f, options: channelTypes.map((t) => ({ label: t.label, value: t.value })) };
        }
        return f;
      }),
    };
  }, [channelTypes]);

  const table = useReactTable({
    data: entries,
    columns,
    state: {
      sorting,
      globalFilter,
      pagination,
      columnFilters,
    },
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    onColumnFiltersChange: setColumnFilters,
    getPaginationRowModel: getPaginationRowModel(),
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFacetedRowModel: getFacetedRowModel(),
    getFacetedUniqueValues: getFacetedUniqueValues(),
    globalFilterFn: 'includesString',
  });

  return (
    <>
      <div className="mb-4">
        <DataTableToolbar
          table={table}
          searchPlaceholder="Search alert channels..."
          resetTrigger={resetTrigger}
          setResetTrigger={setResetTrigger}
          config={config}
        />
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
