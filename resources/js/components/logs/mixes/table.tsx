import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { Link } from '@inertiajs/react';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import { ArrowRight } from 'lucide-react';
import { FingerprintCell } from '@/components/visitors';
import { DataTableColumnHeader } from '@/components/data-table/column-header';

// Define the shape of our data
interface OfferwallMixLog {
  id: number;
  offerwall_mix: { name: string };
  origin: string;
  successful_integrations: number;
  failed_integrations: number;
  total_integrations: number;
  total_offers_aggregated: number;
  total_duration_ms: number;
  created_at: string;
}

// Define the columns for the table
export const columns: ColumnDef<OfferwallMixLog>[] = [
  {
    accessorKey: 'id',
    header: 'ID',
  },
  {
    accessorKey: 'fingerprint',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Short Fingerprint" />,
    cell: ({ row }) => {
      return <FingerprintCell fingerprint={row.original.fingerprint} />;
    },
    enableSorting: false,
    enableHiding: true,
  },
  {
    accessorKey: 'offerwall_mix.name',
    header: 'Mix Name',
  },
  {
    accessorKey: 'origin',
    header: 'Origin',
  },
  {
    header: 'Status',
    cell: ({ row }) => {
      const { successful_integrations, failed_integrations, total_integrations } = row.original;
      let status: 'success' | 'partial' | 'failed' = 'failed';
      let variant: 'success' | 'warning' | 'destructive' = 'destructive';

      if (failed_integrations === 0 && total_integrations > 0) {
        status = 'success';
        variant = 'success';
      } else if (successful_integrations > 0 && failed_integrations > 0) {
        status = 'partial';
        variant = 'warning';
      }
      return (
        <Badge variant={variant} className="capitalize">
          {status}
        </Badge>
      );
    },
  },
  {
    header: 'Integrations',
    cell: ({ row }) => {
      const { successful_integrations, total_integrations } = row.original;
      return `${successful_integrations} / ${total_integrations}`;
    },
  },
  {
    accessorKey: 'total_offers_aggregated',
    header: 'Offers',
  },
  {
    header: 'Duration',
    cell: ({ row }) => {
      const duration = row.original.total_duration_ms;
      return `${(duration / 1000).toFixed(2)}s`;
    },
  },
  {
    header: 'Timestamp',
    cell: ({ row }) => {
      return format(new Date(row.original.created_at), 'yyyy-MM-dd HH:mm:ss');
    },
  },
  {
    id: 'actions',
    cell: ({ row }) => (
      <Button asChild variant="outline" size="icon">
        <Link href={route('logs.offerwall-mixes.show', row.original.id)}>
          <ArrowRight className="h-4 w-4" />
        </Link>
      </Button>
    ),
  },
];

interface MixLogsTableProps {
  data: OfferwallMixLog[];
}

export function MixLogsTable({ data }: MixLogsTableProps) {
  return <DataTable columns={columns} data={data} />;
}
