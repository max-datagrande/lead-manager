import { Button } from '@/components/ui/button';
import { useVerticals } from '@/hooks/use-verticals';
import { Plus } from 'lucide-react';

export const VerticalsActions = () => {
  const { showCreateModal } = useVerticals(); // <-- changed
  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add Vertical
    </Button>
  );
};