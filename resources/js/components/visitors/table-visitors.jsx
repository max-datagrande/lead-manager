import CellEmptyData from '@/components/table-empty-data';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, formatDateTimeUTC, getSortState, serializeSort } from '@/utils/table';
import { Link, router, usePage } from '@inertiajs/react';
import { getCoreRowModel, useReactTable } from '@tanstack/react-table';
import dayjs from 'dayjs';
import timezone from 'dayjs/plugin/timezone';
import utc from 'dayjs/plugin/utc';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import ReactCountryFlag from 'react-country-flag';
import BotBadge from './bot-badge';
import DeviceBadge from './device-badge';
import FingerprintCell from './fingerprint-cell';
import TrafficSourceBadge from './traffic-source-badge';

// Configurar plugins de dayjs
dayjs.extend(utc);
dayjs.extend(timezone);

// --- Columnas TanStack ---
const columns = [
  {
    accessorKey: 'fingerprint',
    header: 'Fingerprint',
    cell: ({ row }) => <FingerprintCell fingerprint={row.original.fingerprint} />,
    enableSorting: false,
  },
  { accessorKey: 'visit_date', header: 'Visit Date' },
  { accessorKey: 'city', header: 'City' },
  { accessorKey: 'state', header: 'State' },
  {
    accessorKey: 'country_code',
    header: 'Country',
    cell: ({ row }) => (
      <div className="flex items-center gap-2 text-sm">
        {console.log(row.original)}
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
  const { data, meta, state } = usePage().props;
  const { links, data: visitors } = data;
  // --- Estados controlados que viajan al backend ---
  const [globalFilter, setGlobalFilter] = useState(state.search ?? '');
  const [sorting, setSorting] = useState(state.sort ? getSortState(state.sort) : []);
  const [columnFilters, setColumnFilters] = useState(state.filters ?? []);
  const [pageIndex, setPageIndex] = useState((state.page ?? 1) - 1);
  const [pageSize, setPageSize] = useState(state.per_page ?? 10);
  function setFilter(id, value) {
    setColumnFilters((prev) => {
      const others = prev.filter((f) => f.id !== id);
      return value ? [...others, { id, value }] : others;
    });
  }
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
    const url = route('visitors.index');
    const data = {
      page: pageIndex + 1,
      per_page: pageSize,
      search: globalFilter || undefined,
      sort: serializeSort(sorting),
      filters: JSON.stringify(columnFilters || []),
    };
    const options = { only: ['data', 'meta', 'state'], replace: true, preserveState: true, preserveScroll: true };
    router.get(url, data, options);
  };
  useEffect(() => {
    const handler = setTimeout(() => {
      getRows();
    }, 200);
    return () => {
      clearTimeout(handler);
    };
  }, [sorting, columnFilters, pageIndex, pageSize, globalFilter]);

  return (
    <>
      {/* Filtros superiores */}
      <div className="flex flex-wrap items-center gap-2">
        <Input placeholder="Filter emails..." value={globalFilter} onChange={(e) => setGlobalFilter(e.target.value)} className="max-w-sm" />
        <select
          className="rounded border px-2 py-1"
          value={columnFilters.find((f) => f.id === 'traffic_source')?.value ?? ''}
          onChange={(e) => setFilter('traffic_source', e.target.value)}
        >
          <option value="">Any source</option>
          <option value="organic">organic</option>
          <option value="google">google</option>
          <option value="facebook">facebook</option>
          <option value="instagram">instagram</option>
          <option value="email">email</option>
          <option value="direct">direct</option>
          <option value="other">other</option>
        </select>

        <select
          className="rounded border px-2 py-1"
          value={columnFilters.find((f) => f.id === 'is_bot')?.value ?? ''}
          onChange={(e) => setFilter('is_bot', e.target.value)}
        >
          <option value="">Human/Bot</option>
          <option value="0">Human</option>
          <option value="1">Bot</option>
        </select>

        <input
          type="text"
          className="rounded border px-2 py-1"
          placeholder="Country code (e.g. US)"
          value={columnFilters.find((f) => f.id === 'country_code')?.value ?? ''}
          onChange={(e) => setFilter('country_code', e.target.value.toUpperCase())}
        />

        <input
          type="date"
          className="rounded border px-2 py-1"
          value={columnFilters.find((f) => f.id === 'visit_date_from')?.value ?? ''}
          onChange={(e) => setFilter('visit_date_from', e.target.value)}
        />
        <input
          type="date"
          className="rounded border px-2 py-1"
          value={columnFilters.find((f) => f.id === 'visit_date_to')?.value ?? ''}
          onChange={(e) => setFilter('visit_date_to', e.target.value)}
        />
      </div>
      <div className="rounded-md border">
        <Table>
          <TableHeader>
            {table.getHeaderGroups().map((hg) => (
              <TableRow key={hg.id}>
                {hg.headers.map((h) => (
                  <TableHead
                    key={h.id}
                    className="whitespace-nowrap"
                    onClick={() => {
                      if (!h.column.getCanSort?.()) return;
                      // alterna asc/desc
                      const id = h.column.id;
                      setSorting((prev) => {
                        const cur = prev[0]?.id === id ? prev[0] : null;
                        if (!cur) return [{ id, desc: false }];
                        if (cur && !cur.desc) return [{ id, desc: true }];
                        return [];
                      });
                    }}
                  >
                    {h.column.columnDef.header}
                  </TableHead>
                ))}
              </TableRow>
            ))}
          </TableHeader>
          <TableBody>
            {visitors.length === 0 && (
              <TableRow>
                <TableCell colSpan={columns.length}>
                  <CellEmptyData />
                </TableCell>
              </TableRow>
            )}
            {table.getRowModel().rows.map((r) => (
              <TableRow key={r.id}>
                {r.getVisibleCells().map((c) => (
                  <TableCell key={c.id} className="p-2">
                    {c.getValue()}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
      {/* Paginación */}
      {data.links && data.links.length > 3 && (
        <div className="flex items-center justify-between px-2 py-4">
          <div className="text-sm text-gray-500">
            Showing {data.from} to {data.to} of {data.total} results
          </div>
          <div className="flex items-center space-x-2">
            {data.links.map((link, index) => {
              if (link.label.includes('Previous')) {
                return (
                  <Button key={index} variant="outline" size="sm" dicsabled={!link.url} asChild={!!link.url}>
                    {link.url ? (
                      <Link href={link.url}>
                        <ChevronLeft className="h-4 w-4" />
                        Previous
                      </Link>
                    ) : (
                      <>
                        <ChevronLeft className="h-4 w-4" />
                        Previous
                      </>
                    )}
                  </Button>
                );
              }

              if (link.label.includes('Next')) {
                return (
                  <Button key={index} variant="outline" size="sm" disabled={!link.url} asChild={!!link.url}>
                    {link.url ? (
                      <Link href={link.url}>
                        Next
                        <ChevronRight className="h-4 w-4" />
                      </Link>
                    ) : (
                      <>
                        Next
                        <ChevronRight className="h-4 w-4" />
                      </>
                    )}
                  </Button>
                );
              }

              // Páginas numeradas
              return (
                <Button key={index} variant={link.active ? 'default' : 'outline'} size="sm" disabled={!link.url} asChild={!!link.url}>
                  {link.url ? <Link href={link.url}>{link.label}</Link> : link.label}
                </Button>
              );
            })}
          </div>
        </div>
      )}
    </>
  );
};
