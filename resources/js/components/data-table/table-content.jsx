import TableRowEmpty from '@/components/data-table/table-row-empty';
import { TableCell, TableRow } from '@/components/ui/table';
import { flexRender } from '@tanstack/react-table';

export function DataTableContent({ table, data}) {
  if (data.length === 0) {
    return <TableRowEmpty colSpan={table.getAllColumns().length}>No data found.</TableRowEmpty>;
  }

  const rowModel = table.getRowModel();
  if (rowModel.rows.length === 0) {
    return <TableRowEmpty colSpan={table.getAllColumns().length}>No data found.</TableRowEmpty>;
  }

  return (
    <>
      {rowModel.rows.map((r) => (
        <TableRow key={r.id}>
          {r.getVisibleCells().map((cell) => (
            <TableCell key={cell.id} className="p-2">
              {flexRender(cell.column.columnDef.cell, cell.getContext())}
            </TableCell>
          ))}
        </TableRow>
      ))}
    </>
  );
}
