import { Button } from '@/components/ui/button';
import { useLeadQualityProviders } from '@/hooks/use-lead-quality-providers';
import { Plus } from 'lucide-react';

export function ProvidersActions() {
  const { goToCreate } = useLeadQualityProviders();

  return (
    <Button onClick={goToCreate} className="gap-2">
      <Plus className="h-4 w-4" />
      Add provider
    </Button>
  );
}
