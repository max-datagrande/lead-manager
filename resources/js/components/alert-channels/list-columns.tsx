import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAlertChannels } from '@/hooks/use-alert-channels';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';
import { Edit, Trash2 } from 'lucide-react';

const TypeBadge = ({ type }: { type: string }) => {
  const { channelTypes } = useAlertChannels();
  const found = channelTypes.find((t) => t.value === type);
  return <Badge variant="outline">{found?.label ?? type}</Badge>;
};

const StatusBadge = ({ isActive }: { isActive: boolean }) => (
  <Badge variant={isActive ? 'default' : 'destructive'}>{isActive ? 'Active' : 'Inactive'}</Badge>
);

const ActionsCell = ({ row }) => {
  const { showEditModal, showDeleteModal } = useAlertChannels();
  const entry = row.original;
  return (
    <div className="flex items-center gap-2">
      <Button variant="ghost" size="sm" onClick={() => showEditModal(entry)} className="h-8 w-8 p-0">
        <Edit className="h-4 w-4" />
      </Button>
      <Button variant="ghost" size="sm" onClick={() => showDeleteModal(entry)} className="h-8 w-8 p-0 text-destructive hover:text-destructive">
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
  );
};

export const columns = [
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    cell: ({ cell }) => <div className="px-2">{cell.getValue()}</div>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'type',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: ({ row }) => <TypeBadge type={row.original.type} />,
    filterFn: (row, columnId, filterValue: string[]) => {
      if (!filterValue?.length) return true;
      return filterValue.includes(row.original.type);
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'webhook_url',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Webhook URL" />,
    cell: ({ cell }) => (
      <div className="max-w-[300px] truncate text-sm text-muted-foreground" title={cell.getValue() as string}>
        {cell.getValue() as string}
      </div>
    ),
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'active',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <StatusBadge isActive={row.original.active} />,
    filterFn: (row, columnId, filterValue: string[]) => {
      if (!filterValue?.length) return true;
      return filterValue.includes(String(row.original.active));
    },
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
    id: 'actions',
    header: 'Actions',
    cell: ActionsCell,
    enableSorting: false,
    enableHiding: false,
  },
];
