import Paginator from '@/components/data-table/paginator';
import TableRowEmpty from '@/components/data-table/table-row-empty';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { useEffect } from 'react';

import { usePage } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';

import {SortingIcon} from '@/components/data-table/sorting-icon';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { usePostbacks } from '@/hooks/use-posbacks';
import { postbackColumns as columns } from './list-columns';

import { DataTableToolbar } from '@/components/data-table/toolbar';

import { LucideIcon } from '@/components/lucide-icon';
/**
 * Componente principal para mostrar la tabla de visitantes con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.postbacks - Datos de visitantes con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de visitantes y controles de paginación
 */
export const TablePostbacks = ({ postbacks }) => {
  const { getPostbacks, columnFilters, setColumnFilters, sorting, setSorting, globalFilter, setGlobalFilter, showRequestViewer } = usePostbacks();
  const { rows, meta, state, data } = usePage().props;
  const links = rows.links ?? [];
  const vendors = data.vendors ?? [];
  let states = data.states ?? [];
  console.log(states);

  states = states.map((item) => {
    return {
      ...item,
      icon: ({ className }) => <LucideIcon name={item.iconName} className={className} size={16} />
    };
  });

  const pageIndex = (state.page ?? 1) - 1;
  const pageSize = state.per_page ?? 10;

  const table = useReactTable({
    data: postbacks,
    columns,
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
          >
            {/* Date Range Picker */}
            <DateRangePicker
              onUpdate={({ range: { from, to } }) => {
                // Obtener filtros actuales
                const currentFilters = table.getState().columnFilters;
                
                // Remover filtros de fecha existentes
                const otherFilters = currentFilters.filter(
                  filter => filter.id !== 'from_date' && filter.id !== 'to_date'
                );
                
                // Agregar nuevos filtros de fecha
                const newFilters = [
                  ...otherFilters,
                  { id: 'from_date', value: from.toISOString() },
                  { id: 'to_date', value: to.toISOString() }
                ];
                
                // Establecer todos los filtros
                table.setColumnFilters(newFilters);
              }}
              align="start"
              locale="en-US"
              showCompare={false}
            />
          </DataTableToolbar>
        </div>
      </div>
      <div className="rounded-md border">
        <Table>
          <Headers table={table} sorting={sorting} setSorting={setSorting} />
          <TableBody>
            <Content table={table} postbacks={postbacks} />
          </TableBody>
        </Table>
      </div>
      <Paginator pages={links} rows={rows} />
    </>
  );
};

function Headers({ table }) {
  return (
    <TableHeader>
      {table.getHeaderGroups().map((headerGroup) => (
        <TableRow key={headerGroup.id}>
          {headerGroup.headers.map((header) => (
            <TableHead
              key={header.id}
              className={cn('whitespace-nowrap select-none', header.column.getCanSort?.() && 'cursor-pointer hover:bg-muted/50')}
              onClick={header.column.getToggleSortingHandler?.()}
            >
              <div className="flex items-center">
                {flexRender(header.column.columnDef.header, header.getContext())}
                <SortingIcon column={header.column} />
              </div>
            </TableHead>
          ))}
        </TableRow>
      ))}
    </TableHeader>
  );
}

function Content({ table, postbacks }) {
  if (postbacks.length === 0) {
    return <TableRowEmpty colSpan={columns.length}>No postbacks found.</TableRowEmpty>;
  }
  const rowModel = table.getRowModel();
  return (
    <>
      {rowModel.rows.map((r) => (
        <TableRow key={r.id}>
          {r.getVisibleCells().map((cell) => (
            <TableCell key={cell.id} className="p-2">
              {flexRender(cell.column.columnDef.cell, cell.getContext())}
            </TableCell>
          ))}
        </TableRow>
      ))}
    </>
  );
}
