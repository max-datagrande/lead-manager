import { Button } from '@/components/ui/button';
import { useVerticalLandingPages } from '@/hooks/use-vertical-landing-pages';
import { Plus } from 'lucide-react';

export const LandingPagesActions = () => {
  const { showCreateModal } = useVerticalLandingPages();
  return (
    <Button onClick={showCreateModal} className="flex items-center gap-2">
      <Plus className="h-4 w-4" />
      Add Landing Page
    </Button>
  );
};