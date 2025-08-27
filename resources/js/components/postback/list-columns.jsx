import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { capitalize } from '@/utils/string';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';
import { Eye } from 'lucide-react';


// --- Columnas TanStack ---
const vendors = {
  ni: 'Natural Intelligence',
};
export const postbackColumns = [
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Postback ID" />,
    cell: ({ row }) => <div className="w-[80px]">{row.getValue('id')}</div>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'offer_id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Offer ID" />,
    cell: ({ row }) => <div className="w-[80px]">{row.getValue('offer_id')}</div>,
    enableHiding: true,
    enableSorting: true,
  },
  {
    accessorKey: 'status',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row, cell }) => {
      const colors = {
        pending: 'secondary',
        processed: 'default',
        failed: 'destructive',
      };
      const value = cell.getValue();
      return <Badge variant={colors[value] ?? 'secondary'}>{capitalize(value)}</Badge>;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'clid',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Click ID" />,
    cell: ({ row }) => <div className="w-[80px] overflow-hidden text-ellipsis whitespace-nowrap">{row.getValue('clid')}</div>,
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'txid',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Transaction ID" />,
    cell: ({ row }) => <div className="w-[80px] overflow-hidden text-ellipsis whitespace-nowrap">{row.getValue('txid')}</div>,
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'vendor',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Vendor" />,
    cell: ({ row, cell }) => {
      const value = cell.getValue();
      return vendors[value] ?? `Unknown (${value})`;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'payout',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Payout" />,
    cell: ({ row, cell }) => {
      const currency = row.original.currency;
      const value = cell.getValue();
      return value ? `${value.toFixed(2)} ${currency}` : value;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'event',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Event Name" />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'failure_reason',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Is Failed" />,
    cell: ({ row, cell }) => {
      const hasError = row.original.failure_reason !== null && row.original.failure_reason !== '';
      if (hasError) {
        return <Badge variant="destructive">{cell.getValue()}</Badge>;
      }
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created At" />,
    cell: ({ row }) => (
      <div className="text-sm whitespace-nowrap">
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
      <div className="text-sm whitespace-nowrap">
        <div className="font-medium">{formatDateTime(row.original.updated_at)}</div>
        <div className="text-xs text-gray-500">{formatDateTimeUTC(row.original.updated_at)}</div>
      </div>
    ),
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'actions',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Actions" />,
    enableSorting: false,
    cell: ({ row, table }) => {
      const postback = row.original;
      const { showRequestViewer } = table.options.meta || {};

      return (
        <Button variant="black" size="sm" className="h-8 px-2" onClick={() => showRequestViewer(postback)}>
          <Eye className="mr-1 h-3 w-3" />
          API Requests
        </Button>
      );
    },
  },
];

