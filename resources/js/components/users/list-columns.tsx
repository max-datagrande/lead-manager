import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useUsers } from '@/hooks/use-users';
import { formatDateTime } from '@/utils/table';
import { Edit, ShieldOff } from 'lucide-react';

const RoleBadge = ({ role }: { role: string }) => {
  const variant = role === 'admin' ? 'default' : role === 'manager' ? 'secondary' : 'outline';

  return <Badge variant={variant}>{role}</Badge>;
};

const StatusBadge = ({ isActive }: { isActive: boolean }) => {
  return <Badge variant={isActive ? 'default' : 'destructive'}>{isActive ? 'Active' : 'Inactive'}</Badge>;
};

const ActionsCell = ({ row }) => {
  const { authUser, showEditModal, showDeactivateModal } = useUsers();
  const user = row.original;

  return (
    <div className="flex items-center gap-2">
      <Button variant="ghost" size="sm" onClick={() => showEditModal(user)} className="h-8 w-8 p-0">
        <Edit className="h-4 w-4" />
      </Button>
      {user.is_active && (
        <Button
          variant="ghost"
          size="sm"
          onClick={() => showDeactivateModal(user)}
          className="h-8 w-8 p-0 text-destructive hover:text-destructive"
          disabled={authUser.id === user.id}
        >
          <ShieldOff className="h-4 w-4" />
        </Button>
      )}
    </div>
  );
};

export const usersColumns = [
  {
    id: 'name',
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    cell: ({ row }) => <div className="text-sm text-foreground">{row.original.name}</div>,
    enableSorting: true,
    enableHiding: false,
  },
  {
    id: 'email',
    accessorKey: 'email',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Email" />,
    cell: ({ row }) => <div className="text-sm text-foreground">{row.original.email}</div>,
    enableSorting: true,
    enableHiding: false,
  },
  {
    id: 'role',
    accessorKey: 'role',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Role" />,
    cell: ({ row }) => <RoleBadge role={row.original.role} />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'is_active',
    accessorKey: 'is_active',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <StatusBadge isActive={row.original.is_active} />,
    filterFn: (row: any, columnId: string, filterValue: any[]) => {
      const cellValue = row.getValue(columnId);
      return filterValue.includes(cellValue);
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'email_verified_at',
    accessorKey: 'email_verified_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Verified" />,
    cell: ({ row }) => (
      <div className="text-sm text-muted-foreground">
        {row.original.email_verified_at ? formatDateTime(row.original.email_verified_at) : 'Pending'}
      </div>
    ),
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'created_at',
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created" />,
    cell: ({ row }) => <div className="text-sm text-muted-foreground">{formatDateTime(row.original.created_at)}</div>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'actions',
    header: 'Actions',
    cell: ActionsCell,
    enableHiding: false,
    enableSorting: false,
  },
];
