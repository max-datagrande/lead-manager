import { LeadQualityProvidersContext } from '@/context/lead-quality-providers-provider';
import { useContext } from 'react';

export function useLeadQualityProviders() {
  const context = useContext(LeadQualityProvidersContext);
  if (!context) throw new Error('useLeadQualityProviders must be used within LeadQualityProvidersProvider');
  return context;
}
