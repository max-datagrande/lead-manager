import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { DataTableRowActions } from '@/components/data-table/row-actions';
import { useOfferwall } from '@/hooks/use-offerwall';

export const columns = [
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    cell: ({ row }) => <div>{row.original.name}</div>,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created At" />,
    cell: ({ row }) => <div>{row.original.created_at}</div>,
  },
  {
    id: 'actions',
    cell: function Cell({ row }) {
      const { showEditModal, showDeleteModal } = useOfferwall();
      const entry = row.original;
      return (
        <DataTableRowActions
          onEdit={() => showEditModal(entry)}
          onDelete={() => showDeleteModal(entry)}
        />
      );
    },
  },
];
