import CopyToClipboard from '@/components/copy-to-clipboard';
import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { usePostbacks } from '@/hooks/use-postbacks';
import { capitalize } from '@/utils/string';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';
import { MoreHorizontal } from 'lucide-react';
// --- Columnas TanStack ---
const vendors = {
  ni: 'Natural Intelligence',
};

// Componente para las acciones de la fila
function ActionsCell({ row }) {
  const { showDeleteModal, showRequestViewer, showStatusModal, handleForceSync } = usePostbacks();
  const postback = row.original;
  const canForceSync = postback.status === 'pending' || postback.status === 'failed';

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-8 w-8 p-0">
          <span className="sr-only">Open menu</span>
          <MoreHorizontal className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {canForceSync && (
          <DropdownMenuItem className="cursor-pointer" onClick={() => handleForceSync(postback)}>
            Force Sync
          </DropdownMenuItem>
        )}
        <DropdownMenuItem className="cursor-pointer" onClick={() => showStatusModal(postback)}>
          Change Status
        </DropdownMenuItem>
        <DropdownMenuItem className="cursor-pointer" onClick={() => showRequestViewer(postback)}>
          View API Requests
        </DropdownMenuItem>
        <DropdownMenuItem className="cursor-pointer text-red-600" onClick={() => showDeleteModal(postback)}>
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export const createPostbackColumns = () => [
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
      const { status, message } = row.original;
      const colors = {
        pending: 'secondary',
        processed: 'default',
        failed: 'destructive',
      };

      let tooltipContent = message;
      if (!tooltipContent) {
        const defaultMessages = {
          processed: 'Processed successfully',
          pending: 'Pending verification',
          failed: 'Failed with unknown error',
        };
        tooltipContent = defaultMessages[status] ?? '';
      }
      const value = cell.getValue();
      return (
        <Tooltip>
          <TooltipTrigger asChild>
            <Badge variant={colors[value] ?? 'secondary'}>{capitalize(value)}</Badge>
          </TooltipTrigger>
          <TooltipContent className="bg-black text-white" arrowClassName="bg-black fill-black">
            <p>{tooltipContent}</p>
          </TooltipContent>
        </Tooltip>
      );
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'click_id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Click ID" />,
    cell: ({ row }) => {
      const value = row.getValue('click_id');
      return (
        <CopyToClipboard textToCopy={value}>
          <span className="cursor-help font-mono text-xs whitespace-nowrap" title={value}>
            {value}
          </span>
        </CopyToClipboard>
      );
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
      const isNull = cellValue === null;
      return isNull ? '' : `${Number(cellValue).toFixed(2)} ${currency}` ?? cellValue;
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
