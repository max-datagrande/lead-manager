import Paginator from '@/components/data-table/paginator';
import { Table, TableBody } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { useWhitelist } from '@/hooks/use-whitelist';
import { whitelistColumns } from './list-columns';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Plus } from 'lucide-react';

/**
 * Componente principal para mostrar la tabla de whitelist con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.entries - Datos de entradas whitelist con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de whitelist y controles de paginación
 */
export const TableWhitelist = ({ entries }) => {
  const {
    getWhitelistEntries,
    columnFilters,
    setColumnFilters,
    sorting,
    setSorting,
    globalFilter,
    setGlobalFilter,
    setResetTrigger,
    resetTrigger,
    isLoading,
    showCreateModal,
  } = useWhitelist();
  
  const { rows, meta, state, data } = usePage().props;
  const links = rows?.links ?? [];
  const types = data?.types ?? [
    { label: 'Domain', value: 'domain' },
    { label: 'IP Address', value: 'ip' }
  ];
  const statuses = data?.statuses ?? [
    { label: 'Active', value: '1' },
    { label: 'Inactive', value: '0' }
  ];

  const pageIndex = (state?.page ?? 1) - 1;
  const pageSize = state?.per_page ?? 10;

  const table = useReactTable({
    data: entries,
    columns: whitelistColumns,
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
    pageCount: meta?.last_page,
    getCoreRowModel: getCoreRowModel(),
  });

  useEffect(() => {
    getWhitelistEntries({ page: pageIndex + 1, per_page: pageSize });
  }, [sorting, columnFilters, globalFilter]);

  return (
    <>
      {/* Toolbar con filtros y botón crear */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search entries..."
            globalQuery={globalFilter}
            setGlobalQuery={setGlobalFilter}
            resetTrigger={resetTrigger}
            setResetTrigger={setResetTrigger}
            filters={[
              {
                columnId: 'type',
                title: 'Type',
                options: types,
              },
              {
                columnId: 'is_active',
                title: 'Status',
                options: statuses,
              },
            ]}
          />
          <Button onClick={showCreateModal} className="flex items-center gap-2">
            <Plus className="h-4 w-4" />
            Add Entry
          </Button>
        </div>
      </div>
      
      {/* Tabla */}
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} />
          <TableBody>
            <DataTableContent table={table} data={entries} isLoading={isLoading} />
          </TableBody>
        </Table>
      </div>
      
      {/* Paginación */}
      <Paginator pages={links} rows={rows} />
    </>
  );
};