import { Button } from '@/components/ui/button';
import { useLandings } from '@/hooks/use-landings';
import { Plus } from 'lucide-react';

export const LandingPagesActions = () => {
  const { showCreateModal } = useLandings();
  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add Landing Page
    </Button>
  );
};
