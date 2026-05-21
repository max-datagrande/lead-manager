import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { FormattedDateTime } from '@/components/formatted-date-time';
import { StageBadge } from '@/components/ping-post/stage-badge';
import { StatusBadge } from '@/components/ping-post/status-badge';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { BuyerEventRow } from '@/types/buyer-events';
import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye } from 'lucide-react';
import { route } from 'ziggy-js';

const HIDDEN_FILTER_COLUMN = {
  header: () => null,
  cell: () => null,
  enableHiding: false,
  size: 0,
  minSize: 0,
  maxSize: 0,
} as const;

function formatMs(ms: number | null): string {
  if (ms === null || ms === undefined) return '—';
  return ms >= 1000 ? `${(ms / 1000).toFixed(1)}s` : `${ms}ms`;
}

function formatMoney(v: string | number | null): React.ReactNode {
  if (v === null || v === undefined || v === '') {
    return <span className="text-muted-foreground">—</span>;
  }
  return <span className="font-medium text-green-600">${Number(v).toFixed(2)}</span>;
}

function formatReason(reason: string | null): React.ReactNode {
  if (!reason) return <span className="text-muted-foreground">—</span>;
  const label = reason.replace(/_/g, ' ');
  return <span className="text-xs text-muted-foreground capitalize">{label}</span>;
}

export const buyerEventColumns: ColumnDef<BuyerEventRow>[] = [
  {
    id: 'actions',
    header: 'Actions',
    cell: ({ row }) => (
      <div className="flex items-center gap-0.5">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button variant="ghost" size="icon" asChild>
              <Link href={route('ping-post.dispatches.show', row.original.lead_dispatch_id)}>
                <Eye className="h-4 w-4" />
              </Link>
            </Button>
          </TooltipTrigger>
          <TooltipContent>View dispatch</TooltipContent>
        </Tooltip>
      </div>
    ),
    enableSorting: false,
  },
  // Filter-only hidden columns — registered so TanStack recognises the filter ids.
  { id: 'integration_id', ...HIDDEN_FILTER_COLUMN },
  { id: 'stage', ...HIDDEN_FILTER_COLUMN },
  { id: 'event_type', ...HIDDEN_FILTER_COLUMN },
  { id: 'reason', ...HIDDEN_FILTER_COLUMN },
  { id: 'workflow_id', ...HIDDEN_FILTER_COLUMN },
  { id: 'company_id', ...HIDDEN_FILTER_COLUMN },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Date" />,
    cell: ({ cell }) => <FormattedDateTime date={cell.getValue<string>()} />,
    enableSorting: true,
  },
  {
    id: 'stage_display',
    accessorKey: 'stage',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Stage" />,
    cell: ({ row }) => <StageBadge stage={row.original.stage} />,
    enableSorting: false,
  },
  {
    id: 'event_type_display',
    accessorKey: 'event_type',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Event Type" />,
    cell: ({ row }) => <StatusBadge status={row.original.event_type} variant="event" />,
    enableSorting: false,
  },
  {
    id: 'reason_display',
    accessorKey: 'reason',
    header: 'Reason',
    cell: ({ row }) => formatReason(row.original.reason),
    enableSorting: false,
  },
  {
    id: 'integration',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Buyer" />,
    cell: ({ row }) => <span className="text-sm whitespace-nowrap">{row.original.integration_name ?? '—'}</span>,
    enableSorting: true,
  },
  {
    id: 'company',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Company" />,
    cell: ({ row }) => <span className="text-sm">{row.original.company_name ?? '—'}</span>,
    enableSorting: true,
  },
  {
    id: 'workflow',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Workflow" />,
    cell: ({ row }) => <span className="text-sm whitespace-nowrap">{row.original.workflow_name ?? '—'}</span>,
    enableSorting: true,
  },
  {
    accessorKey: 'ping_bid',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Ping Bid" />,
    cell: ({ cell }) => formatMoney(cell.getValue<string | number | null>()),
    enableSorting: true,
  },
  {
    accessorKey: 'post_bid',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Post Bid" />,
    cell: ({ cell }) => formatMoney(cell.getValue<string | number | null>()),
    enableSorting: true,
  },
  {
    accessorKey: 'final_payout',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Final Payout" />,
    cell: ({ cell }) => formatMoney(cell.getValue<string | number | null>()),
    enableSorting: true,
  },
  {
    accessorKey: 'http_status_code',
    header: ({ column }) => <DataTableColumnHeader column={column} title="HTTP" />,
    cell: ({ cell }) => {
      const v = cell.getValue<number | null>();
      return v ? <span className="font-mono text-xs">{v}</span> : <span className="text-muted-foreground">—</span>;
    },
    enableSorting: true,
  },
  {
    accessorKey: 'duration_ms',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Duration" />,
    cell: ({ cell }) => <span className="text-sm text-muted-foreground">{formatMs(cell.getValue<number | null>())}</span>,
    enableSorting: true,
  },
  {
    accessorKey: 'dispatch_uuid',
    header: 'Dispatch',
    cell: ({ row }) => {
      const uuid = row.original.dispatch_uuid;
      if (!uuid) return <span className="text-muted-foreground">—</span>;
      return (
        <Link href={route('ping-post.dispatches.show', row.original.lead_dispatch_id)} className="font-mono text-xs hover:underline" title={uuid}>
          {uuid.slice(0, 8)}
        </Link>
      );
    },
    enableSorting: false,
  },
];
