import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Eye } from 'lucide-react';

import { formatDateTime, formatDateTimeUTC } from '@/utils/table';
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
    cell: ({ row, table }) => {
      const { showLeadDataModal } = table.options.meta || {};
      return (
        <div className="flex items-center gap-2">
          {showLeadDataModal && (
            <Button
              variant="ghost"
              size="icon"
              className="h-6 w-6"
              onClick={() => showLeadDataModal(row.original)}
              title="View Details"
            >
              <Eye className="h-4 w-4" />
            </Button>
          )}
          <FingerprintCell fingerprint={row.original.fingerprint} />
        </div>
      );
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'host',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Host" />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'path_visited',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Path Visited" />,
    cell: ({ row }) => {
      if (!row.original.path_visited) {
        return null;
      }
      if (row.original.path_visited === '/') {
        return <div className="text-sm">Home</div>;
      }
      //Replace / at the end
      const path = row.original.path_visited.replace(/\/$/, '');
      return <div className="text-sm">{path}</div>;
    },
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
    accessorKey: 'state',
    header: ({ column }) => <DataTableColumnHeader column={column} title="State" />,
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
    accessorKey: 'postal_code',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Postal Code" />,
    enableSorting: true,
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
    accessorKey: 'is_bot',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: ({ row }) => <BotBadge isBot={row.original.is_bot} />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'platform',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Platform" />,
    cell: ({ row }) => <div className="text-sm">{row.original.platform ?? ''}</div>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'click_id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Campaign Click ID" />,
    cell: ({ row }) => {
      if (!row.original.click_id) {
        return null;
      }
      const truncated = row.original.click_id.substring(row.original.click_id.length - 20);
      return (
        <span className="text-xs" title={row.original.click_id}>
          {truncated}...
        </span>
      );
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'utm_campaign_name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="UTM Campaign" />,
    cell: ({ row }) => <div className="text-sm">{row.original.utm_campaign_name ?? ''}</div>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'utm_content',
    header: ({ column }) => <DataTableColumnHeader column={column} title="UTM Content" />,
    cell: ({ row }) => <div className="text-sm">{row.original.utm_content ?? ''}</div>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'utm_source',
    header: ({ column }) => <DataTableColumnHeader column={column} title="UTM Source" />,
    cell: ({ row }) => {
      if (!row.original.utm_source) {
        return null;
      }
      const utmSource = row.original.utm_source ?? '';
      const utmMedium = row.original.utm_medium ?? '';
      return <TrafficSourceBadge source={utmSource} medium={utmMedium} />
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'channel',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Channel" />,
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
    accessorKey: 'referrer',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Referrer" />,
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
