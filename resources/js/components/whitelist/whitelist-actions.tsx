import { Button } from '@/components/ui/button';
import { useWhitelist } from '@/hooks/use-whitelist';
import { Plus } from 'lucide-react';

/**
 * Componente de acciones para la página de whitelist
 * Contiene el botón para crear nuevas entradas
 */
export const WhitelistActions = () => {
  const { showCreateModal } = useWhitelist();

  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add Entry
    </Button>
  );
};