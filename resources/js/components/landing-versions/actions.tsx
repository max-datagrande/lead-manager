import { Button } from '@/components/ui/button';
import { useVersions } from '@/hooks/use-landings';
import { Plus } from 'lucide-react';

export const LandingPagesVersionsActions = () => {
  const { showCreateModal } = useVersions();
  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add Landing Page Version
    </Button>
  );
};
