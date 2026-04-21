import { Button } from '@/components/ui/button';
import { useLeadQualityRules } from '@/hooks/use-lead-quality-rules';
import { Plus } from 'lucide-react';

export function RulesActions() {
  const { goToCreate } = useLeadQualityRules();

  return (
    <Button onClick={goToCreate} className="gap-2">
      <Plus className="h-4 w-4" />
      Add rule
    </Button>
  );
}
