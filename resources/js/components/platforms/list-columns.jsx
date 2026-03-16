import { DataTableColumnHeader } from '@/components/data-table/column-header'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { usePlatforms } from '@/hooks/use-platforms'
import { formatDateTime, formatDateTimeUTC } from '@/utils/table'
import { Edit, Trash2 } from 'lucide-react'

const ActionsCell = ({ row }) => {
  const { showEditModal, showDeleteModal } = usePlatforms()
  const entry = row.original

  return (
    <div className="flex items-center gap-2">
      <Button variant="ghost" size="sm" onClick={() => showEditModal(entry)} className="h-8 w-8 p-0">
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
  )
}

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
    id: 'company',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Company" />,
    cell: ({ row }) => row.original.company?.name ?? <span className="text-muted-foreground">—</span>,
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'token_mappings',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Token Mappings" />,
    cell: ({ row }) => {
      const mappings = row.original.token_mappings ?? {}
      console.log(mappings);
      const entries = Object.entries(mappings)
      console.log({ entries });
      if (!entries.length) return <span className="text-muted-foreground">—</span>
      return (
        <div className="flex flex-wrap gap-1">
          {entries.map(([external, internal]) => (
            <Badge key={external} variant="secondary" className="font-mono text-xs">
              {external} → {internal}
            </Badge>
          ))}
        </div>
      )
    },
    enableSorting: false,
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
]
