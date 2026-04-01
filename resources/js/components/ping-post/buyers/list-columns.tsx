import { DataTableColumnHeader } from '@/components/data-table/column-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import type { Buyer } from '@/types/ping-post'
import { Link, router } from '@inertiajs/react'
import type { ColumnDef } from '@tanstack/react-table'
import { Copy, Edit, Eye, MoreHorizontal, Trash2 } from 'lucide-react'
import { route } from 'ziggy-js'

function ActionsCell({ row }: { row: { original: Buyer } }) {
  const buyer = row.original

  const handleDelete = () => {
    if (confirm(`Delete buyer "${buyer.name}"? This cannot be undone.`)) {
      router.delete(route('ping-post.buyers.destroy', buyer.id))
    }
  }

  const handleDuplicate = () => {
    router.post(route('ping-post.buyers.duplicate', buyer.id))
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon">
          <MoreHorizontal className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem asChild>
          <Link href={route('ping-post.buyers.show', buyer.id)}>
            <Eye className="mr-2 h-4 w-4" />
            View
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route('ping-post.buyers.edit', buyer.id)}>
            <Edit className="mr-2 h-4 w-4" />
            Edit
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem onClick={handleDuplicate}>
          <Copy className="mr-2 h-4 w-4" />
          Duplicate
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={handleDelete} className="text-destructive">
          <Trash2 className="mr-2 h-4 w-4" />
          Delete
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}

export const buyerColumns: ColumnDef<Buyer>[] = [
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    cell: ({ cell }) => <span className="text-muted-foreground text-sm">#{cell.getValue<number>()}</span>,
    enableSorting: true,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    cell: ({ row }) => (
      <Link href={route('ping-post.buyers.show', row.original.id)} className="font-medium hover:underline">
        {row.original.name}
      </Link>
    ),
    enableSorting: true,
  },
  {
    accessorKey: 'type',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Type" />,
    cell: ({ cell }) => {
      const v = cell.getValue<string>()
      return (
        <Badge variant="outline" className="text-xs">
          {v === 'ping-post' ? 'Ping-Post' : 'Post-Only'}
        </Badge>
      )
    },
    filterFn: (row, _, filterValue: string[]) => !filterValue?.length || filterValue.includes(row.original.type),
    enableSorting: true,
  },
  {
    accessorKey: 'company',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Company" />,
    cell: ({ row }) => <span className="text-sm text-muted-foreground">{row.original.company?.name ?? '—'}</span>,
    enableSorting: false,
  },
  {
    accessorKey: 'buyerConfig',
    header: 'Pricing',
    cell: ({ row }) => {
      const cfg = row.original.buyerConfig
      if (!cfg) return <span className="text-muted-foreground text-xs">—</span>
      const label = { fixed: 'Fixed', min_bid: 'Min Bid', conditional: 'Conditional', postback: 'Postback' }[cfg.pricing_type] ?? cfg.pricing_type
      const price = cfg.fixed_price ? `$${Number(cfg.fixed_price).toFixed(2)}` : ''
      return (
        <span className="text-sm">
          {label}
          {price && <span className="ml-1 text-muted-foreground">{price}</span>}
        </span>
      )
    },
    enableSorting: false,
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => (
      <Badge variant="outline" className={row.original.is_active ? 'border-green-500 text-green-600' : 'border-red-400 text-red-500'}>
        {row.original.is_active ? 'Active' : 'Inactive'}
      </Badge>
    ),
    filterFn: (row, _, filterValue: string[]) => !filterValue?.length || filterValue.includes(String(row.original.is_active)),
    enableSorting: true,
  },
  {
    id: 'actions',
    header: '',
    cell: ActionsCell,
    enableSorting: false,
    enableHiding: false,
  },
]
