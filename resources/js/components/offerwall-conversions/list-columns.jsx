import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { FingerprintCell } from '@/components/visitors';
import { formatDateTime, formatDateTimeUTC } from '@/utils/table';

export const columns = [
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
    accessorKey: 'integration.id',
    id: 'integration.id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Integration" />,
    cell: ({ row }) => {
      return <div className="font-medium">{row.original.integration.name}</div>;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'integration.company.id',
    id: 'integration.company.id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Company" />,
    cell: ({ row }) => {
      return <div className="font-medium">{row.original.integration.company.name}</div>;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'pathname',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Pathname" />,
    cell: ({ row }) => {
      return <div className="font-medium">{row.original.pathname}</div>;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'amount',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Payout" />,
    cell: ({ row }) => {
      const amount = parseFloat(row.getValue('amount'));
      const formatted = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
      }).format(amount);
      return <div className="font-medium">{formatted}</div>;
    },
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Created At" />,
    cell: ({ row }) => (
      <div className="text-sm">
        <div className="font-medium">{formatDateTime(row.original.created_at)}</div>
        <div className="text-xs whitespace-nowrap text-gray-500">{formatDateTimeUTC(row.original.created_at)}</div>
      </div>
    ),
  }
];
