import { Button } from '@/components/ui/button';
import { useAlertChannels } from '@/hooks/use-alert-channels';
import { Plus } from 'lucide-react';

export const AlertChannelsActions = () => {
  const { showCreateModal } = useAlertChannels();

  return (
    <div className="flex items-center gap-2">
      <Button onClick={showCreateModal} className="flex items-center gap-2">
        <Plus className="h-4 w-4" />
        Add Channel
      </Button>
    </div>
  );
};
