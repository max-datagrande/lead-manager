import Paginator from '@/components/data-table/paginator';
import TableRowEmpty from '@/components/data-table/table-row-empty';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { useEffect } from 'react';

import { usePage } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';

import {SortingIcon} from '@/components/data-table/sorting-icon';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { useVisitors } from '@/hooks/use-visitors';
import { visitorColumns as columns } from './list-columns';

import { DataTableToolbar } from '@/components/data-table/toolbar';
/**
 * Componente principal para mostrar la tabla de visitantes con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.visitors - Datos de visitantes con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de visitantes y controles de paginación
 */
export const TableVisitors = ({ visitors }) => {
  const { getVisitors, columnFilters, setColumnFilters, sorting, setSorting, globalFilter, setGlobalFilter } = useVisitors();
  const { rows, meta, state, data } = usePage().props;
  const links = rows.links ?? [];
  const hosts = data.hosts ?? [];
  const states = data.states ?? [];

  const pageIndex = (state.page ?? 1) - 1;
  const pageSize = state.per_page ?? 10;
  console.log(columnFilters);

  const table = useReactTable({
    data: visitors,
    columns,
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
          >
            {/* Date Range Picker */}
            <DateRangePicker onUpdate={(values) => console.log(values)} align="start" locale="en-US" showCompare={false} />
          </DataTableToolbar>
        </div>
      </div>
      <div className="rounded-md border">
        <Table>
          <Headers table={table} sorting={sorting} setSorting={setSorting} />
          <TableBody>
            <Content table={table} visitors={visitors} />
          </TableBody>
        </Table>
      </div>
      <Paginator pages={links} rows={rows} />
    </>
  );
};

function Headers({ table, sorting, setSorting }) {
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

function Content({ table, visitors }) {
  if (visitors.length === 0) {
    return <TableRowEmpty colSpan={columns.length}>No visitors found.</TableRowEmpty>;
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
