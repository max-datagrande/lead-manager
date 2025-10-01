import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { Table, TableBody } from '@/components/ui/table';
import { usePostbacks } from '@/hooks/use-postbacks';
import { usePage } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { useEffect } from 'react';
//Icons
import { mapIcon } from '@/components/lucide-icon';

//Columns
import { createPostbackColumns } from './list-columns';
const postbackColumns = createPostbackColumns();
/**
 * Componente principal para mostrar la tabla de postbacks con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.entries - Datos de postbacks con información de paginación
 * @param {Object} props.meta - Datos de paginación
 * @param {Object} props.data - Datos de los diferentes tipos de postback
 * @returns {JSX.Element} Tabla completa con datos de postbacks y controles de paginación
 */
export default function TablePostbacks({ entries, meta, data }) {
  const {
    getPostbacks,
    columnFilters,
    setColumnFilters,
    sorting,
    setSorting,
    globalFilter,
    setGlobalFilter,
    isLoading,
    pagination,
    setPagination,
  } = usePostbacks();

  const vendorFilterOptions = data.vendorFilterOptions ?? [];
  const statusFilterOptions = mapIcon(data.statusFilterOptions ?? []);
  const { pageIndex, pageSize } = pagination;

  const table = useReactTable({
    data: entries,
    columns: postbackColumns,
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
    getPostbacks({ page: pageIndex + 1, per_page: pageSize });
  }, [sorting, columnFilters, globalFilter, pagination]);

  return (
    <>
      {/* Filtros */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search..."
            filters={[
              {
                columnId: 'vendor',
                title: 'Vendor',
                options: vendorFilterOptions,
              },
              {
                columnId: 'status',
                title: 'Status',
                options: statusFilterOptions,
              },
            ]}
          />
        </div>
      </div>
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} sorting={sorting} setSorting={setSorting} />
          <TableBody>
            <DataTableContent table={table} data={entries} isLoading={isLoading} />
          </TableBody>
        </Table>
      </div>
      <DataTablePagination table={table} />
    </>
  );
}
