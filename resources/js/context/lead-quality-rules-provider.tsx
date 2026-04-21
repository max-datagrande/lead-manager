import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import type { RuleRow } from '@/types/models/lead-quality';
import { router, useForm } from '@inertiajs/react';
import { createContext, type ReactNode } from 'react';

type ContextValue = {
  goToCreate: () => void;
  goToEdit: (entry: RuleRow) => void;
  showDeleteModal: (entry: RuleRow) => void;
};

export const LeadQualityRulesContext = createContext<ContextValue | null>(null);

export function LeadQualityRulesProvider({ children }: { children: ReactNode }) {
  const modal = useModal();
  const { addMessage } = useToast();
  const { delete: destroy } = useForm();

  const goToCreate = () => {
    router.visit(route('lead-quality.validation-rules.create'));
  };

  const goToEdit = (entry: RuleRow) => {
    router.visit(route('lead-quality.validation-rules.edit', entry.id));
  };

  const showDeleteModal = async (entry: RuleRow) => {
    const confirmed = await modal.confirm({
      title: 'Delete Validation Rule',
      description: `Delete "${entry.name}"? Buyers linked to this rule will stop requiring its validation. This cannot be undone.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });

    if (!confirmed) return;

    addMessage('Deleting validation rule', 'info');
    destroy(route('lead-quality.validation-rules.destroy', entry.id), {
      preserveScroll: true,
      preserveState: true,
    });
  };

  return <LeadQualityRulesContext.Provider value={{ goToCreate, goToEdit, showDeleteModal }}>{children}</LeadQualityRulesContext.Provider>;
}
