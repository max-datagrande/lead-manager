import { Button } from '@/components/ui/button';
import { useFields } from '@/hooks/use-fields';
import { Plus } from 'lucide-react';

/**
 * Componente de acciones para la página de fields
 * Contiene el botón para crear nuevas entradas
 */
export const FieldsActions = () => {
  const { showCreateModal } = useFields();

  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add Field
    </Button>
  );
};
