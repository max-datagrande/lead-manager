import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ValidationLogRow } from '@/types/models/lead-quality';
import { formatDateTime } from '@/utils/table';
import type { ColumnDef } from '@tanstack/react-table';
import { Eye } from 'lucide-react';
import { StatusBadge } from './status-badge';

interface ColumnContext {
  onOpenDetail: (id: number) => void;
}

export function createLogColumns({ onOpenDetail }: ColumnContext): ColumnDef<ValidationLogRow>[] {
  return [
    {
      accessorKey: 'id',
      header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
      cell: ({ row }) => <code className="text-xs text-muted-foreground">#{row.original.id}</code>,
      enableSorting: true,
    },
    {
      accessorKey: 'status',
      header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }) => <StatusBadge status={row.original.status} />,
      enableSorting: true,
    },
    {
      id: 'rule',
      header: 'Rule',
      cell: ({ row }) =>
        row.original.rule ? (
          <div className="flex flex-col">
            <span className="text-sm">{row.original.rule.name}</span>
            {row.original.rule.validation_type && (
              <Badge variant="outline" className="mt-0.5 w-fit text-xs">
                {row.original.rule.validation_type}
              </Badge>
            )}
          </div>
        ) : (
          <span className="text-xs text-muted-foreground">—</span>
        ),
      enableSorting: false,
    },
    {
      id: 'buyer',
      header: 'Buyer',
      cell: ({ row }) => row.original.buyer?.name ?? <span className="text-xs text-muted-foreground">—</span>,
      enableSorting: false,
    },
    {
      id: 'provider',
      header: 'Provider',
      cell: ({ row }) =>
        row.original.provider ? (
          <span className="text-sm">{row.original.provider.name}</span>
        ) : (
          <span className="text-xs text-muted-foreground">—</span>
        ),
      enableSorting: false,
    },
    {
      accessorKey: 'attempts_count',
      header: ({ column }) => <DataTableColumnHeader column={column} title="Attempts" />,
      cell: ({ row }) => <span className="font-mono text-sm">{row.original.attempts_count}</span>,
      enableSorting: true,
    },
    {
      id: 'fingerprint',
      header: 'Fingerprint',
      cell: ({ row }) =>
        row.original.fingerprint ? (
          <code className="max-w-[160px] truncate text-xs text-muted-foreground" title={row.original.fingerprint}>
            {row.original.fingerprint}
          </code>
        ) : (
          <span className="text-xs text-muted-foreground">—</span>
        ),
      enableSorting: false,
    },
    {
      accessorKey: 'created_at',
      header: ({ column }) => <DataTableColumnHeader column={column} title="Created" />,
      cell: ({ row }) => <span className="text-sm text-muted-foreground">{formatDateTime(row.original.created_at)}</span>,
      enableSorting: true,
    },
    {
      id: 'actions',
      header: '',
      cell: ({ row }) => (
        <div className="flex justify-end">
          <Button variant="ghost" size="sm" className="h-8 w-8 p-0" onClick={() => onOpenDetail(row.original.id)} aria-label="View detail">
            <Eye className="h-4 w-4" />
          </Button>
        </div>
      ),
      enableSorting: false,
    },
    // Filter-only columns: provide column IDs so the toolbar facets can attach without rendering.
    // Size 0 keeps them out of the header row.
    {
      id: 'validation_rule_id',
      accessorFn: (row) => row.rule?.id ?? null,
      header: () => null,
      cell: () => null,
      size: 0,
      enableSorting: false,
      enableHiding: false,
    },
    {
      id: 'integration_id',
      accessorFn: (row) => row.buyer?.id ?? null,
      header: () => null,
      cell: () => null,
      size: 0,
      enableSorting: false,
      enableHiding: false,
    },
    {
      id: 'provider_id',
      accessorFn: (row) => row.provider?.id ?? null,
      header: () => null,
      cell: () => null,
      size: 0,
      enableSorting: false,
      enableHiding: false,
    },
  ];
}
