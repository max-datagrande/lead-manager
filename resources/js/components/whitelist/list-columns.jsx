import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { formatDateTime } from '@/utils/table';
import { Edit, Trash2, Globe, Server } from 'lucide-react';
import { useWhitelist } from '@/hooks/use-whitelist';

// Componente para mostrar el tipo de entrada
const TypeBadge = ({ type }) => {
  const variants = {
    domain: { variant: 'default', icon: Globe, label: 'Domain' },
    ip: { variant: 'secondary', icon: Server, label: 'IP Address' }
  };

  const config = variants[type] || variants.domain;
  const Icon = config.icon;

  return (
    <Badge variant={config.variant} className="flex items-center gap-1">
      <Icon className="h-3 w-3" />
      {config.label}
    </Badge>
  );
};

// Componente para mostrar el estado activo/inactivo
const StatusBadge = ({ isActive }) => {
  return (
    <Badge variant={isActive ? 'default' : 'destructive'}>
      {isActive ? 'Active' : 'Inactive'}
    </Badge>
  );
};

// Componente para las acciones de la fila
const ActionsCell = ({ row }) => {
  const { showEditModal, showDeleteModal } = useWhitelist();
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

// --- Columnas TanStack ---
export const whitelistColumns = [
  {
    accessorKey: 'type',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: ({ row }) => <TypeBadge type={row.original.type} />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    cell: ({ row }) => (
      <div className="font-medium">
        {row.original.name || 'N/A'}
      </div>
    ),
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'value',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Value" />,
    cell: ({ row }) => (
      <div className="font-mono text-sm">
        {row.original.value}
      </div>
    ),
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <StatusBadge isActive={row.original.is_active} />,
    enableSorting: true,
    enableHiding: true,
    filterFn: 'booleanFilter',
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created At" />,
    cell: ({ row }) => (
      <div className="text-sm text-muted-foreground">
        {formatDateTime(row.original.created_at)}
      </div>
    ),
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'updated_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Updated At" />,
    cell: ({ row }) => (
      <div className="text-sm text-muted-foreground">
        {formatDateTime(row.original.updated_at)}
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
