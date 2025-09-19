import { IntegrationsContext } from '@/context/integrations-provider.jsx';
import { useContext } from 'react';

export const useIntegrations = () => {
  const context = useContext(IntegrationsContext);
  if (!context) {
    throw new Error('useIntegrations must be used within an IntegrationsProvider');
  }
  return context;
};
