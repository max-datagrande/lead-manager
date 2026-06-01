import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { FormattedDateTime } from '@/components/formatted-date-time';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useLandings } from '@/hooks/use-landings';
import { Edit, SquareArrowOutUpRight, Trash2 } from 'lucide-react';

const StatusBadge = ({ isActive }) => <Badge variant={isActive ? 'default' : 'destructive'}>{isActive ? 'Active' : 'Inactive'}</Badge>;

const ActionsCell = ({ row }) => {
  const { showEditModal, showDeleteModal, showVersions } = useLandings();
  const entry = row.original;
  return (
    <div className="flex items-center gap-2">
      <Button variant="ghost" size="sm" onClick={() => showEditModal(entry)} className="h-8 w-8 p-0">
        <Edit className="h-4 w-4" />
      </Button>
      <Button variant="ghost" size="sm" onClick={() => showDeleteModal(entry)} className="h-8 w-8 p-0 text-destructive hover:text-destructive">
        <Trash2 className="h-4 w-4" />
      </Button>
      <Button variant="ghost" size="sm" onClick={() => showVersions(entry)} className="h-8 w-8 p-0">
        <SquareArrowOutUpRight className="h-4 w-4" />
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
    accessorKey: 'url',
    header: ({ column }) => <DataTableColumnHeader column={column} title="URL" />,
    cell: ({ cell }) => (
      <a href={cell.getValue()} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-500 underline">
        {cell.getValue()}
      </a>
    ),
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'vertical',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Vertical" />,
    cell: ({ row }) => <span>{row.original.vertical?.name ?? '—'}</span>,
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'is_external',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: ({ row }) => (
      <Badge variant={row.original.is_external ? 'secondary' : 'outline'}>
        {row.original.is_external ? 'External' : 'Internal'}
        {row.original.is_external && <span className="text-xs text-gray-500">({row.original.company?.name})</span>}
      </Badge>
    ),
    filterFn: (row, columnId, filterValue: string[]) => {
      if (!filterValue?.length) return true;
      return filterValue.includes(String(row.original.is_external));
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'columns',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Columns" />,
    cell: ({ row }) => {
      const count = row.original.columns?.length ?? 0;
      return (
        <Badge variant={count > 0 ? 'secondary' : 'outline'} className="font-normal">
          {count} column{count === 1 ? '' : 's'}
        </Badge>
      );
    },
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
    cell: ({ row }) => <FormattedDateTime date={row.original.created_at} />,
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
