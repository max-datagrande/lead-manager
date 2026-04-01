import { Button } from '@/components/ui/button';
import { useCompanies } from '@/hooks/use-companies';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Plus, RefreshCw } from 'lucide-react';

/**
 * Componente de acciones para la página de companies
 * Contiene el botón para crear nuevas entradas y sincronizar desde producción
 */
export const CompaniesActions = () => {
  const { showCreateModal, confirmSync } = useCompanies();
  const { props: { app } } = usePage<SharedData>();
  const isLocalEnv = app.env === 'local';

  return (
    <div className="flex items-center gap-2">
      {isLocalEnv && (
        <Button onClick={confirmSync} variant="outline" className="flex items-center gap-2">
          <RefreshCw className="h-4 w-4" />
          Sync
        </Button>
      )}
      <Button onClick={showCreateModal} className="flex items-center gap-2">
        <Plus className="h-4 w-4" />
        Add Company
      </Button>
    </div>
  );
};
