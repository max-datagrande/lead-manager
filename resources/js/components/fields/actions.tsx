import { Button } from '@/components/ui/button';
import { useFields } from '@/hooks/use-fields';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Plus, RefreshCw } from 'lucide-react';

/**
 * Componente de acciones para la página de fields
 * Contiene el botón para crear nuevas entradas y sincronizar desde producción
 */
export const FieldsActions = () => {
  const { showCreateModal, confirmSync } = useFields();
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
        Add Field
      </Button>
    </div>
  );
};
