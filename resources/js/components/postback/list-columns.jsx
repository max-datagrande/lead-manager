import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { usePostbacks } from '@/hooks/use-postbacks';
import { capitalize } from '@/utils/string';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';
import { MoreHorizontal } from 'lucide-react';

// --- Columnas TanStack ---
const vendors = {
  ni: 'Natural Intelligence',
};

// Componente para las acciones de la fila
const ActionsCell = ({ row }) => {
  const postback = row.original;
  const { showDeleteModal, showRequestViewer, showStatusModal } = usePostbacks();
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-8 w-8 p-0">
          <span className="sr-only">Open menu</span>
          <MoreHorizontal className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onClick={() => showStatusModal(postback)}>
          Change Status
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => showRequestViewer(postback)}>
          View API Requests
        </DropdownMenuItem>
        <DropdownMenuItem onClick={() => showDeleteModal(postback)} className="text-red-600">
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
};

export const postbackColumns = [
  {
    accessorKey: 'id',
    cessorKey: 'payout',
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
    accessorKey: 'click_id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Click ID" />,
    cell: ({ row }) => {
      return <div className="w-[80px] overflow-hidden text-ellipsis whitespace-nowrap">{row.getValue('click_id')}</div>;
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'transaction_id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Transaction ID" />,
    cell: ({ row }) => <div className="w-[80px] overflow-hidden text-ellipsis whitespace-nowrap">{row.getValue('transaction_id')}</div>,
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
      const cellValue = cell.getValue();
      return `${Number(cellValue).toFixed(2)} ${currency}` ?? cellValue;
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
    accessorKey: 'message',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Message" />,
    cell: ({ row }) => {
        const { status, message } = row.original;
        if (status === 'failed') {
            return <Badge variant="destructive">{message}</Badge>;
        }
        if (message) {
            return <span className="text-muted-foreground text-sm">{message}</span>;
        }
        const defaultMessages = {
            processed: 'Processed successfully',
            pending: 'Pending verification',
        };
        return <span className="text-muted-foreground/50 text-sm italic">{defaultMessages[status] ?? ''}</span>;
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
    header: 'Actions',
    cell: ActionsCell,
    enableSorting: false,
    enableHiding: false,
  },
];
