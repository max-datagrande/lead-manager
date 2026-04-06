import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { FirePostbacksModal } from '@/components/ping-post/dispatches/fire-postbacks-modal';
import { StatusBadge } from '@/components/ping-post/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useModal } from '@/hooks/use-modal';
import type { LeadDispatch } from '@/types/ping-post';
import { Link } from '@inertiajs/react';
import type { CellContext, ColumnDef } from '@tanstack/react-table';
import { Eye, Zap } from 'lucide-react';
import { route } from 'ziggy-js';

const STRATEGY_LABELS: Record<string, string> = {
  best_bid: 'Best Bid',
  waterfall: 'Waterfall',
  combined: 'Combined',
};

function formatMs(ms: number | null) {
  if (!ms) return '—';
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

function ActionsCell({ row, table }: CellContext<LeadDispatch, unknown>) {
  const modal = useModal();
  const d = row.original;
  const meta = table.options.meta as any;

  const postbacks = meta?.workflowPostbacks?.[d.workflow_id] ?? [];
  const alreadyFired = meta?.firedDispatches?.includes(d.dispatch_uuid);
  const showFire = d.status === 'sold' && postbacks.length > 0 && !alreadyFired;

  return (
    <div className="flex items-center gap-0.5">
      {showFire && (
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 text-amber-500 hover:text-amber-600"
              onClick={async () => {
                const fired = await modal.openAsync<boolean>(<FirePostbacksModal dispatchId={d.id} postbacks={postbacks} />);
                if (fired) {
                  meta?.markAsFired?.(d.dispatch_uuid);
                }
              }}
            >
              <Zap className="h-4 w-4" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Fire postbacks</TooltipContent>
        </Tooltip>
      )}
      <Button variant="ghost" size="icon" asChild>
        <Link href={route('ping-post.dispatches.show', d.id)}>
          <Eye className="h-4 w-4" />
        </Link>
      </Button>
    </div>
  );
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
    cell: ({ cell }) => <span className="font-mono text-xs text-muted-foreground">{String(cell.getValue()).slice(0, 12)}…</span>,
    enableSorting: false,
  },
  {
    accessorKey: 'workflow',
    header: 'Workflow',
    cell: ({ row }) => <span className="text-sm">{row.original.workflow?.name ?? `#${row.original.workflow_id}`}</span>,
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
    accessorKey: 'winner_integration',
    header: 'Winner',
    cell: ({ row }) => <span className="text-sm">{row.original.winner_integration?.name ?? '—'}</span>,
    enableSorting: false,
  },
  {
    accessorKey: 'final_price',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Price" />,
    cell: ({ cell }) => {
      const v = cell.getValue<string | null>();
      return v ? <span className="font-medium text-green-600">${Number(v).toFixed(2)}</span> : <span className="text-muted-foreground">—</span>;
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
    cell: ({ cell }) => <span className="text-sm text-muted-foreground">{new Date(cell.getValue<string>()).toLocaleString()}</span>,
    enableSorting: true,
  },
  {
    id: 'actions',
    header: 'Actions',
    cell: ActionsCell,
    enableSorting: false,
  },
];
