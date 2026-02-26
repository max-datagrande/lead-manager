import { Table, TableBody } from '@/components/ui/table';
import { useEffect } from 'react';

import { getCoreRowModel, useReactTable } from '@tanstack/react-table';

import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { useVisitors } from '@/hooks/use-visitors';
import { visitorColumns } from './list-columns';

import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';

/**
 * Componente principal para mostrar la tabla de visitantes con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.entries - Datos de visitantes con información de paginación
 * @param {Object} props.meta - Metadatos de la paginación
 * @param {Object} props.data - Datos adicionales como hosts y states
 * @returns {JSX.Element} Tabla completa con datos de visitantes y controles de paginación
 */
export const TableVisitors = ({ entries, meta, data }) => {
  const { getVisitors, columnFilters, setColumnFilters, sorting, setSorting, globalFilter, setGlobalFilter, isLoading, pagination, setPagination } =
    useVisitors();
  const { hosts = [], states = [] } = data;
  const { pageIndex, pageSize } = pagination;

  const resetPagination = () => {
    setPagination({ pageIndex: 0, pageSize });
  };

  const table = useReactTable({
    data: entries,
    columns: visitorColumns,
    state: {
      sorting,
      columnFilters: columnFilters,
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

  useEffect(() => {
    getVisitors({ page: pageIndex + 1, per_page: pageSize });
  }, [sorting, columnFilters, globalFilter, pageIndex, pageSize]);

  return (
    <>
      {/* Filtros */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search..."
            config={{
              filters: [
                {
                  columnId: 'host',
                  title: 'Host',
                  options: hosts,
                },
                {
                  columnId: 'state',
                  title: 'State',
                  options: states,
                },
              ],
              dateRange: { column: 'created_at', label: 'Created At' },
            }}
          />
        </div>
      </div>
      <div className="relative overflow-hidden rounded-md border">
        {isLoading && (
          <div className="absolute top-0 right-0 bottom-0 left-0 z-10 flex h-full w-full items-center justify-center bg-background">
            <div className="flex items-center justify-center space-x-2">
              <div className="h-4 w-4 animate-spin rounded-full border-b-2 border-foreground"></div>
              <span>Loading...</span>
            </div>
          </div>
        )}
        <Table className="relative">
          <DataTableHeader table={table} />
          <TableBody>
            <DataTableContent table={table} data={entries} />
          </TableBody>
        </Table>
      </div>
      {!isLoading && <DataTablePagination table={table} />}
    </>
  );
};
