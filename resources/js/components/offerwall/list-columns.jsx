import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Button } from '@/components/ui/button';
import { useOfferwall } from '@/hooks/use-offerwall';
import { Edit, Trash2 } from 'lucide-react';

// Componente para las acciones de la fila
const ActionsCell = ({ row }) => {
  const { showEditModal, showDeleteModal } = useOfferwall();
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
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    cell: ({ row }) => <div>{row.original.name}</div>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created At" />,
    cell: ({ row }) => <div>{row.original.created_at}</div>,
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'actions',
    cell: ActionsCell,
  },
];
