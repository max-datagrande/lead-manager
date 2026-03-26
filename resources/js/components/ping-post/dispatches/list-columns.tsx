import { DataTableColumnHeader } from '@/components/data-table/column-header'
import { StatusBadge } from '@/components/ping-post/status-badge'
import { Badge } from '@/components/ui/badge'
import type { LeadDispatch } from '@/types/ping-post'
import { Link } from '@inertiajs/react'
import type { ColumnDef } from '@tanstack/react-table'
import { route } from 'ziggy-js'

const STRATEGY_LABELS: Record<string, string> = {
  best_bid: 'Best Bid',
  waterfall: 'Waterfall',
  combined: 'Combined',
}

function formatMs(ms: number | null) {
  if (!ms) return '—'
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`
}

export const dispatchColumns: ColumnDef<LeadDispatch>[] = [
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    cell: ({ row }) => (
      <Link href={route('ping-post.dispatches.show', row.original.id)} className="font-mono text-xs hover:underline">
        #{row.original.id}
      </Link>
    ),
    enableSorting: true,
  },
  {
    accessorKey: 'fingerprint',
    header: 'Fingerprint',
    cell: ({ cell }) => (
      <span className="font-mono text-xs text-muted-foreground">{String(cell.getValue()).slice(0, 12)}…</span>
    ),
    enableSorting: false,
  },
  {
    accessorKey: 'workflow',
    header: 'Workflow',
    cell: ({ row }) => (
      <span className="text-sm">{row.original.workflow?.name ?? `#${row.original.workflow_id}`}</span>
    ),
    enableSorting: false,
  },
  {
    accessorKey: 'strategy_used',
    header: 'Strategy',
    cell: ({ cell }) => (
      <Badge variant="outline" className="text-xs">
        {STRATEGY_LABELS[cell.getValue<string>()] ?? cell.getValue<string>()}
      </Badge>
    ),
    filterFn: (row, _, filterValue: string[]) => !filterValue?.length || filterValue.includes(row.original.strategy_used),
    enableSorting: false,
  },
  {
    accessorKey: 'status',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ cell }) => <StatusBadge status={cell.getValue<string>()} variant="dispatch" />,
    filterFn: (row, _, filterValue: string[]) => !filterValue?.length || filterValue.includes(row.original.status),
    enableSorting: true,
  },
  {
    accessorKey: 'winnerIntegration',
    header: 'Winner',
    cell: ({ row }) => (
      <span className="text-sm">{row.original.winnerIntegration?.name ?? '—'}</span>
    ),
    enableSorting: false,
  },
  {
    accessorKey: 'final_price',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Price" />,
    cell: ({ cell }) => {
      const v = cell.getValue<string | null>()
      return v ? <span className="font-medium text-green-600">${Number(v).toFixed(2)}</span> : <span className="text-muted-foreground">—</span>
    },
    enableSorting: true,
  },
  {
    accessorKey: 'total_duration_ms',
    header: 'Duration',
    cell: ({ cell }) => <span className="text-sm text-muted-foreground">{formatMs(cell.getValue<number | null>())}</span>,
    enableSorting: false,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Date" />,
    cell: ({ cell }) => (
      <span className="text-sm text-muted-foreground">
        {new Date(cell.getValue<string>()).toLocaleString()}
      </span>
    ),
    enableSorting: true,
  },
]
