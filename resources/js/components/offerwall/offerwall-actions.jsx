import { Button } from '@/components/ui/button';
import { useOfferwall } from '@/hooks/use-offerwall';
import { Plus } from 'lucide-react';

/**
 * Componente de acciones para la página de offerwalls
 * Contiene el botón para crear nuevas entradas
 */
export const OfferwallActions = () => {
  const { showCreateModal } = useOfferwall();

  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Create Offerwall Mix
    </Button>
  );
};
