import { DataTableContent } from '@/components/data-table/table-content';
import { DataTableHeader } from '@/components/data-table/table-header';
import { DataTableToolbar } from '@/components/data-table/toolbar';
import { Table, TableBody } from '@/components/ui/table';
import { getSortState } from '@/utils/table';
import { getCoreRowModel, getFilteredRowModel, useReactTable } from '@tanstack/react-table';
import { useState } from 'react';
import { DataTableColumnHeader } from '@/components/data-table/column-header';


const columns = [
  {
    accessorKey: 'id',
    header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'label',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Label" />,
    enableSorting: true,
    enableHiding: true,
  },
  {
    accessorKey: 'name',
    header: ({ column }) => <DataTableColumnHeader column={column} title="Parameter Name" />,
    enableSorting: true,
    enableHiding: true,
  },
];
export function TableFields({ fields, state }) {
  const { sort } = state;
  const [resetTrigger, setResetTrigger] = useState(false);
  const table = useReactTable({
    data: fields,
    columns: columns,
    state: {
      sorting: sort ? getSortState(sort) : [],
    },
    getCoreRowModel: getCoreRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    globalFilterFn: 'includesString ',
  });

  return (
    <>
      {/* Filtros */}
      <div className="mb-4">
        <div className="mb-4 flex justify-between gap-2">
          <DataTableToolbar
            table={table}
            searchPlaceholder="Search..."
            resetTrigger={resetTrigger}
            setResetTrigger={setResetTrigger}
          />
        </div>
      </div>
      <div className="rounded-md border">
        <Table>
          <DataTableHeader table={table} />
          <TableBody>
            <DataTableContent table={table} data={fields} />
          </TableBody>
        </Table>
      </div>
    </>
  );
}
