import { Badge } from '@/components/ui/badge';
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
