export const columns = [
  {
    accessorKey: 'integration.name',
    header: 'Integration',
  },
  {
    accessorKey: 'company.name',
    header: 'Company',
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
