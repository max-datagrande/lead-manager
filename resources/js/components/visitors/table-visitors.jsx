import Paginator from '@/components/data-table/paginator';

import { Table, TableBody} from '@/components/ui/table';
import { useEffect } from 'react';

import { usePage } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';

import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { useVisitors } from '@/hooks/use-visitors';
import { visitorColumns } from './list-columns';

import { DataTableToolbar } from '@/components/data-table/toolbar';
/**
 * Componente principal para mostrar la tabla de visitantes con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.visitors - Datos de visitantes con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de visitantes y controles de paginación
 */
export const TableVisitors = ({ visitors }) => {
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
  } = useVisitors();
  const { rows, meta, state, data } = usePage().props;
  const links = rows.links ?? [];
  const hosts = data.hosts ?? [];
  const states = data.states ?? [];

  const pageIndex = (state.page ?? 1) - 1;
  const pageSize = state.per_page ?? 10;

  const table = useReactTable({
    data: visitors,
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
            globalQuery={globalFilter}
            setGlobalQuery={setGlobalFilter}
            resetTrigger={resetTrigger}
            setResetTrigger={setResetTrigger}
            filters={[
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
            ]}
          />
        </div>
      </div>
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} sorting={sorting} setSorting={setSorting} />
          <TableBody>
            <DataTableContent table={table} data={visitors} isLoading={isLoading} />
          </TableBody>
        </Table>
      </div>
      <Paginator pages={links} rows={rows} />
    </>
  );
};
