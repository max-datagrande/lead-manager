import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTablePagination } from '@/components/data-table/table-pagination';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { useWhitelist } from '@/hooks/use-whitelist';
import { getCoreRowModel, getFilteredRowModel, getPaginationRowModel, getSortedRowModel, useReactTable } from '@tanstack/react-table';
import { whitelistColumns } from './list-columns';
/**
 * Componente principal para mostrar la tabla de whitelist con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.entries - Datos de entradas whitelist con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de whitelist y controles de paginación
 */
export const TableWhitelist = ({ entries }) => {
  const { resetTrigger, setResetTrigger, sorting, setSorting, globalFilter, setGlobalFilter, pagination, setPagination } = useWhitelist();
  const types = [
    { label: 'Domain', value: 'domain' },
    { label: 'IP Address', value: 'ip' },
  ];

  const statuses = [
    { label: 'Active', value: 1 },
    { label: 'Inactive', value: 0 },
  ];

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
    filterFns: {
      booleanFilter: (row, columnId, filterValue) => {
        const cellValue = row.getValue(columnId);
        return filterValue.includes(cellValue);
      },
    },
  });

  return (
    <>
      {/* Toolbar con filtros y botón crear */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search entries..."
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
        </div>
      </div>

      {/* Tabla */}
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
};
