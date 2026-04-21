import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { FormattedDateTime } from '@/components/formatted-date-time';
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
      <Tooltip>
        <TooltipTrigger asChild>
          <Button variant="ghost" size="icon" asChild>
            <Link href={route('ping-post.dispatches.show', d.id)}>
              <Eye className="h-4 w-4" />
            </Link>
          </Button>
        </TooltipTrigger>
        <TooltipContent>View details</TooltipContent>
      </Tooltip>
      {showFire && (
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              variant="ghost"
              size="icon"
              className="h-8 w-8 text-amber-500 hover:text-amber-600"
              onClick={async () => {
                const fired = await modal.openAsync<boolean>(<FirePostbacksModal dispatchId={d.id} postbacks={postbacks} />, {
                  maxWidth: 'sm:max-w-2xl',
                });
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
    </div>
  );
}

export const dispatchColumns: ColumnDef<LeadDispatch>[] = [
  {
    id: 'actions',
    header: 'Actions',
    cell: ActionsCell,
    enableSorting: false,
  },
  // Filter-only columns — no visible output, needed so TanStack recognises the filter ids
  { id: 'workflow_id', accessorKey: 'workflow_id', header: () => null, cell: () => null, enableHiding: false, size: 0, minSize: 0, maxSize: 0 },
  {
    id: 'winner_integration_id',
    accessorKey: 'winner_integration_id',
    header: () => null,
    cell: () => null,
    enableHiding: false,
    size: 0,
    minSize: 0,
    maxSize: 0,
  },
  { id: 'company_id', header: () => null, cell: () => null, enableHiding: false, size: 0, minSize: 0, maxSize: 0 },
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    cell: ({ row }) => (
      <Link href={route('ping-post.dispatches.show', row.original.id)} className="font-mono text-xs hover:underline px-2">
        #{row.original.id}
      </Link>
    ),
    enableSorting: true,
  },
  {
    accessorKey: 'fingerprint',
    header: 'Fingerprint',
    cell: ({ cell }) => <span className="font-mono text-xs text-muted-foreground whitespace-nowrap max-w-25 text-ellipsis block overflow-hidden">{String(cell.getValue())}</span>,
    enableSorting: false,
  },
  {
    accessorKey: 'workflow',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Workflow" />,
    cell: ({ row }) => <span className="text-sm whitespace-nowrap">{row.original.workflow?.name ?? `#${row.original.workflow_id}`}</span>,
    enableSorting: true,
  },
  {
    accessorKey: 'utm_source',
    header: ({ column }) => <DataTableColumnHeader column={column} title="UTM Source" />,
    cell: ({ cell }) => {
      const v = cell.getValue<string | null>();
      return v ? <span className="text-sm">{v}</span> : <span className="text-muted-foreground">—</span>;
    },
    enableSorting: true,
  },
  {
    accessorKey: 'strategy_used',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Strategy" />,
    cell: ({ cell }) => (
      <Badge variant="outline" className="text-xs">
        {STRATEGY_LABELS[cell.getValue<string>()] ?? cell.getValue<string>()}
      </Badge>
    ),
    enableSorting: true,
  },
  {
    accessorKey: 'status',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ cell }) => <StatusBadge status={cell.getValue<string>()} variant="dispatch" />,
    enableSorting: true,
  },
  {
    accessorKey: 'winner_integration',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Winner" />,
    cell: ({ row }) => <span className="text-sm whitespace-nowrap">{row.original.winner_integration?.name ?? '—'}</span>,
    enableSorting: true,
  },
  {
    id: 'company',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Company" />,
    cell: ({ row }) => {
      const company = row.original.winner_integration?.company;
      return <span className="text-sm">{company?.name ?? '—'}</span>;
    },
    enableSorting: true,
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
    header: ({ column }) => <DataTableColumnHeader column={column} title="Duration" />,
    cell: ({ cell }) => <span className="text-sm text-muted-foreground">{formatMs(cell.getValue<number | null>())}</span>,
    enableSorting: true,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Date" />,
    cell: ({ cell }) => <FormattedDateTime date={cell.getValue<string>()} />,
    enableSorting: true,
  }
];
