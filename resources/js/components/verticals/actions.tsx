import { Button } from '@/components/ui/button';
import { useVerticals } from '@/hooks/use-verticals';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Plus, RefreshCw } from 'lucide-react';

export const VerticalsActions = () => {
  const { showCreateModal, confirmSync } = useVerticals();
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
        Add Vertical
      </Button>
    </div>
  );
};