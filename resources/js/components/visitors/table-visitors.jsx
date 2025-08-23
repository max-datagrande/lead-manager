import Paginator from '@/components/table/paginator';
import TableRowEmpty from '@/components/table/table-row-empty';

import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { toggleColumnSorting } from '@/utils/table';
import { useEffect } from 'react';

import { usePage } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';

import { ComboboxUnique } from '@/components/filters/combo-unique';
import SelectColumnVisibility from '@/components/table/select-column-visibility';
import SortingIcon from '@/components/table/sorting-icon';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { useVisitors } from '@/context/visitors-provider';
import { visitorColumns as columns } from './list-columns';

/**
 * Componente principal para mostrar la tabla de visitantes con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.visitors - Datos de visitantes con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de visitantes y controles de paginación
 */
export const TableVisitors = () => {
  const { getVisitors, setFilter, columnFilters, sorting, setSorting, firstRender, globalFilter, setGlobalFilter } = useVisitors();
  const { rows, meta, state, data } = usePage().props;
  const visitors = rows.data ?? [];
  const links = rows.links ?? [];
  const { hosts } = data;

  const pageIndex = (state.page ?? 1) - 1;
  const pageSize = state.per_page ?? 10;

  const table = useReactTable({
    data: visitors,
    columns,
    state: {
      sorting,
      columnFilters: columnFilters,
      pagination: { pageIndex, pageSize },
      globalFilter,
    },
    manualSorting: true,
    manualFiltering: true,
    manualPagination: true,
    pageCount: meta.last_page,
    getCoreRowModel: getCoreRowModel(),
  });

  //Reload data on page change
  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false;
      return;
    }
    const handler = setTimeout(() => {
      getVisitors({ page: pageIndex + 1, per_page: pageSize });
    }, 200);
    return () => {
      clearTimeout(handler);
    };
  }, [sorting, columnFilters, globalFilter]);

  return (
    <>
      {/* Filtros */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          {/* Global Search */}
          <Input placeholder="Search..." value={globalFilter ?? ''} onChange={(event) => setGlobalFilter(event.target.value)} className="max-w-sm" />
          {/* Host */}
          <ComboboxUnique
            items={hosts}
            label="Host"
            currentValue={columnFilters.find((f) => f.id === 'host')?.value || ''}
            onChange={(value) => {
              setFilter('host', value);
            }}
          />
          {/* Date Range Picker */}
          <DateRangePicker onUpdate={(values) => console.log(values)} align="start" locale="en-US" showCompare={false} />
          {/* Column Visibility */}
          <SelectColumnVisibility columns={table.getAllColumns()} />
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
              onClick={() => {
                const canSorted = header.column.getCanSort?.();
                if (!canSorted) return;
                const columnId = header.column.id;
                setSorting((prev) => toggleColumnSorting(prev, columnId));
              }}
            >
              <div className="flex items-center">
                {header.column.columnDef.header}
                <SortingIcon column={header.column} sorting={sorting} />
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
