import { Badge } from '@/components/ui/badge';
import { DataTableColumnHeader } from '@/components/data-table/column-header';

import { formatDateTime, formatDateTimeUTC, formatOnlyDate, formatOnlyDateUTC } from '@/utils/table';
import ReactCountryFlag from 'react-country-flag';
import BotBadge from './bot-badge';
import DeviceBadge from './device-badge';
import FingerprintCell from './fingerprint-cell';
import TrafficSourceBadge from './traffic-source-badge';

// --- Columnas TanStack ---
export const visitorColumns = [
  {
    accessorKey: 'fingerprint',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Short Fingerprint" />,
    cell: ({ row }) => {
      return <FingerprintCell fingerprint={row.original.fingerprint} />;
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'visit_date',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Visit Date" />,
    cell: ({ row }) => {
      return (
        <div className="text-sm">
          <div className="font-medium">{formatOnlyDate(row.original.visit_date)}</div>
          <div className="text-xs text-gray-500">{formatOnlyDateUTC(row.original.visit_date)}</div>
        </div>
      );
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'city',
    header: ({ column }) => <DataTableColumnHeader column={column} title="City" />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'state',
    header: ({ column }) => <DataTableColumnHeader column={column} title="State" />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'country_code',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Country" />,
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
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'device_type',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Device" />,
    cell: ({ row }) => <DeviceBadge deviceType={row.original.device_type} />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'browser',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Browser/OS" />,
    cell: ({ row }) => (
      <div className="text-sm">
        <div>{row.original.browser || 'Unknown'}</div>
        <div className="text-xs text-gray-500">{row.original.os || 'Unknown'}</div>
      </div>
    ),
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'utm_source',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Traffic Source" />,
    cell: ({ row }) => <TrafficSourceBadge source={row.original.utm_source} />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'visit_count',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Visits" />,
    cell: ({ row }) => <Badge variant="outline">{row.original.visit_count || 1}</Badge>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'is_bot',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: ({ row }) => <BotBadge isBot={row.original.is_bot} />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'host',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Host" />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created At" />,
    cell: ({ row }) => (
      <div className="text-sm">
        <div className="font-medium">{formatDateTime(row.original.created_at)}</div>
        <div className="text-xs text-gray-500">{formatDateTimeUTC(row.original.created_at)}</div>
      </div>
    ),
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'updated_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Updated At" />,
    cell: ({ row }) => (
      <div className="text-sm">
        <div className="font-medium">{formatDateTime(row.original.updated_at)}</div>
        <div className="text-xs text-gray-500">{formatDateTimeUTC(row.original.updated_at)}</div>
      </div>
    ),
    enableSorting: true,
    enableHiding: true,
  },
];
