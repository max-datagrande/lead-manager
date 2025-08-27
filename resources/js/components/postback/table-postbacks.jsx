import Paginator from '@/components/data-table/paginator';
import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { Table, TableBody } from '@/components/ui/table';
import { useEffect } from 'react';

import { postbackColumns } from './list-columns';

import { usePage } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';

import { usePostbacks } from '@/hooks/use-posbacks';

import { DataTableToolbar } from '@/components/data-table/toolbar';

import { mapIcon } from '@/components/lucide-icon';
/**
 * Componente principal para mostrar la tabla de visitantes con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.postbacks - Datos de visitantes con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de visitantes y controles de paginación
 */
export default function TablePostbacks({ postbacks }) {
  const {
    getPostbacks,
    columnFilters,
    setColumnFilters,
    sorting,
    setSorting,
    globalFilter,
    setGlobalFilter,
    showRequestViewer,
    setResetTrigger,
    resetTrigger,
    isLoading,
  } = usePostbacks();

  const { rows, meta, state, data } = usePage().props;
  const links = rows.links ?? [];
  const vendors = data.vendors ?? [];
  const states = mapIcon(data.states ?? []);

  const pageIndex = (state.page ?? 1) - 1;
  const pageSize = state.per_page ?? 10;

  const table = useReactTable({
    data: postbacks,
    columns: postbackColumns,
    state: {
      sorting,
      columnFilters: columnFilters,
      pagination: { pageIndex, pageSize },
      globalFilter,
    },
    meta: {
      showRequestViewer,
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
    getPostbacks({ page: pageIndex + 1, per_page: pageSize });
  }, [sorting, columnFilters, globalFilter]);

  return (
    <>
      {/* Filtros */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search..."
            resetTrigger={resetTrigger}
            setResetTrigger={setResetTrigger}
            globalQuery={globalFilter}
            setGlobalQuery={setGlobalFilter}
            filters={[
              {
                columnId: 'vendor',
                title: 'Vendor',
                options: vendors,
              },
              {
                columnId: 'status',
                title: 'Status',
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
            <DataTableContent table={table} data={postbacks} isLoading={isLoading} />
          </TableBody>
        </Table>
      </div>
      <Paginator pages={links} rows={rows} />
    </>
  );
}
