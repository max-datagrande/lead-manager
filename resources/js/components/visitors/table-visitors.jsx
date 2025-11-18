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
  const {
    getVisitors,
    columnFilters,
    setColumnFilters,
    sorting,
    setSorting,
    globalFilter,
    setGlobalFilter,
    setResetTrigger,
    resetTrigger,
    isLoading,
    pagination,
    setPagination,
  } = useVisitors();
  const { hosts = [], states = [] } = data;
  const { pageIndex, pageSize } = pagination;

  const table = useReactTable({
    data: entries,
    columns: visitorColumns,
    state: {
      sorting,
      columnFilters: columnFilters,
      pagination: { pageIndex, pageSize },
      globalFilter,
    },
    onSortingChange: (sortingUpdate) => {
      const newSorting = typeof sortingUpdate === 'function' ? sortingUpdate(sorting) : sortingUpdate;
      setSorting(newSorting);
    },
    onPaginationChange: (paginationUpdate) => {
      const newPagination = typeof paginationUpdate === 'function' ? paginationUpdate(pagination) : paginationUpdate;
      setPagination(newPagination);
    },
    onColumnFiltersChange: (filtersUpdate) => {
      const newFilters = typeof filtersUpdate === 'function' ? filtersUpdate(columnFilters) : filtersUpdate;
      setColumnFilters(newFilters);
    },
    onGlobalFilterChange: setGlobalFilter,
    manualSorting: true,
    manualFiltering: true,
    manualPagination: true,
    pageCount: meta.last_page,
    getCoreRowModel: getCoreRowModel(),
  });

  useEffect(() => {
    getVisitors({ page: pageIndex + 1, per_page: pageSize });
  }, [sorting, columnFilters, globalFilter]);

  return (
    <>
      {/* Filtros */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search..."
            filterByColumn="created_at"
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
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} />
          <TableBody>
            <DataTableContent table={table} data={entries} isLoading={isLoading} />
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} />
    </>
  );
};
