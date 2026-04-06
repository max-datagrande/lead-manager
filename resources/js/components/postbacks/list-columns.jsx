import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePostbacks } from '@/hooks/use-postbacks';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';
import { router } from '@inertiajs/react';
import { Copy, Edit, Eye, Play, Trash2 } from 'lucide-react';

const ActionsCell = ({ row }) => {
  const { showModalDetails, copyUrl, showEditModal, showDeleteModal } = usePostbacks();
  const entry = row.original;

  return (
    <div className="flex items-center gap-1">
      <Button variant="ghost" size="sm" onClick={() => showModalDetails(entry)} className="h-8 w-8 p-0">
        <Eye className="h-4 w-4" />
      </Button>
      {entry.type !== 'internal' && (
        <Button variant="ghost" size="sm" onClick={() => copyUrl(entry)} className="h-8 w-8 p-0">
          <Copy className="h-4 w-4" />
        </Button>
      )}
      {entry.type === 'internal' && (
        <Button variant="ghost" size="sm" onClick={() => router.visit(route('postbacks.internal.fire-form', entry.id))} className="h-8 w-8 p-0 text-primary hover:text-primary">
          <Play className="h-4 w-4" />
        </Button>
      )}
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
    cell: ({ row }) => <span className="font-medium">{row.original.name}</span>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'type',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: ({ row }) => {
      const type = row.original.type;
      return (
        <Badge variant={type === 'internal' ? 'secondary' : 'outline'} className="capitalize">
          {type}
        </Badge>
      );
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'platform',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Platform" />,
    cell: ({ row }) => row.original.platform?.name ?? <span className="text-muted-foreground">—</span>,
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'Postback Url',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Postback URL" />,
    cell: ({ row }) => {
      const url = row.original.generated_url;
      if (!url) return <span className="text-muted-foreground">—</span>;
      return (
        <span className="block max-w-xs truncate font-mono text-xs text-muted-foreground" title={url}>
          {url}
        </span>
      );
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'fire_mode',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Fire Mode" />,
    cell: ({ row }) => {
      const mode = row.original.fire_mode;
      return (
        <Badge variant={mode === 'realtime' ? 'default' : 'secondary'} className="capitalize">
          {mode}
        </Badge>
      );
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => {
      const active = row.original.is_active;
      return <Badge variant={active ? 'success' : 'outline'}>{active ? 'Active' : 'Inactive'}</Badge>;
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
