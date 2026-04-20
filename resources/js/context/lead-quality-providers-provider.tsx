import { useModal } from '@/hooks/use-modal';
import { useToast } from '@/hooks/use-toast';
import type { ProviderRow } from '@/types/models/lead-quality';
import { router, useForm } from '@inertiajs/react';
import { createContext, type ReactNode } from 'react';

type ContextValue = {
  showDeleteModal: (entry: ProviderRow) => void;
  goToCreate: () => void;
  goToEdit: (entry: ProviderRow) => void;
};

export const LeadQualityProvidersContext = createContext<ContextValue | null>(null);

export function LeadQualityProvidersProvider({ children }: { children: ReactNode }) {
  const modal = useModal();
  const { addMessage } = useToast();
  const { delete: destroy } = useForm();

  const goToCreate = () => {
    router.visit(route('lead-quality.providers.create'));
  };

  const goToEdit = (entry: ProviderRow) => {
    router.visit(route('lead-quality.providers.edit', entry.id));
  };

  const showDeleteModal = async (entry: ProviderRow) => {
    const confirmed = await modal.confirm({
      title: 'Delete Provider',
      description: `Are you sure you want to delete "${entry.name}"? This cannot be undone.`,
      confirmText: 'Delete',
      cancelText: 'Cancel',
      destructive: true,
    });

    if (!confirmed) return;

    addMessage('Deleting provider', 'info');
    destroy(route('lead-quality.providers.destroy', entry.id), {
      preserveScroll: true,
      preserveState: true,
    });
  };

  return <LeadQualityProvidersContext.Provider value={{ showDeleteModal, goToCreate, goToEdit }}>{children}</LeadQualityProvidersContext.Provider>;
}
