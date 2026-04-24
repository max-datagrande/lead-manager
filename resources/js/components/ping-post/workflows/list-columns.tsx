import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { WorkflowSnippetsModal } from '@/components/ping-post/workflows/snippets-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { Workflow } from '@/types/ping-post';
import { Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Code, Copy, Edit, Eye, MoreHorizontal, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';

const STRATEGY_LABELS: Record<string, string> = {
  best_bid: 'Best Bid',
  waterfall: 'Waterfall',
  combined: 'Combined',
};

function ActionsCell({ row }: { row: { original: Workflow } }) {
  const workflow = row.original;
  const [snippetOpen, setSnippetOpen] = useState(false);

  const handleDelete = () => {
    if (confirm(`Delete workflow "${workflow.name}"? This cannot be undone.`)) {
      router.delete(route('ping-post.workflows.destroy', workflow.id));
    }
  };

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="icon">
            <MoreHorizontal className="h-4 w-4" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          <DropdownMenuItem asChild>
            <Link href={route('ping-post.workflows.show', workflow.id)}>
              <Eye className="mr-2 h-4 w-4" />
              View
            </Link>
          </DropdownMenuItem>
          <DropdownMenuItem asChild>
            <Link href={route('ping-post.workflows.edit', workflow.id)}>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Link>
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => router.post(route('ping-post.workflows.duplicate', workflow.id))}>
            <Copy className="mr-2 h-4 w-4" />
            Duplicate
          </DropdownMenuItem>
          <DropdownMenuItem onClick={() => setSnippetOpen(true)}>
            <Code className="mr-2 h-4 w-4" />
            Show snippet
          </DropdownMenuItem>
          <DropdownMenuSeparator />
          <DropdownMenuItem onClick={handleDelete} className="text-destructive">
            <Trash2 className="mr-2 h-4 w-4" />
            Delete
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
      <WorkflowSnippetsModal workflow={workflow} open={snippetOpen} onOpenChange={setSnippetOpen} />
    </>
  );
}

export const workflowColumns: ColumnDef<Workflow>[] = [
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    cell: ({ cell }) => <span className="text-sm text-muted-foreground">#{cell.getValue<number>()}</span>,
    enableSorting: true,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Name" />,
    cell: ({ row }) => (
      <Link href={route('ping-post.workflows.show', row.original.id)} className="font-medium hover:underline">
        {row.original.name}
      </Link>
    ),
    enableSorting: true,
  },
  {
    accessorKey: 'strategy',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Strategy" />,
    cell: ({ cell }) => (
      <Badge variant="outline" className="text-xs">
        {STRATEGY_LABELS[cell.getValue<string>()] ?? cell.getValue<string>()}
      </Badge>
    ),
    filterFn: (row, _, filterValue: string[]) => !filterValue?.length || filterValue.includes(row.original.strategy),
    enableSorting: true,
  },
  {
    accessorKey: 'execution_mode',
    header: 'Mode',
    cell: ({ cell }) => <span className="text-sm text-muted-foreground capitalize">{cell.getValue<string>()}</span>,
    enableSorting: false,
  },
  {
    accessorKey: 'workflow_buyers_count',
    header: 'Buyers',
    cell: ({ cell }) => <span className="text-sm">{cell.getValue<number>() ?? 0}</span>,
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
];
