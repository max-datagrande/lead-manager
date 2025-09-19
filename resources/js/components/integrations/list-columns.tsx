import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { Edit, Eye, Trash2 } from 'lucide-react';

// --- Type Definitions ---
type Integration = {
  id: number;
  name: string;
  type: 'ping-post' | 'post-only' | 'offerwall';
  is_active: boolean;
};

// --- Celdas y Componentes Específicos ---

const StatusBadge = ({ isActive }: { isActive: boolean }) => (
  <Badge variant={isActive ? 'default' : 'destructive'}>{isActive ? 'Active' : 'Inactive'}</Badge>
);

const TypeBadge = ({ type }: { type: Integration['type'] }) => {
  const typeClasses = {
    'ping-post': 'bg-blue-500',
    'post-only': 'bg-green-500',
    offerwall: 'bg-purple-500',
  };
  return <Badge className={`${typeClasses[type]} text-white`}>{type}</Badge>;
};

const ActionsCell = ({ row }: { row: { original: Integration } }) => {
  const integration = row.original;

  return (
    <div className="flex items-center gap-2">
      <Link href={route('integrations.show', integration.id)}>
        <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
          <Eye className="h-4 w-4" />
        </Button>
      </Link>
      <Link href={route('integrations.edit', integration.id)}>
        <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
          <Edit className="h-4 w-4" />
        </Button>
      </Link>
      {/* El borrado puede ser un modal de confirmación, lo implementaremos después */}
      <Button
        variant="ghost"
        size="sm"
        // onClick={() => showDeleteModal(integration)}
        className="h-8 w-8 p-0 text-destructive hover:text-destructive"
      >
        <Trash2 className="h-4 w-4" />
      </Button>
    </div>
  );
};

// --- Definición de Columnas para TanStack ---

export const columns = [
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    cell: ({ cell }) => <div className="px-2">{cell.getValue()}</div>,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
  },
  {
    accessorKey: 'type',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: ({ row }) => <TypeBadge type={row.original.type} />,
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <StatusBadge isActive={row.original.is_active} />,
  },
  {
    id: 'actions',
    header: 'Actions',
    cell: ActionsCell,
  },
];
