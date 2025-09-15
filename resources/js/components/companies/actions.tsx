import { Button } from '@/components/ui/button';
import { useCompanies } from '@/hooks/use-companies';
import { Plus } from 'lucide-react';

/**
 * Componente de acciones para la página de companies
 * Contiene el botón para crear nuevas entradas
 */
export const CompaniesActions = () => {
  const { showCreateModal } = useCompanies();

  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add Company
    </Button>
  );
};
