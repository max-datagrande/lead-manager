import { DataTableColumnHeader } from '@/components/data-table/column-header';
import { FingerprintCell } from '@/components/visitors';

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
    header: 'Integration',
    cell: ({ row }) => {
      console.log(row.original);
      return <div className="text-right font-medium">{row.original.integration.name}</div>;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'integration.company.id',
    id: 'integration.company.id',
    header: 'Company',
    cell: ({ row }) => {
      console.log(row.original);
      return <div className="text-right font-medium">{row.original.integration.company.name}</div>;
    },
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'amount',
    header: () => <div className="text-right">Payout</div>,
    cell: ({ row }) => {
      const amount = parseFloat(row.getValue('amount'));
      const formatted = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
      }).format(amount);
      return <div className="text-right font-medium">{formatted}</div>;
    },
  },
  {
    accessorKey: 'click_id',
    header: 'Click ID',
  },
  {
    accessorKey: 'created_at',
    header: 'Date',
    cell: ({ row }) => new Date(row.getValue('created_at')).toLocaleDateString(),
  },
  // Example for an actions column, can be implemented later
  // {
  //     id: 'actions',
  //     cell: ({ row }) => {
  //         const conversion = row.original;
  //         return (
  //             <button onClick={() => handleViewDetails(conversion.id)}>View</button>
  //         );
  //     },
  // },
];
