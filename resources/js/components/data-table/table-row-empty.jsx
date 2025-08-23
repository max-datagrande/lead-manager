import { TableCell, TableRow } from '@/components/ui/table';
import { SearchX } from 'lucide-react';
/**
 * Componente para mostrar mensaje cuando no hay datos
 *
 * @returns {JSX.Element} Mensaje de estado vacío
 */
export default function TableRowEmpty({ colSpan, children }) {
  return (
    <TableRow>
      <TableCell colSpan={colSpan}>
        <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-gray-500">
          <SearchX className="h-8 w-8" />
          <span>{children}</span>
        </div>
      </TableCell>
    </TableRow>
  );
}
