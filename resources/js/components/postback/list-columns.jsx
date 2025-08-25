import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
    header: 'ID',
    enableSorting: true,
  },
  {
    accessorKey: 'offer_id',
    header: 'Offer ID',
    enableSorting: true,
  },
  {
    accessorKey: 'status',
    header: 'Status',
    enableSorting: true,
    cell: ({ row, cell }) => {
      const colors = {
        pending: 'secondary',
        processed: 'default',
        failed: 'destructive',
      };
      const value = cell.getValue();
      return <Badge variant={colors[value] ?? 'secondary'}>{capitalize(value)}</Badge>;
    },
  },
  {
    accessorKey: 'clid',
    header: 'Click ID',
    enableSorting: false,
  },
  {
    accessorKey: 'txid',
    header: 'Transaction ID',
    enableSorting: false,
  },
  {
    accessorKey: 'vendor',
    header: 'Vendor',
    enableSorting: true,
    cell: ({ row, cell }) => {
      const value = cell.getValue();
      return vendors[value] ?? `Unknown (${value})`;
    },
  },
  {
    accessorKey: 'payout',
    header: 'Payout',
    enableSorting: true,
    cell: ({ row, cell }) => {
      const currency = row.original.currency;
      const value = cell.getValue();
      return value ? `${value.toFixed(2)} ${currency}` : value;
    },
  },
  {
    accessorKey: 'event',
    header: 'Event Name',
    enableSorting: true,
  },
  {
    accessorKey: 'failure_reason',
    header: 'Is Failed',
    enableSorting: true,
    cell: ({ row, cell }) => {
      const hasError = row.original.failure_reason !== null && row.original.failure_reason !== '';
      if (hasError) {
        return <Badge variant="destructive">{cell.getValue()}</Badge>;
      }
    },
  },
  {
    accessorKey: 'created_at',
    header: 'Created At',
    cell: ({ row }) => (
      <div className="text-sm whitespace-nowrap">
        <div className="font-medium">{formatDateTime(row.original.created_at)}</div>
        <div className="text-xs text-gray-500">{formatDateTimeUTC(row.original.created_at)}</div>
      </div>
    ),
  },
  {
    accessorKey: 'updated_at',
    header: 'Updated At',
    cell: ({ row }) => (
      <div className="text-sm whitespace-nowrap">
        <div className="font-medium">{formatDateTime(row.original.updated_at)}</div>
        <div className="text-xs text-gray-500">{formatDateTimeUTC(row.original.updated_at)}</div>
      </div>
    ),
  },
  {
    id: 'actions',
    header: 'Shares',
    enableSorting: false,
    cell: ({ row, table }) => {
      const postback = row.original;
      const { showRequestViewer } = table.options.meta || {};

      return (
        <Button variant="black" size="sm" className="h-8 px-2" onClick={() => showRequestViewer?.(postback)}>
          <Eye className="mr-1 h-3 w-3" />
          API Requests
        </Button>
      );
    },
  },
];
/*
return (
        <div className="flex items-center gap-2">
          <Dialog>
            <DialogTrigger asChild>
              <Button variant="outline" size="sm" className="h-8 px-2">
                <Eye className="h-3 w-3 mr-1" />
                API Requests
              </Button>
            </DialogTrigger>
            <DialogContent className="max-w-4xl max-h-[80vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>
                  API Requests - Postback #{postback.id}
                </DialogTitle>
              </DialogHeader>

            </DialogContent>
          </Dialog>
        </div>
      );
*/
