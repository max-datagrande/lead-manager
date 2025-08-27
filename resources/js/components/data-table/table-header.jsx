import { TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { flexRender } from '@tanstack/react-table';

export function DataTableHeader({ table }) {
  return (
    <TableHeader>
      {table.getHeaderGroups().map((headerGroup) => (
        <TableRow key={headerGroup.id}>
          {headerGroup.headers.map((header) => (
            <TableHead key={header.id} colSpan={header.colSpan} className="p-2 whitespace-nowrap">
              {header.isPlaceholder ? null : flexRender(header.column.columnDef.header, header.getContext())}
            </TableHead>
          ))}
        </TableRow>
      ))}
    </TableHeader>
  );
}
