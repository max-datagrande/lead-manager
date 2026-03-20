import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import {
  getCoreRowModel,
  getFilteredRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getPaginationRowModel,
  getSortedRowModel,
  useReactTable,
} from '@tanstack/react-table';
import { useMemo } from 'react';
import { columns } from './list-columns';
import { useLandings } from '@/hooks/use-landings';

const toolbarConfig = {
  dateRange: { column: 'created_at', label: 'Created At' },
  filters: [
    {
      columnId: 'active',
      title: 'Status',
      options: [
        { label: 'Active', value: 'true' },
        { label: 'Inactive', value: 'false' },
      ],
    },
    {
      columnId: 'is_external',
      title: 'Type',
      options: [
        { label: 'External', value: 'true' },
        { label: 'Internal', value: 'false' },
      ],
    },
  ],
};

export function TableLandingPages({ entries }) {
  const {
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
  } = useLandings();

  // Separate date filters from real column filters
  const fromFilter = useMemo(
    () => columnFilters.find((f) => f.id === 'from_date'),
    [columnFilters]
  );
  const toFilter = useMemo(
    () => columnFilters.find((f) => f.id === 'to_date'),
    [columnFilters]
  );
  const realColumnFilters = useMemo(
    () => columnFilters.filter((f) => f.id !== 'from_date' && f.id !== 'to_date'),
    [columnFilters]
  );

  // Apply date range filter on entries
  const filteredEntries = useMemo(() => {
    if (!fromFilter && !toFilter) return entries;
    return entries.filter((row) => {
      const date = new Date(row.created_at);
      if (fromFilter && date < new Date(fromFilter.value as string)) return false;
      if (toFilter && date > new Date(toFilter.value as string)) return false;
      return true;
    });
  }, [entries, fromFilter, toFilter]);

  const table = useReactTable({
    data: filteredEntries,
    columns,
    state: {
      sorting,
      globalFilter,
      pagination,
      columnFilters: realColumnFilters,
    },
    onPaginationChange: setPagination,
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    onColumnFiltersChange: (updater) => {
      const next = typeof updater === 'function' ? updater(realColumnFilters) : updater;
      setColumnFilters([
        ...next,
        ...(fromFilter ? [fromFilter] : []),
        ...(toFilter ? [toFilter] : []),
      ]);
    },
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
          searchPlaceholder="Search landing pages..."
          resetTrigger={resetTrigger}
          setResetTrigger={setResetTrigger}
          config={toolbarConfig}
        />
      </div>
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} />
          <TableBody>
            <DataTableContent table={table} data={filteredEntries} />
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} />
    </>
  );
}
