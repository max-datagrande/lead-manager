import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePostbacks } from '@/hooks/use-posbacks';
import { capitalize } from '@/utils/string';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';
import { Eye, Trash2 } from 'lucide-react';


// --- Columnas TanStack ---
const vendors = {
  ni: 'Natural Intelligence',
};

// Componente para las acciones de la fila
const ActionsCell = ({ row }) => {
  const postback = row.original;
  const { showDeleteModal, showRequestViewer } = usePostbacks();
  return (
    <div className="flex items-center gap-2">
      <Button variant="black" size="sm" className="h-8 px-2" onClick={() => showRequestViewer(postback)}>
        <Eye className="mr-1 h-3 w-3" />
        API Requests
      </Button>
      <Button variant="destructive" size="sm" onClick={() => showDeleteModal(postback)} className="h-8 w-8 p-0">
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
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
    header: 'Actions',
    cell: ActionsCell,
    enableSorting: false,
    enableHiding: false,
  },
];
