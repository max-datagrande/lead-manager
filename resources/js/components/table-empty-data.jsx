import { SearchX } from 'lucide-react';
/**
 * Componente para mostrar mensaje cuando no hay datos
 *
 * @returns {JSX.Element} Mensaje de estado vacío
 */
export function CellEmptyData() {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-8 text-center text-gray-500">
      <SearchX className="h-8 w-8" />
      <span>No visitors found.</span>
    </div>
  );
}
