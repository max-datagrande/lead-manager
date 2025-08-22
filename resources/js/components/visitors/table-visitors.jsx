import { cn } from '@/lib/utils';
import Paginator from '@/components/table/paginator';
import TableRowEmpty from '@/components/table/table-row-empty';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  formatDateTime,
  formatDateTimeUTC,
  formatOnlyDate,
  formatOnlyDateUTC,
  getSortState,
  serializeSort,
  toggleColumnSorting,
} from '@/utils/table';

import { router, usePage } from '@inertiajs/react';
import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table';
import { useEffect, useRef, useState } from 'react';
import ReactCountryFlag from 'react-country-flag';
import BotBadge from './bot-badge';
import DeviceBadge from './device-badge';
import FingerprintCell from './fingerprint-cell';
import TrafficSourceBadge from './traffic-source-badge';
import { VisitorFilters } from './visitor-filters';

import SelectColumnVisibility from '@/components/table/select-column-visibility';
import SortingIcon from '@/components/table/sorting-icon';
import { DateRangePicker } from '@/components/ui/date-range-picker';

// --- Columnas TanStack ---
const columns = [
  {
    accessorKey: 'fingerprint',
    header: 'Fingerprint',
    cell: ({ row }) => {
      return <FingerprintCell fingerprint={row.original.fingerprint} />;
    },
    enableSorting: false,
  },
  {
    accessorKey: 'visit_date',
    header: 'Visit Date',
    cell: ({ row }) => {
      return (
        <div className="text-sm">
          <div className="font-medium">{formatOnlyDate(row.original.visit_date)}</div>
          <div className="text-xs text-gray-500">{formatOnlyDateUTC(row.original.visit_date)}</div>
        </div>
      );
    },
  },
  { accessorKey: 'city', header: 'City' },
  { accessorKey: 'state', header: 'State' },
  {
    accessorKey: 'country_code',
    header: 'Country',
    cell: ({ row }) => (
      <div className="flex items-center gap-2 text-sm">
        {row.original.country_code && (
          <ReactCountryFlag
            countryCode={row.original.country_code}
            svg
            style={{ width: '1.2em', height: '1.2em' }}
            title={row.original.country_code}
          />
        )}
        <span>{row.original.country_code || 'N/A'}</span>
      </div>
    ),
  },
  { accessorKey: 'device_type', header: 'Device', cell: ({ row }) => <DeviceBadge deviceType={row.original.device_type} /> },
  {
    accessorKey: 'browser',
    header: 'Browser/OS',
    cell: ({ row }) => (
      <div className="text-sm">
        <div>{row.original.browser || 'Unknown'}</div>
        <div className="text-xs text-gray-500">{row.original.os || 'Unknown'}</div>
      </div>
    ),
  },
  { accessorKey: 'traffic_source', header: 'Traffic Source', cell: ({ row }) => <TrafficSourceBadge source={row.original.traffic_source} /> },
  { accessorKey: 'visit_count', header: 'Visits', cell: ({ row }) => <Badge variant="outline">{row.original.visit_count || 1}</Badge> },
  { accessorKey: 'is_bot', header: 'Type', cell: ({ row }) => <BotBadge isBot={row.original.is_bot} /> },
  { accessorKey: 'host', header: 'Host' },
  {
    accessorKey: 'created_at',
    header: 'Created At',
    cell: ({ row }) => (
      <div className="text-sm">
        <div className="font-medium">{formatDateTime(row.original.created_at)}</div>
        <div className="text-xs text-gray-500">{formatDateTimeUTC(row.original.created_at)}</div>
      </div>
    ),
  },
  {
    accessorKey: 'updated_at',
    header: 'Updated At',
    cell: ({ row }) => (
      <div className="text-sm">
        <div className="font-medium">{formatDateTime(row.original.updated_at)}</div>
        <div className="text-xs text-gray-500">{formatDateTimeUTC(row.original.updated_at)}</div>
      </div>
    ),
  },
];

/**
 * Componente principal para mostrar la tabla de visitantes con paginación
 *
 * @param {Object} props - Propiedades del componente
 * @param {Object} props.visitors - Datos de visitantes con información de paginación
 * @returns {JSX.Element} Tabla completa con datos de visitantes y controles de paginación
 */
export const TableVisitors = () => {
  const { rows, meta, state } = usePage().props;
  const visitors = rows.data ?? [];
  const links = rows.links ?? [];
  // --- Estados controlados que viajan al backend ---
  const [globalFilter, setGlobalFilter] = useState(state.search ?? '');
  const [sorting, setSorting] = useState(state.sort ? getSortState(state.sort) : []);
  const [columnFilters, setColumnFilters] = useState(state.filters ?? []);

  const pageIndex = (state.page ?? 1) - 1;
  const pageSize = state.per_page ?? 10;
  const firstRender = useRef(true);

  const setFilter = (id, value) => {
    setColumnFilters((prev) => {
      const others = prev.filter((f) => f.id !== id);
      return value ? [...others, { id, value }] : others;
    });
  }

  // Función para limpiar todos los filtros
  const handleClearFilters = () => {
    setColumnFilters([]);
  };

  const table = useReactTable({
    data: visitors,
    columns,
    state: {
      sorting,
      columnFilters,
      pagination: { pageIndex, pageSize },
      globalFilter,
    },
    manualSorting: true,
    manualFiltering: true,
    manualPagination: true,
    pageCount: meta.last_page,
    getCoreRowModel: getCoreRowModel(),
  });

  // --- Navegación (debounce) ---
  const getRows = () => {
    if (firstRender.current) {
      firstRender.current = false;
      return;
    }
    console.log('Ejecutando');
    const url = route('visitors.index');
    const data = {
      page: pageIndex + 1,
      per_page: pageSize,
      search: globalFilter || undefined,
      sort: serializeSort(sorting),
      filters: JSON.stringify(columnFilters || []),
    };
    const options = { only: ['rows', 'meta', 'state'], replace: true, preserveState: true, preserveScroll: true };
    router.get(url, data, options);
  };

  useEffect(() => {
    const handler = setTimeout(() => {
      getRows();
    }, 200);
    return () => {
      clearTimeout(handler);
    };
  }, [sorting, columnFilters, globalFilter]);

  return (
    <>
      {/* Filtros */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between">
          {/* Global Search */}
          <Input placeholder="Search..." value={globalFilter ?? ''} onChange={(event) => setGlobalFilter(event.target.value)} className="max-w-sm" />
          <DateRangePicker
            onUpdate={(values) => console.log(values)}
            align="start"
            locale="en-US"
            showCompare={false}
          />
          {/* Column Visibility */}
          <SelectColumnVisibility columns={table.getAllColumns()} />
        </div>

        {/* Filtros Avanzados */}
        <VisitorFilters columnFilters={columnFilters} onFiltersChange={setColumnFilters} onClearFilters={handleClearFilters} />
      </div>
      <div className="rounded-md border">
        <Table>
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
          <TableBody>
            {visitors.length === 0 && <TableRowEmpty colSpan={columns.length}>No visitors found.</TableRowEmpty>}
            {table.getRowModel().rows.map((r) => (
              <TableRow key={r.id}>
                {r.getVisibleCells().map((cell) => (
                  <TableCell key={cell.id} className="p-2">
                    {flexRender(cell.column.columnDef.cell, cell.getContext())}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      <Paginator pages={links} rows={rows} />
    </>
  );
};

