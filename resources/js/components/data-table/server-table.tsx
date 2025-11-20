import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';

interface ServerTableProps<TData> {
  data: TData[];
  columns: any[];
  meta: {
    last_page: number;
  };
  isLoading: boolean;
  pagination: { pageIndex: number; pageSize: number };
  setPagination: (pagination: { pageIndex: number; pageSize: number }) => void;
  sorting: any[];
  setSorting: (sorting: any[]) => void;
  columnFilters: any[];
  setColumnFilters: (filters: any[]) => void;
  globalFilter: string;
  setGlobalFilter: (filter: string) => void;
  toolbarConfig?: {
    searchPlaceholder?: string;
    filterByColumn?: string;
    filters?: Array<{
      columnId: string;
      title: string;
      options: any;
    }>;
    dateRange?: { column: string; label: string };
  };
  additionalData?: Record<string, any>;
}

export function ServerTable<TData>({
  data,
  columns,
  meta,
  isLoading,
  pagination,
  setPagination,
  sorting,
  setSorting,
  columnFilters,
  setColumnFilters,
  globalFilter,
  setGlobalFilter,
  toolbarConfig = {},
  additionalData = {},
}: ServerTableProps<TData>) {
  const { pageIndex, pageSize } = pagination;

  const resetPagination = () => {
    setPagination({ pageIndex: 0, pageSize });
  };

  const table = useReactTable({
    data,
    columns,
    state: {
      sorting,
      columnFilters,
      pagination: { pageIndex, pageSize },
      globalFilter,
    },
    onPaginationChange: (updater) => {
      const newPagination = typeof updater === 'function' ? updater({ pageIndex, pageSize }) : updater;
      setPagination(newPagination);
    },
    onSortingChange: (sortingUpdate) => {
      const newSorting = typeof sortingUpdate === 'function' ? sortingUpdate(sorting) : sortingUpdate;
      setSorting(newSorting);
      resetPagination();
    },
    onColumnFiltersChange: (filtersUpdate) => {
      const newFilters = typeof filtersUpdate === 'function' ? filtersUpdate(columnFilters) : filtersUpdate;
      setColumnFilters(newFilters);
      resetPagination();
    },
    onGlobalFilterChange: (filter) => {
      setGlobalFilter(filter);
      resetPagination();
    },
    getCoreRowModel: getCoreRowModel(),
    manualSorting: true,
    manualFiltering: true,
    manualPagination: true,
    pageCount: meta.last_page,
  });

  return (
    <>
      {toolbarConfig && (
        <div className="mb-4">
          <div className="mb-4 flex justify-between gap-2">
            <DataTableToolbar
              table={table}
              searchPlaceholder={toolbarConfig.searchPlaceholder || 'Search...'}
              config={{
                filters: toolbarConfig.filters || [],
                dateRange: toolbarConfig.dateRange,
              }}
            />
          </div>
        </div>
      )}
      <div className="relative overflow-hidden rounded-md border">
        {isLoading && (
          <div className="absolute top-0 right-0 bottom-0 left-0 z-10 flex h-full w-full items-center justify-center bg-white">
            <div className="flex items-center justify-center space-x-2">
              <div className="h-4 w-4 animate-spin rounded-full border-b-2 border-gray-900"></div>
              <span>Loading...</span>
            </div>
          </div>
        )}
        <Table className="relative">
          <DataTableHeader table={table} />
          <TableBody>
            <DataTableContent table={table} data={data} />
          </TableBody>
        </Table>
      </div>
      {!isLoading && <DataTablePagination table={table} />}
    </>
  );
}
