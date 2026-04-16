import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';
import { Edit, Trash2 } from 'lucide-react';
import { useVersions } from '@/hooks/use-landings';

const StatusBadge = ({ status }) => (
  <Badge variant={status ? 'default' : 'destructive'}>
    {status ? 'Active' : 'Inactive'}
  </Badge>
);

const ActionsCell = ({ row }) => {
  const { showEditModal, showDeleteModal } = useVersions();
  const entry = row.original;

  return (
    <div className="flex items-center gap-2">
      <Button
        variant="ghost"
        size="sm"
        onClick={() => showEditModal(entry)}
        className="h-8 w-8 p-0"
      >
        <Edit className="h-4 w-4" />
      </Button>

      <Button
        variant="ghost"
        size="sm"
        onClick={() => showDeleteModal(entry)}
        className="h-8 w-8 p-0 text-destructive hover:text-destructive"
      >
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
  );
};

export const columns = [
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    enableSorting: true,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    enableSorting: true,
  },
  {
    accessorKey: 'description',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Description" />,
    enableSorting: false,
  },
  {
    accessorKey: 'fullUrl',
    header: ({ column }) => <DataTableColumnHeader column={column} title="URL" />,
    cell: ({ cell }) => (
      <a
        href={cell.getValue()}
        target="_blank"
        rel="noopener noreferrer"
        className="text-blue-500 underline text-sm"
      >
        {cell.getValue()}
      </a>
    ),
    enableSorting: false,
  },
  {
    accessorKey: 'status',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <StatusBadge status={row.original.status} />,
    enableSorting: true,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created At" />,
    cell: ({ row }) => (
      <div className="text-sm">
        <div className="font-medium">{formatDateTime(row.original.created_at)}</div>
        <div className="text-xs text-gray-500">
          {formatDateTimeUTC(row.original.created_at)}
        </div>
      </div>
    ),
    enableSorting: true,
  },
  {
    id: 'actions',
    header: 'Actions',
    cell: ActionsCell,
  },
];