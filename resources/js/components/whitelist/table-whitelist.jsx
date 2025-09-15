import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Button } from '@/components/ui/button';
import { Table, TableBody } from '@/components/ui/table';
import { useWhitelist } from '@/hooks/use-whitelist';
import { usePage } from '@inertiajs/react';
import { getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { whitelistColumns } from './list-columns';
import { getSortState } from '@/utils/table';
/**
 * Componente principal para mostrar la tabla de whitelist con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.entries - Datos de entradas whitelist con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de whitelist y controles de paginación
 */
export const TableWhitelist = ({ entries }) => {
  const { isLoading, showCreateModal } = useWhitelist();
  const { filters } = usePage().props;
  const { sort } = filters;
  const types = [
    { label: 'Domain', value: 'domain' },
    { label: 'IP Address', value: 'ip' },
  ];

  const statuses = [
    { label: 'Active', value: 'true' },
    { label: 'Inactive', value: 'false' },
  ];

  const [resetTrigger, setResetTrigger] = useState(false);
  // Estados para sorting y filtering
  const [sorting, setSorting] = useState(sort ? getSortState(sort) : []);
  const [globalFilter, setGlobalFilter] = useState('');
  const [pagination, setPagination] = useState({
    pageIndex: 0, //initial page index
    pageSize: 10, //default page size
  });

  const table = useReactTable({
    data: entries,
    columns: whitelistColumns,
    state: {
      sorting,
      globalFilter,
      pagination,
    },
    onPaginationChange: setPagination,
    onPageSizeChange: setPagination,
    onSortingChange: setSorting,
    onGlobalFilterChange: setGlobalFilter,
    getPaginationRowModel: getPaginationRowModel(),
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    rowCount: entries.length,
    globalFilterFn: 'includesString',
  });

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
      <DataTablePagination table={table} />
    </>
  );
};
