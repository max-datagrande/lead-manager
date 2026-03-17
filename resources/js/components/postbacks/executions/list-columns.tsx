import CopyToClipboard from '@/components/copy-to-clipboard';
import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { FormattedDateTime } from '@/components/formatted-date-time';
import { type PostbackExecution } from '@/components/postbacks/executions';
import { DispatchLogsViewer } from '@/components/postbacks/executions/dispatch-logs-viewer';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useModal } from '@/hooks/use-modal';
import { type ColumnDef } from '@tanstack/react-table';
import { CheckCircle, Clock, Loader, MinusCircle, SlidersHorizontal, XCircle } from 'lucide-react';

const statusConfig: Record<PostbackExecution['status'], { label: string; icon: React.ComponentType<{ className?: string }>; className: string }> = {
  pending: { label: 'Pending', icon: Clock, className: 'border-yellow-200 bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300' },
  dispatching: { label: 'Dispatching', icon: Loader, className: 'border-blue-200 bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300' },
  completed: {
    label: 'Completed',
    icon: CheckCircle,
    className: 'border-green-200 bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
  },
  failed: { label: 'Failed', icon: XCircle, className: 'border-red-200 bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' },
  skipped: { label: 'Skipped', icon: MinusCircle, className: 'border-gray-200 bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' },
};

function ActionsCell({ row }: { row: { original: PostbackExecution } }) {
  const modal = useModal();
  const execution = row.original;

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" className="h-8 w-8 p-0">
          <SlidersHorizontal className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start">
        <DropdownMenuItem
          className="cursor-pointer"
          onClick={() => modal.open(<DispatchLogsViewer execution={execution} />, { maxWidth: 'sm:max-w-3xl' })}
        >
          View Dispatch Logs
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

export const createExecutionColumns = (): ColumnDef<PostbackExecution>[] => [
  // Filter-only columns — no visible output, needed so TanStack recognises the filter ids
  {
    id: 'fire_mode',
    accessorFn: (row) => row.postback?.fire_mode ?? '',
    header: () => null,
    cell: () => null,
    enableHiding: false,
    size: 0,
    minSize: 0,
    maxSize: 0,
  },
  {
    id: 'postback_id',
    accessorKey: 'postback_id',
    header: () => null,
    cell: () => null,
    enableHiding: false,
    size: 0,
    minSize: 0,
    maxSize: 0,
  },
  {
    accessorKey: 'execution_uuid',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Execution UUID" />,
    cell: ({ row }) => {
      const uuid = row.getValue<string>('execution_uuid');
      return (
        <div className="flex items-center gap-2">
          <ActionsCell row={row} />
          <CopyToClipboard textToCopy={uuid}>
            <Tooltip>
              <TooltipTrigger asChild>
                <span className="w-12 truncate font-mono text-xs md:w-50">{uuid}</span>
              </TooltipTrigger>
              <TooltipContent className="max-w-xs bg-black text-white" arrowClassName="bg-black fill-black">
                {uuid}
              </TooltipContent>
            </Tooltip>
          </CopyToClipboard>
        </div>
      );
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    id: 'postback',
    accessorFn: (row) => row.postback?.name ?? '—',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Postback" />,
    cell: ({ row }) => {
      const execution = row.original;
      return (
        <div className="flex flex-col gap-1">
          <span className="text-sm font-medium whitespace-nowrap">{execution.postback?.name ?? '—'}</span>
          {execution.postback && (
            <Badge variant="outline" className="w-fit px-1.5 py-0 text-xs">
              {execution.postback.fire_mode}
            </Badge>
          )}
        </div>
      );
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'status',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => {
      const status = row.getValue<PostbackExecution['status']>('status');
      const cfg = statusConfig[status];
      const Icon = cfg.icon;
      return (
        <Badge variant="outline" className={cfg.className}>
          <Icon className="mr-1 h-3 w-3" />
          {cfg.label}
        </Badge>
      );
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    id: 'inbound_params',
    header: 'Inbound Params',
    cell: ({ row }) => {
      const params = row.original.inbound_params;
      if (!params || Object.keys(params).length === 0) return <span className="text-muted-foreground">—</span>;
      const entries = Object.entries(params);
      const preview = entries
        .slice(0, 2)
        .map(([k, v]) => `${k}=${v}`)
        .join(', ');
      const hasMore = entries.length > 2;
      return (
        <Tooltip>
          <TooltipTrigger asChild>
            <span className="cursor-help font-mono text-xs">
              {preview}
              {hasMore ? ` +${entries.length - 2} more` : ''}
            </span>
          </TooltipTrigger>
          <TooltipContent className="max-w-xs bg-black text-white" arrowClassName="bg-black fill-black">
            <pre className="text-xs whitespace-pre-wrap">{JSON.stringify(params, null, 2)}</pre>
          </TooltipContent>
        </Tooltip>
      );
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'outbound_url',
    header: 'Outbound URL',
    cell: ({ row }) => {
      const url = row.getValue<string | null>('outbound_url');
      if (!url) return <span className="text-muted-foreground">—</span>;
      return (
        <Tooltip>
          <TooltipTrigger asChild>
            <span className="block max-w-[180px] cursor-help truncate font-mono text-xs">{url}</span>
          </TooltipTrigger>
          <TooltipContent className="max-w-sm bg-black text-white" arrowClassName="bg-black fill-black">
            <span className="text-xs break-all">{url}</span>
          </TooltipContent>
        </Tooltip>
      );
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'attempts',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Attempts" />,
    cell: ({ row }) => {
      const { attempts, max_attempts } = row.original;
      return (
        <span className="font-mono text-xs">
          {attempts}/{max_attempts}
        </span>
      );
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'ip_address',
    header: 'IP',
    cell: ({ row }) => <span className="font-mono text-xs text-muted-foreground">{row.getValue('ip_address') ?? '—'}</span>,
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'dispatched_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Dispatched" />,
    cell: ({ row }) => {
      const val = row.getValue<string | null>('dispatched_at');
      return <FormattedDateTime date={val} />;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'completed_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Completed" />,
    cell: ({ row }) => {
      const val = row.getValue<string | null>('completed_at');
      return <FormattedDateTime date={val} />;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created" />,
    cell: ({ row }) => <FormattedDateTime date={row.getValue('created_at')} />,
    enableSorting: true,
    enableHiding: true,
  },
];
